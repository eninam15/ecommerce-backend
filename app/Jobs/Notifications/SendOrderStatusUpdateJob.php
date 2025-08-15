<?php

namespace App\Jobs\Notifications;

use App\Models\Order;
use App\Enums\OrderStatus;
use App\Mail\Orders\OrderStatusUpdateMail;
use App\Mail\Orders\OrderShippedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendOrderStatusUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(
        public Order $order,
        public OrderStatus $newStatus,
        public ?string $comment = null,
        public ?string $trackingNumber = null,
        public ?string $carrier = null
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if (!$this->order->user) {
                Log::error('Orden sin usuario para actualización de estado', [
                    'order_id' => $this->order->id
                ]);
                return;
            }

            if (!$this->shouldSendEmail()) {
                Log::info('Email de estado no enviado - configuración de desarrollo', [
                    'order_id' => $this->order->id,
                    'new_status' => $this->newStatus->value,
                    'user_email' => $this->order->user->email
                ]);
                return;
            }

            // Elegir el template correcto basado en el estado
            $mailClass = $this->getMailClass();
            
            if ($mailClass) {
                Mail::to($this->order->user->email)->send($mailClass);

                Log::info('Email de actualización de estado enviado', [
                    'order_id' => $this->order->id,
                    'new_status' => $this->newStatus->value,
                    'mail_class' => get_class($mailClass),
                    'user_email' => $this->order->user->email
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error enviando actualización de estado', [
                'order_id' => $this->order->id,
                'new_status' => $this->newStatus->value,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get the appropriate mail class based on status
     */
    private function getMailClass()
    {
        return match($this->newStatus) {
            OrderStatus::SHIPPED => new OrderShippedMail(
                $this->order,
                $this->trackingNumber,
                $this->carrier,
                $this->estimateDeliveryDate()
            ),
            OrderStatus::PAID,
            OrderStatus::PROCESSING,
            OrderStatus::DELIVERED,
            OrderStatus::CANCELLED,
            OrderStatus::REFUNDED => new OrderStatusUpdateMail(
                $this->order,
                $this->newStatus,
                $this->comment
            ),
            default => null
        };
    }

    /**
     * Estimate delivery date based on shipping method
     */
    private function estimateDeliveryDate(): ?\DateTime
    {
        if (!$this->newStatus === OrderStatus::SHIPPED) {
            return null;
        }

        // Lógica simple: 3-5 días hábiles
        $businessDays = rand(3, 5);
        $deliveryDate = now();
        
        for ($i = 0; $i < $businessDays; $i++) {
            $deliveryDate->addDay();
            // Saltar fines de semana
            while ($deliveryDate->isWeekend()) {
                $deliveryDate->addDay();
            }
        }

        return $deliveryDate;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de actualización de estado falló', [
            'order_id' => $this->order->id,
            'new_status' => $this->newStatus->value,
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