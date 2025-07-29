<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;

class CleanupExpiredStockReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(StockService $stockService): void
    {
        try {
            Log::info('Iniciando limpieza automática de reservas expiradas');
            
            $releasedCount = $stockService->releaseExpiredReservations();
            
            Log::info("Limpieza completada: {$releasedCount} reservas liberadas");
            
            // Opcional: enviar notificación a administradores si hay muchas reservas expiradas
            if ($releasedCount > 10) {
                Log::warning("Alto número de reservas expiradas liberadas: {$releasedCount}");
                // Aquí podrías enviar una notificación al equipo de administración
            }
            
        } catch (\Exception $e) {
            Log::error('Error en limpieza de reservas expiradas', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falló el job de limpieza de reservas expiradas', [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}