<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        // ===== EVENTOS DE CUPONES =====
        
        \App\Events\UserRegistered::class => [
            \App\Listeners\SendWelcomeCouponListener::class,
        ],
        
        \App\Events\CouponApplied::class => [
            \App\Listeners\TrackCouponUsageListener::class,
        ],
        
        \App\Events\CouponUsed::class => [
            \App\Listeners\TrackCouponUsageListener::class . '@handleCouponUsed',
        ],
        
        \App\Events\CartAbandoned::class => [
            \App\Listeners\HandleAbandonedCartListener::class,
        ],
        
        \App\Events\OrderCompleted::class => [
            \App\Listeners\CheckLoyaltyEligibilityListener::class,
        ],
        
        \App\Events\CouponExpiringSoon::class => [
            \App\Listeners\NotifyExpiringCouponListener::class,
        ],

        // ===== EVENTOS EXISTENTES =====
        
        Illuminate\Auth\Events\Registered::class => [
            Illuminate\Auth\Listeners\SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // Observer para detectar carritos abandonados
        \App\Models\Cart::observe(\App\Observers\CartObserver::class);
        
        // Observer para detectar órdenes completadas
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}