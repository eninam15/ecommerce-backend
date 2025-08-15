<?php

namespace App\Jobs\Notifications;

use App\Models\Payment;
use App\Mail\Payments\PaymentConfirmationMail;
use App\Events\Orders\OrderStatusChanged;
use App\Enums\PaymentStatus;
use App\Enums\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPaymentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(
        public Payment $payment,
        public string $notificationType = 'confirmation' // confirmation, failed, refund
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (!$this->payment->order || !$this->payment->order->user) {
                Log::error('Payment sin orden o usuario válido', [
                    'payment_id' => $this->payment->id
                ]);
                return;
            }

            if (!$this->shouldSendEmail()) {
                Log::info('Email de pago no enviado - configuración de desarrollo', [
                    'payment_id' => $this->payment->id,
                    'type' => $this->notificationType,
                    'user_email' => $this->payment->order->user->email
                ]);
                return;
            }

            // Solo enviar confirmación para pagos completados
            if ($this->notificationType === 'confirmation' && 
                $this->payment->status === PaymentStatus::COMPLETED) {
                
                Mail::to($this->payment->order->user->email)
                    ->send(new PaymentConfirmationMail($this->payment));

                Log::info('Email de confirmación de pago enviado', [
                    'payment_id' => $this->payment->id,
                    'order_id' => $this->payment->order->id,
                    'amount' => $this->payment->amount,
                    'user_email' => $this->payment->order->user->email
                ]);

                // Trigger automático de cambio de estado de orden
                $this->triggerOrderStatusChange();
            }

        } catch (\Exception $e) {
            Log::error('Error enviando notificación de pago', [
                'payment_id' => $this->payment->id,
                'type' => $this->notificationType,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Trigger automatic order status change after payment
     */
    private function triggerOrderStatusChange(): void
    {
        try {
            // Si el pago se completó, cambiar orden a PAID
            if ($this->payment->status === PaymentStatus::COMPLETED && 
                $this->payment->order->status === OrderStatus::PAYMENT_PENDING) {
                
                $previousStatus = $this->payment->order->status;
                
                // Actualizar el estado de la orden
                $this->payment->order->update([
                    'status' => OrderStatus::PAID
                ]);

                // Disparar evento para notificar el cambio de estado
                event(new OrderStatusChanged(
                    $this->payment->order->fresh(),
                    $previousStatus,
                    OrderStatus::PAID,
                    'Pago confirmado automáticamente'
                ));

                Log::info('Estado de orden actualizado automáticamente tras pago', [
                    'order_id' => $this->payment->order->id,
                    'previous_status' => $previousStatus->value,
                    'new_status' => OrderStatus::PAID->value
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error actualizando estado de orden tras pago', [
                'payment_id' => $this->payment->id,
                'order_id' => $this->payment->order->id,
                'error' => $e->getMessage()
            ]);
            // No relanzar la excepción para no fallar el job principal
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de notificación de pago falló', [
            'payment_id' => $this->payment->id,
            'type' => $this->notificationType,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Determinar si se debe enviar email
     */
    private function shouldSendEmail(): bool
    {
        if (app()->environment('local', 'development')) {
            return config('mail.send_in_development', false);
        }

        if (app()->environment('testing')) {
            return false;
        }

        return config('mail.enabled', true);
    }
}