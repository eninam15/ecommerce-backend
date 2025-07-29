<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AutoCouponService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        try {
            $processedCount = $notificationService->processScheduledNotifications();
            
            Log::info("Notificaciones de cupones procesadas", [
                'processed_count' => $processedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error procesando notificaciones programadas', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}

class GenerateAbandonedCartCouponsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutoCouponService $autoCouponService): void
    {
        try {
            $generatedCount = $autoCouponService->generateAbandonedCartCoupons();
            
            Log::info("Cupones de carrito abandonado generados", [
                'generated_count' => $generatedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error generando cupones de carrito abandonado', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}

class GenerateLoyaltyCouponsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutoCouponService $autoCouponService): void
    {
        try {
            $generatedCount = $autoCouponService->generateLoyaltyCoupons();
            
            Log::info("Cupones de fidelidad generados", [
                'generated_count' => $generatedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error generando cupones de fidelidad', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}

class GenerateSeasonalCouponsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutoCouponService $autoCouponService): void
    {
        try {
            $generatedCount = $autoCouponService->generateSeasonalCoupons();
            
            Log::info("Cupones estacionales generados", [
                'generated_count' => $generatedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error generando cupones estacionales', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}

class SendExpiringCouponsNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService): void
    {
        try {
            $notificationCount = $notificationService->sendExpiringCouponsNotification();
            
            Log::info("Notificaciones de cupones expirando enviadas", [
                'notification_count' => $notificationCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error enviando notificaciones de cupones expirando', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}

class GenerateBehaviorCouponsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutoCouponService $autoCouponService): void
    {
        try {
            $generatedCount = $autoCouponService->generateBehaviorCoupons();
            
            Log::info("Cupones basados en comportamiento generados", [
                'generated_count' => $generatedCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error generando cupones de comportamiento', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}