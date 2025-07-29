<?php

namespace App\Observers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Events\CartAbandoned;
use App\Events\OrderCompleted;
use App\Events\UserRegistered;
use Illuminate\Support\Facades\Log;

class CartObserver
{
    /**
     * Handle the Cart "updated" event.
     */
    public function updated(Cart $cart): void
    {
        // Detectar posible abandono de carrito
        // Solo si tiene items y no se ha actualizado en las últimas 2 horas
        if ($cart->items()->count() > 0 && 
            $cart->updated_at->diffInHours(now()) >= 2) {
            
            // Verificar que no hayamos enviado notificación recientemente
            $recentNotification = \App\Models\CouponNotification::where('user_id', $cart->user_id)
                ->where('type', 'abandoned_cart')
                ->where('created_at', '>', now()->subDays(1))
                ->exists();
                
            if (!$recentNotification) {
                event(new CartAbandoned(
                    $cart->user,
                    $cart,
                    $cart->updated_at
                ));
            }
        }
    }
}

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Detectar cuando una orden se completa
        if ($order->isDirty('status') && 
            in_array($order->status, ['delivered', 'completed'])) {
            
            event(new OrderCompleted($order->user, $order));
        }
    }
}

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Disparar evento de usuario registrado para cupón de bienvenida
        event(new UserRegistered($user));
        
        Log::info('Nuevo usuario registrado', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    }
}