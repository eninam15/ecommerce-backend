<?php

namespace App\Services;

use App\Models\User;
use App\Models\Coupon;
use App\Models\Cart;
use App\Models\Order;
use App\Models\CouponNotification;
use App\Models\NotificationPreference;
use App\Services\CouponService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Enviar cupón de bienvenida a nuevo usuario
     */
    public function sendWelcomeCoupon(User $user): void
    {
        if (!$this->userHasPreference($user->id, 'email', 'coupon_notifications')) {
            return;
        }

        // Buscar cupón de bienvenida activo
        $welcomeCoupon = Coupon::where('type', 'first_purchase')
            ->where('status', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$welcomeCoupon) {
            Log::info('No hay cupón de bienvenida disponible para nuevo usuario', [
                'user_id' => $user->id
            ]);
            return;
        }

        $this->scheduleNotification([
            'user_id' => $user->id,
            'coupon_id' => $welcomeCoupon->id,
            'type' => 'welcome',
            'channel' => 'email',
            'scheduled_at' => now()->addMinutes(5), // 5 minutos después del registro
            'data' => [
                'user_name' => $user->name,
                'coupon_code' => $welcomeCoupon->code,
                'discount_value' => $welcomeCoupon->discount_value,
                'expires_at' => $welcomeCoupon->expires_at
            ]
        ]);
    }

    /**
     * Enviar notificación de carrito abandonado con cupón
     */
    public function sendAbandonedCartCoupon(string $userId): void
    {
        $user = User::find($userId);
        $cart = Cart::where('user_id', $userId)->with('items')->first();

        if (!$user || !$cart || $cart->items->count() === 0) {
            return;
        }

        if (!$this->userHasPreference($userId, 'email', 'abandoned_cart')) {
            return;
        }

        // Crear cupón especial para carrito abandonado (si no existe)
        $abandonedCartCoupon = $this->getOrCreateAbandonedCartCoupon();

        $this->scheduleNotification([
            'user_id' => $userId,
            'coupon_id' => $abandonedCartCoupon->id,
            'type' => 'abandoned_cart',
            'channel' => 'email',
            'scheduled_at' => now()->addHours(2), // 2 horas después del abandono
            'data' => [
                'user_name' => $user->name,
                'cart_items_count' => $cart->items->count(),
                'cart_total' => $cart->total,
                'coupon_code' => $abandonedCartCoupon->code,
                'discount_value' => $abandonedCartCoupon->discount_value,
                'cart_url' => config('app.frontend_url') . '/cart'
            ]
        ]);
    }

    /**
     * Notificar cupones que expiran pronto
     */
    public function sendExpiringCouponsNotification(): int
    {
        $expiringCoupons = Coupon::where('status', true)
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(3))
            ->get();

        $notificationCount = 0;

        foreach ($expiringCoupons as $coupon) {
            // Obtener usuarios que tienen este cupón disponible
            $eligibleUsers = $this->getEligibleUsersForCoupon($coupon);

            foreach ($eligibleUsers as $user) {
                if (!$this->userHasPreference($user->id, 'email', 'coupon_notifications')) {
                    continue;
                }

                // Verificar que no hayamos enviado esta notificación recientemente
                $recentNotification = CouponNotification::where('user_id', $user->id)
                    ->where('coupon_id', $coupon->id)
                    ->where('type', 'expiring_soon')
                    ->where('created_at', '>', now()->subDays(1))
                    ->exists();

                if ($recentNotification) {
                    continue;
                }

                $this->scheduleNotification([
                    'user_id' => $user->id,
                    'coupon_id' => $coupon->id,
                    'type' => 'expiring_soon',
                    'channel' => 'email',
                    'scheduled_at' => now(),
                    'data' => [
                        'user_name' => $user->name,
                        'coupon_code' => $coupon->code,
                        'coupon_name' => $coupon->name,
                        'discount_value' => $coupon->discount_value,
                        'expires_at' => $coupon->expires_at,
                        'hours_remaining' => now()->diffInHours($coupon->expires_at)
                    ]
                ]);

                $notificationCount++;
            }
        }

        return $notificationCount;
    }

    /**
     * Enviar cupón de cumpleaños
     */
    public function sendBirthdayCoupon(User $user): void
    {
        if (!$this->userHasPreference($user->id, 'email', 'birthday_offers')) {
            return;
        }

        // Crear cupón especial de cumpleaños
        $birthdayCoupon = $this->createBirthdayCoupon($user);

        $this->scheduleNotification([
            'user_id' => $user->id,
            'coupon_id' => $birthdayCoupon->id,
            'type' => 'birthday',
            'channel' => 'email',
            'scheduled_at' => now(),
            'data' => [
                'user_name' => $user->name,
                'coupon_code' => $birthdayCoupon->code,
                'discount_value' => $birthdayCoupon->discount_value,
                'expires_at' => $birthdayCoupon->expires_at,
                'birthday_message' => '¡Feliz cumpleaños! Te regalamos este cupón especial.'
            ]
        ]);
    }

    /**
     * Procesar notificaciones programadas
     */
    public function processScheduledNotifications(): int
    {
        $scheduledNotifications = CouponNotification::scheduled()
            ->with(['user', 'coupon'])
            ->limit(100) // Procesar en lotes
            ->get();

        $processedCount = 0;

        foreach ($scheduledNotifications as $notification) {
            try {
                $this->sendNotification($notification);
                $processedCount++;
            } catch (\Exception $e) {
                $notification->markAsFailed($e->getMessage());
                Log::error('Error enviando notificación de cupón', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processedCount;
    }

    /**
     * Enviar notificación específica
     */
    protected function sendNotification(CouponNotification $notification): void
    {
        switch ($notification->channel) {
            case 'email':
                $this->sendEmailNotification($notification);
                break;
            case 'sms':
                $this->sendSmsNotification($notification);
                break;
            case 'push':
                $this->sendPushNotification($notification);
                break;
            default:
                throw new \Exception("Canal de notificación no soportado: {$notification->channel}");
        }

        $notification->markAsSent();
    }

    /**
     * Enviar notificación por email
     */
    protected function sendEmailNotification(CouponNotification $notification): void
    {
        $template = match($notification->type) {
            'welcome' => 'emails.coupons.welcome',
            'abandoned_cart' => 'emails.coupons.abandoned-cart',
            'expiring_soon' => 'emails.coupons.expiring-soon',
            'birthday' => 'emails.coupons.birthday',
            default => 'emails.coupons.generic'
        };

        Mail::send($template, [
            'user' => $notification->user,
            'coupon' => $notification->coupon,
            'data' => $notification->data,
            'tracking_url' => $this->generateTrackingUrl($notification)
        ], function ($message) use ($notification) {
            $message->to($notification->user->email, $notification->user->name)
                   ->subject($this->getEmailSubject($notification));
        });
    }

    /**
     * Programar una notificación
     */
    protected function scheduleNotification(array $data): CouponNotification
    {
        return CouponNotification::create(array_merge($data, [
            'status' => 'pending'
        ]));
    }

    /**
     * Verificar preferencias de usuario
     */
    protected function userHasPreference(string $userId, string $channel, string $type): bool
    {
        $preference = NotificationPreference::where('user_id', $userId)
            ->where('channel', $channel)
            ->where('type', $type)
            ->first();

        // Si no existe preferencia, asumir que está habilitada
        return $preference ? $preference->enabled : true;
    }

    /**
     * Obtener o crear cupón para carrito abandonado
     */
    protected function getOrCreateAbandonedCartCoupon(): Coupon
    {
        $existingCoupon = Coupon::where('type', 'percentage')
            ->where('discount_value', 10)
            ->where('status', true)
            ->where('expires_at', '>', now())
            ->where('description', 'like', '%carrito%')
            ->first();

        if ($existingCoupon) {
            return $existingCoupon;
        }

        return Coupon::create([
            'code' => 'COMEBACK10',
            'name' => 'Vuelve y Ahorra',
            'description' => '10% de descuento para recuperar tu carrito abandonado',
            'type' => 'percentage',
            'discount_value' => 10.00,
            'minimum_amount' => 25.00,
            'maximum_discount' => 50.00,
            'usage_limit' => null,
            'usage_limit_per_user' => 1,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'created_by' => auth()->id()
        ]);
    }

    /**
     * Crear cupón personalizado de cumpleaños
     */
    protected function createBirthdayCoupon(User $user): Coupon
    {
        $code = 'BIRTHDAY' . strtoupper(substr($user->name, 0, 3)) . now()->format('dm');

        return Coupon::create([
            'code' => $code,
            'name' => "Cupón de Cumpleaños - {$user->name}",
            'description' => 'Cupón especial de cumpleaños válido por 7 días',
            'type' => 'percentage',
            'discount_value' => 15.00,
            'minimum_amount' => 50.00,
            'maximum_discount' => 100.00,
            'usage_limit' => 1,
            'usage_limit_per_user' => 1,
            'first_purchase_only' => false,
            'status' => true,
            'starts_at' => now(),
            'expires_at' => now()->addDays(7),
            'created_by' => auth()->id()
        ]);
    }

    /**
     * Obtener usuarios elegibles para un cupón
     */
    protected function getEligibleUsersForCoupon(Coupon $coupon): \Illuminate\Support\Collection
    {
        $query = User::where('email_verified_at', '!=', null);

        // Si es solo para primera compra, filtrar usuarios sin órdenes
        if ($coupon->first_purchase_only) {
            $query->whereDoesntHave('orders', function ($q) {
                $q->whereIn('status', ['paid', 'delivered', 'completed']);
            });
        }

        // Si tiene límite por usuario, excluir usuarios que ya lo usaron al límite
        if ($coupon->usage_limit_per_user) {
            $query->whereDoesntHave('couponUsages', function ($q) use ($coupon) {
                $q->where('coupon_id', $coupon->id)
                  ->havingRaw('COUNT(*) >= ?', [$coupon->usage_limit_per_user]);
            });
        }

        return $query->limit(1000)->get(); // Limitar para evitar memoria
    }

    /**
     * Generar URL de tracking
     */
    protected function generateTrackingUrl(CouponNotification $notification): string
    {
        return config('app.frontend_url') . "/coupons/track/{$notification->id}";
    }

    /**
     * Obtener subject del email
     */
    protected function getEmailSubject(CouponNotification $notification): string
    {
        return match($notification->type) {
            'welcome' => '¡Bienvenido! Tu cupón de descuento te espera',
            'abandoned_cart' => '¿Olvidaste algo? Te damos un descuento extra',
            'expiring_soon' => '⏰ Tu cupón expira pronto - úsalo ahora',
            'birthday' => '🎉 ¡Feliz cumpleaños! Aquí tienes tu regalo',
            default => 'Tienes un cupón especial esperándote'
        };
    }

    // ===== MÉTODOS PARA SMS Y PUSH (placeholder) =====

    protected function sendSmsNotification(CouponNotification $notification): void
    {
        // Implementar con servicio SMS (Twilio, etc.)
        Log::info('SMS notification sent', ['notification_id' => $notification->id]);
    }

    protected function sendPushNotification(CouponNotification $notification): void
    {
        // Implementar con servicio Push (Firebase, etc.)
        Log::info('Push notification sent', ['notification_id' => $notification->id]);
    }
}