<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Cart;
use App\Models\Order;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user
    ) {}
}

class CouponApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Coupon $coupon,
        public Cart $cart,
        public float $discountAmount
    ) {}
}

class CouponUsed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Coupon $coupon,
        public Order $order,
        public float $discountAmount
    ) {}
}

class CartAbandoned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Cart $cart,
        public \DateTime $abandonedAt
    ) {}
}

class OrderCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public Order $order
    ) {}
}

class CouponExpiringSoon
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Coupon $coupon,
        public int $hoursUntilExpiry
    ) {}
}

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Events\CouponApplied;
use App\Events\CouponUsed;
use App\Events\CartAbandoned;
use App\Events\OrderCompleted;
use App\Events\CouponExpiringSoon;
use App\Services\AutoCouponService;
use App\Services\NotificationService;
use App\Services\CouponAnalyticsService;
use Illuminate\Support\Facades\Log;

class SendWelcomeCouponListener
{
    public function __construct(
        protected AutoCouponService $autoCouponService,
        protected NotificationService $notificationService
    ) {}

    public function handle(UserRegistered $event): void
    {
        // Generar cupón de bienvenida automáticamente
        $coupon = $this->autoCouponService->generateWelcomeCoupon($event->user);
        
        if ($coupon) {
            // Programar notificación de bienvenida
            $this->notificationService->sendWelcomeCoupon($event->user);
            
            Log::info('Cupón de bienvenida automático generado', [
                'user_id' => $event->user->id,
                'coupon_code' => $coupon->code
            ]);
        }
    }
}

class TrackCouponUsageListener
{
    public function __construct(
        protected CouponAnalyticsService $analyticsService
    ) {}

    public function handle(CouponApplied $event): void
    {
        // Registrar aplicación de cupón para analytics
        Log::info('Cupón aplicado al carrito', [
            'user_id' => $event->user->id,
            'coupon_id' => $event->coupon->id,
            'coupon_code' => $event->coupon->code,
            'cart_id' => $event->cart->id,
            'discount_amount' => $event->discountAmount
        ]);
        
        // Aquí podrías enviar datos a herramientas de analytics externas
        // Como Google Analytics, Mixpanel, etc.
    }

    public function handleCouponUsed(CouponUsed $event): void
    {
        Log::info('Cupón usado en orden completada', [
            'user_id' => $event->user->id,
            'coupon_id' => $event->coupon->id,
            'order_id' => $event->order->id,
            'discount_amount' => $event->discountAmount
        ]);
    }
}

class HandleAbandonedCartListener
{
    public function __construct(
        protected AutoCouponService $autoCouponService,
        protected NotificationService $notificationService
    ) {}

    public function handle(CartAbandoned $event): void
    {
        // Programar notificación de carrito abandonado para 2 horas después
        \App\Jobs\GenerateAbandonedCartCouponsJob::dispatch()->delay(now()->addHours(2));
        
        Log::info('Carrito abandonado detectado', [
            'user_id' => $event->user->id,
            'cart_id' => $event->cart->id,
            'abandoned_at' => $event->abandonedAt
        ]);
    }
}

class CheckLoyaltyEligibilityListener
{
    public function __construct(
        protected AutoCouponService $autoCouponService
    ) {}

    public function handle(OrderCompleted $event): void
    {
        // Verificar si el usuario es elegible para cupón de fidelidad
        $orderCount = $event->user->orders()
            ->whereIn('status', ['delivered', 'completed'])
            ->count();
            
        // Cada 5 órdenes, generar cupón de fidelidad
        if ($orderCount % 5 === 0) {
            \App\Jobs\GenerateLoyaltyCouponsJob::dispatch()->delay(now()->addHours(1));
            
            Log::info('Usuario elegible para cupón de fidelidad', [
                'user_id' => $event->user->id,
                'order_count' => $orderCount
            ]);
        }
    }
}

class NotifyExpiringCouponListener
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function handle(CouponExpiringSoon $event): void
    {
        // Disparar notificaciones de cupones próximos a expirar
        \App\Jobs\SendExpiringCouponsNotificationJob::dispatch();
        
        Log::info('Cupón próximo a expirar detectado', [
            'coupon_id' => $event->coupon->id,
            'coupon_code' => $event->coupon->code,
            'hours_until_expiry' => $event->hoursUntilExpiry
        ]);
    }
}