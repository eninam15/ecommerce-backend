<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Coupon;
use App\Models\Cart;
use Illuminate\Support\Facades\Log;

class CleanupExpiredCouponsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        try {
            Log::info('Iniciando limpieza de cupones expirados');
            
            // Remover cupones expirados de carritos
            $expiredCartCoupons = Cart::whereNotNull('coupon_id')
                ->whereHas('coupon', function ($query) {
                    $query->where('expires_at', '<', now())
                          ->orWhere('status', false);
                })
                ->get();

            $removedFromCarts = 0;
            foreach ($expiredCartCoupons as $cart) {
                // Recalcular totales sin cupón
                $subtotal = $cart->items->sum(function ($item) {
                    return $item->price * $item->quantity;
                });

                $cart->update([
                    'coupon_id' => null,
                    'coupon_code' => null,
                    'coupon_discount' => 0,
                    'subtotal' => $subtotal,
                    'total' => $subtotal
                ]);

                $removedFromCarts++;
            }

            // Marcar cupones como inactivos si están expirados
            $expiredCoupons = Coupon::where('status', true)
                ->where('expires_at', '<', now())
                ->update(['status' => false]);

            Log::info('Limpieza de cupones completada', [
                'removed_from_carts' => $removedFromCarts,
                'deactivated_coupons' => $expiredCoupons
            ]);

        } catch (\Exception $e) {
            Log::error('Error en limpieza de cupones expirados', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falló el job de limpieza de cupones', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}