<?php

namespace App\Jobs\Notifications;

use App\Models\Order;
use App\Mail\Orders\OrderConfirmationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public Order $order
    ) {
        // Configurar queue específico para notificaciones
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verificar que la orden tenga usuario
            if (!$this->order->user) {
                Log::error('Orden sin usuario para envío de confirmación', [
                    'order_id' => $this->order->id
                ]);
                return;
            }

            // Verificar configuración de mail
            if (!$this->shouldSendEmail()) {
                Log::info('Email no enviado - configuración de desarrollo', [
                    'order_id' => $this->order->id,
                    'user_email' => $this->order->user->email,
                    'environment' => app()->environment()
                ]);
                return;
            }

            // Enviar email
            Mail::to($this->order->user->email)
                ->send(new OrderConfirmationMail($this->order));

            Log::info('Email de confirmación de orden enviado', [
                'order_id' => $this->order->id,
                'user_email' => $this->order->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando confirmación de orden', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-lanzar excepción para activar reintentos
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de confirmación de orden falló definitivamente', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Aquí podrías enviar una notificación a los administradores
        // o guardar en una tabla de notificaciones fallidas
    }

    /**
     * Determinar si se debe enviar email basado en configuración
     */
    private function shouldSendEmail(): bool
    {
        // En desarrollo, solo enviar si está explícitamente habilitado
        if (app()->environment('local', 'development')) {
            return config('mail.send_in_development', false);
        }

        // En testing, nunca enviar emails reales
        if (app()->environment('testing')) {
            return false;
        }

        // En producción, siempre enviar (a menos que esté deshabilitado)
        return config('mail.enabled', true);
    }
}