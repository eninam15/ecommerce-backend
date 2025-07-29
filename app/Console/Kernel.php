<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\CleanupExpiredStockReservationsJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Limpiar reservas expiradas cada 15 minutos
        $schedule->job(CleanupExpiredStockReservationsJob::class)
                 ->everyFifteenMinutes()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Comando alternativo cada 30 minutos
        $schedule->command('stock:cleanup-reservations')
                 ->everyThirtyMinutes()
                 ->withoutOverlapping();

        // Generar reporte de stock bajo diario (opcional)
        $schedule->command('stock:low-stock-report')
                 ->dailyAt('09:00')
                 ->timezone('America/Mexico_City');

        // Limpiar cupones expirados de carritos cada hora
        $schedule->job(\App\Jobs\CleanupExpiredCouponsJob::class)
                 ->hourly()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Desactivar cupones expirados diariamente
        $schedule->call(function () {
            \App\Models\Coupon::where('status', true)
                ->where('expires_at', '<', now())
                ->update(['status' => false]);
        })->dailyAt('02:00');

        // ===== AUTOMATIZACIÓN DE CUPONES =====
        
        // Procesar notificaciones programadas cada 15 minutos
        $schedule->job(\App\Jobs\ProcessScheduledNotificationsJob::class)
                 ->everyFifteenMinutes()
                 ->withoutOverlapping();

        // Generar cupones para carritos abandonados cada 2 horas
        $schedule->job(\App\Jobs\GenerateAbandonedCartCouponsJob::class)
                 ->everyTwoHours()
                 ->withoutOverlapping();

        // Generar cupones de fidelidad semanalmente
        $schedule->job(\App\Jobs\GenerateLoyaltyCouponsJob::class)
                 ->weekly()
                 ->sundays()
                 ->at('10:00');

        // Verificar cupones estacionales diariamente
        $schedule->job(\App\Jobs\GenerateSeasonalCouponsJob::class)
                 ->dailyAt('08:00');

        // Notificar cupones que expiran pronto (diariamente)
        $schedule->job(\App\Jobs\SendExpiringCouponsNotificationJob::class)
                 ->dailyAt('09:00');

        // Generar cupones basados en comportamiento (dos veces por semana)
        $schedule->job(\App\Jobs\GenerateBehaviorCouponsJob::class)
                 ->twiceWeekly(2, 5) // Martes y viernes
                 ->at('14:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}