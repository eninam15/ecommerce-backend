<?php

namespace App\Services;

use App\Models\User;
use App\Models\Coupon;
use App\Models\Cart;
use App\Models\Order;
use App\Services\CouponService;
use App\Services\NotificationService;
use App\Enums\CouponType;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AutoCouponService
{
    public function __construct(
        protected CouponService $couponService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Generar cupones automáticos para nuevos usuarios
     */
    public function generateWelcomeCoupon(User $user): ?Coupon
    {
        // Verificar si ya tiene cupón de bienvenida
        $existingWelcome = Coupon::where('first_purchase_only', true)
            ->where('status', true)
            ->whereHas('usages', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->exists();

        if ($existingWelcome) {
            return null;
        }

        // Crear cupón personalizado de bienvenida
        $welcomeCode = 'WELCOME' . strtoupper(substr($user->name, 0, 3)) . now()->format('md');

        $couponData = new \App\Dtos\CouponData(
            code: $welcomeCode,
            name: "Bienvenida {$user->name}",
            description: "Cupón de bienvenida personalizado para {$user->name}",
            type: CouponType::PERCENTAGE,
            discountValue: 15.0,
            minimumAmount: 50.0,
            maximumDiscount: 75.0,
            usageLimit: 1,
            usageLimitPerUser: 1,
            firstPurchaseOnly: true,
            status: true,
            startsAt: now(),
            expiresAt: now()->addDays(30)
        );

        $coupon = $this->couponService->createCoupon($couponData);

        // Programar notificación
        $this->notificationService->sendWelcomeCoupon($user);

        Log::info('Cupón de bienvenida automático generado', [
            'user_id' => $user->id,
            'coupon_code' => $coupon->code
        ]);

        return $coupon;
    }

    /**
     * Generar cupones para recuperar carritos abandonados
     */
    public function generateAbandonedCartCoupons(): int
    {
        // Carritos abandonados hace más de 2 horas y menos de 7 días
        $abandonedCarts = Cart::whereHas('items')
            ->where('updated_at', '<=', now()->subHours(2))
            ->where('updated_at', '>=', now()->subDays(7))
            ->whereNull('coupon_id') // Que no tengan cupón aplicado
            ->with(['user', 'items'])
            ->get();

        $generatedCount = 0;

        foreach ($abandonedCarts as $cart) {
            // Verificar que el usuario no haya recibido cupón de carrito abandonado recientemente
            $recentAbandonedCoupon = \App\Models\CouponNotification::where('user_id', $cart->user_id)
                ->where('type', 'abandoned_cart')
                ->where('created_at', '>', now()->subDays(3))
                ->exists();

            if ($recentAbandonedCoupon) {
                continue;
            }

            // Crear cupón escalado según el valor del carrito
            $cartTotal = $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });

            $coupon = $this->createAbandonedCartCoupon($cart->user, $cartTotal);

            if ($coupon) {
                // Aplicar automáticamente al carrito
                $this->couponService->applyCouponToCart(new \App\Dtos\ApplyCouponData(
                    couponCode: $coupon->code,
                    userId: $cart->user_id,
                    cartId: $cart->id
                ));

                // Enviar notificación
                $this->notificationService->sendAbandonedCartCoupon($cart->user_id);

                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    /**
     * Generar cupones de fidelidad para clientes frecuentes
     */
    public function generateLoyaltyCoupons(): int
    {
        // Usuarios con 5+ órdenes completadas en los últimos 6 meses
        $loyalCustomers = User::whereHas('orders', function ($query) {
                $query->whereIn('status', ['delivered', 'completed'])
                      ->where('created_at', '>=', now()->subMonths(6));
            }, '>=', 5)
            ->whereDoesntHave('couponUsages', function ($query) {
                $query->whereHas('coupon', function ($q) {
                    $q->where('description', 'like', '%fidelidad%')
                      ->where('created_at', '>', now()->subMonths(1));
                });
            })
            ->get();

        $generatedCount = 0;

        foreach ($loyalCustomers as $customer) {
            $totalSpent = $customer->orders()
                ->whereIn('status', ['delivered', 'completed'])
                ->where('created_at', '>=', now()->subMonths(6))
                ->sum('total');

            $loyaltyCoupon = $this->createLoyaltyCoupon($customer, $totalSpent);

            if ($loyaltyCoupon) {
                // Enviar notificación personalizada
                $this->scheduleCustomNotification($customer, $loyaltyCoupon, 'loyalty_reward');
                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    /**
     * Generar cupones estacionales automáticos
     */
    public function generateSeasonalCoupons(): int
    {
        $today = now();
        $generatedCount = 0;

        // Detectar eventos estacionales
        $seasonalEvents = $this->getSeasonalEvents($today);

        foreach ($seasonalEvents as $event) {
            $existingCoupon = Coupon::where('name', 'like', "%{$event['name']}%")
                ->where('created_at', '>=', $today->subDays(30))
                ->first();

            if (!$existingCoupon) {
                $seasonalCoupon = $this->createSeasonalCoupon($event);
                if ($seasonalCoupon) {
                    $generatedCount++;
                    
                    // Notificar a usuarios elegibles
                    $this->notifySeasonalCoupon($seasonalCoupon);
                }
            }
        }

        return $generatedCount;
    }

    /**
     * Generar cupones basados en comportamiento
     */
    public function generateBehaviorCoupons(): int
    {
        $generatedCount = 0;

        // 1. Usuarios que ven productos pero no compran (retargeting)
        $windowShoppers = $this->getWindowShoppers();
        foreach ($windowShoppers as $user) {
            $retargetingCoupon = $this->createRetargetingCoupon($user);
            if ($retargetingCoupon) {
                $this->scheduleCustomNotification($user, $retargetingCoupon, 'retargeting');
                $generatedCount++;
            }
        }

        // 2. Usuarios con alta actividad (VIP upgrade)
        $vipEligible = $this->getVipEligibleUsers();
        foreach ($vipEligible as $user) {
            $vipCoupon = $this->createVipCoupon($user);
            if ($vipCoupon) {
                $this->scheduleCustomNotification($user, $vipCoupon, 'vip_upgrade');
                $generatedCount++;
            }
        }

        return $generatedCount;
    }

    /**
     * Crear cupón para carrito abandonado
     */
    protected function createAbandonedCartCoupon(User $user, float $cartTotal): ?Coupon
    {
        // Escalar descuento según valor del carrito
        $discountValue = match(true) {
            $cartTotal >= 500 => 15.0, // 15% para carritos altos
            $cartTotal >= 200 => 12.0, // 12% para carritos medios
            $cartTotal >= 100 => 10.0, // 10% para carritos bajos
            default => 8.0 // 8% para carritos muy bajos
        };

        $code = 'COMEBACK' . strtoupper(substr($user->name, 0, 2)) . now()->format('Hi');

        $couponData = new \App\Dtos\CouponData(
            code: $code,
            name: "Regresa y Ahorra - {$user->name}",
            description: "Cupón especial para recuperar tu carrito abandonado",
            type: CouponType::PERCENTAGE,
            discountValue: $discountValue,
            minimumAmount: $cartTotal * 0.8, // 80% del valor actual
            maximumDiscount: min($cartTotal * 0.2, 100), // Máximo 20% del carrito o $100
            usageLimit: 1,
            usageLimitPerUser: 1,
            firstPurchaseOnly: false,
            status: true,
            startsAt: now(),
            expiresAt: now()->addDays(3) // Urgencia de 3 días
        );

        return $this->couponService->createCoupon($couponData);
    }

    /**
     * Crear cupón de fidelidad
     */
    protected function createLoyaltyCoupon(User $user, float $totalSpent): ?Coupon
    {
        $discountValue = match(true) {
            $totalSpent >= 2000 => 25.0, // 25% para gastadores premium
            $totalSpent >= 1000 => 20.0, // 20% para gastadores altos
            $totalSpent >= 500 => 15.0,  // 15% para gastadores medios
            default => 10.0 // 10% base
        };

        $code = 'LOYAL' . strtoupper(substr($user->name, 0, 3)) . now()->format('Ym');

        $couponData = new \App\Dtos\CouponData(
            code: $code,
            name: "Recompensa de Fidelidad - {$user->name}",
            description: "Gracias por tu lealtad. Cupón especial de cliente frecuente.",
            type: CouponType::PERCENTAGE,
            discountValue: $discountValue,
            minimumAmount: 75.0,
            maximumDiscount: $totalSpent >= 2000 ? 500.0 : 200.0,
            usageLimit: 1,
            usageLimitPerUser: 1,
            firstPurchaseOnly: false,
            status: true,
            startsAt: now(),
            expiresAt: now()->addDays(30)
        );

        return $this->couponService->createCoupon($couponData);
    }

    /**
     * Crear cupón estacional
     */
    protected function createSeasonalCoupon(array $event): ?Coupon
    {
        $code = strtoupper($event['code']) . now()->format('Y');

        $couponData = new \App\Dtos\CouponData(
            code: $code,
            name: $event['name'],
            description: $event['description'],
            type: CouponType::PERCENTAGE,
            discountValue: $event['discount'],
            minimumAmount: $event['minimum_amount'],
            maximumDiscount: $event['maximum_discount'],
            usageLimit: $event['usage_limit'],
            usageLimitPerUser: $event['usage_limit_per_user'],
            firstPurchaseOnly: false,
            status: true,
            startsAt: $event['starts_at'],
            expiresAt: $event['expires_at']
        );

        return $this->couponService->createCoupon($couponData);
    }

    /**
     * Obtener eventos estacionales
     */
    protected function getSeasonalEvents(Carbon $date): array
    {
        $events = [];
        $month = $date->month;
        $day = $date->day;

        // San Valentín (14 de febrero)
        if ($month === 2 && $day >= 10 && $day <= 14) {
            $events[] = [
                'name' => 'Especial San Valentín',
                'code' => 'VALENTINE',
                'description' => 'Amor con descuento especial para San Valentín',
                'discount' => 20.0,
                'minimum_amount' => 100.0,
                'maximum_discount' => 150.0,
                'usage_limit' => 500,
                'usage_limit_per_user' => 1,
                'starts_at' => Carbon::create($date->year, 2, 10),
                'expires_at' => Carbon::create($date->year, 2, 15)
            ];
        }

        // Día de la Madre (primer domingo de mayo)
        if ($month === 5 && $day >= 1 && $day <= 7) {
            $events[] = [
                'name' => 'Día de la Madre',
                'code' => 'MAMA',
                'description' => 'Descuento especial para mamá',
                'discount' => 25.0,
                'minimum_amount' => 75.0,
                'maximum_discount' => 200.0,
                'usage_limit' => 300,
                'usage_limit_per_user' => 1,
                'starts_at' => Carbon::create($date->year, 5, 1),
                'expires_at' => Carbon::create($date->year, 5, 10)
            ];
        }

        // Black Friday (cuarto viernes de noviembre)
        if ($month === 11 && $day >= 20 && $day <= 30) {
            $events[] = [
                'name' => 'Black Friday Mega Sale',
                'code' => 'BLACKFRIDAY',
                'description' => 'El descuento más grande del año',
                'discount' => 40.0,
                'minimum_amount' => 150.0,
                'maximum_discount' => 500.0,
                'usage_limit' => 1000,
                'usage_limit_per_user' => 2,
                'starts_at' => Carbon::create($date->year, 11, 24),
                'expires_at' => Carbon::create($date->year, 11, 30)
            ];
        }

        // Navidad (diciembre)
        if ($month === 12 && $day >= 15) {
            $events[] = [
                'name' => 'Especial Navideño',
                'code' => 'NAVIDAD',
                'description' => 'Descuento navideño para tus regalos',
                'discount' => 30.0,
                'minimum_amount' => 120.0,
                'maximum_discount' => 300.0,
                'usage_limit' => 800,
                'usage_limit_per_user' => 2,
                'starts_at' => Carbon::create($date->year, 12, 15),
                'expires_at' => Carbon::create($date->year, 12, 31)
            ];
        }

        return $events;
    }

    /**
     * Obtener usuarios "window shoppers"
     */
    protected function getWindowShoppers(): \Illuminate\Support\Collection
    {
        // Usuarios que han visitado productos pero no han comprado en 7 días
        return User::whereHas('viewedProducts') // Esto requeriría un sistema de tracking
            ->whereDoesntHave('orders', function ($query) {
                $query->where('created_at', '>', now()->subDays(7));
            })
            ->limit(50)
            ->get();
    }

    /**
     * Obtener usuarios elegibles para VIP
     */
    protected function getVipEligibleUsers(): \Illuminate\Support\Collection
    {
        return User::whereHas('orders', function ($query) {
                $query->whereIn('status', ['delivered', 'completed'])
                      ->where('created_at', '>=', now()->subMonths(3));
            }, '>=', 3)
            ->whereHas('orders', function ($query) {
                $query->whereIn('status', ['delivered', 'completed'])
                      ->where('created_at', '>=', now()->subMonths(3))
                      ->havingRaw('SUM(total) >= ?', [1000]);
            })
            ->limit(20)
            ->get();
    }

    /**
     * Crear cupón de retargeting
     */
    protected function createRetargetingCoupon(User $user): ?Coupon
    {
        $code = 'RETURN' . strtoupper(substr($user->name, 0, 3)) . now()->format('dm');

        $couponData = new \App\Dtos\CouponData(
            code: $code,
            name: "Te Extrañamos - {$user->name}",
            description: "Cupón especial porque te extrañamos",
            type: CouponType::PERCENTAGE,
            discountValue: 12.0,
            minimumAmount: 40.0,
            maximumDiscount: 60.0,
            usageLimit: 1,
            usageLimitPerUser: 1,
            firstPurchaseOnly: false,
            status: true,
            startsAt: now(),
            expiresAt: now()->addDays(7)
        );

        return $this->couponService->createCoupon($couponData);
    }

    /**
     * Crear cupón VIP
     */
    protected function createVipCoupon(User $user): ?Coupon
    {
        $code = 'VIP' . strtoupper(substr($user->name, 0, 3)) . now()->format('m');

        $couponData = new \App\Dtos\CouponData(
            code: $code,
            name: "Acceso VIP - {$user->name}",
            description: "Cupón VIP exclusivo por tu lealtad",
            type: CouponType::PERCENTAGE,
            discountValue: 30.0,
            minimumAmount: 200.0,
            maximumDiscount: 400.0,
            usageLimit: 1,
            usageLimitPerUser: 1,
            firstPurchaseOnly: false,
            status: true,
            startsAt: now(),
            expiresAt: now()->addDays(60)
        );

        return $this->couponService->createCoupon($couponData);
    }

    /**
     * Programar notificación personalizada
     */
    protected function scheduleCustomNotification(User $user, Coupon $coupon, string $type): void
    {
        \App\Models\CouponNotification::create([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'type' => $type,
            'channel' => 'email',
            'status' => 'pending',
            'scheduled_at' => now()->addMinutes(30),
            'data' => [
                'user_name' => $user->name,
                'coupon_code' => $coupon->code,
                'discount_value' => $coupon->discount_value,
                'expires_at' => $coupon->expires_at
            ]
        ]);
    }

    /**
     * Notificar cupón estacional a usuarios elegibles
     */
    protected function notifySeasonalCoupon(Coupon $coupon): void
    {
        $eligibleUsers = User::where('email_verified_at', '!=', null)
            ->limit(1000)
            ->get();

        foreach ($eligibleUsers as $user) {
            $this->scheduleCustomNotification($user, $coupon, 'seasonal');
        }
    }
}