<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Eloquent\CartRepository;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Interfaces\ShippingAddressRepositoryInterface;
use App\Repositories\Eloquent\ShippingAddressRepository;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use App\Repositories\Eloquent\BannerRepository;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(ShippingAddressRepositoryInterface::class, ShippingAddressRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(BannerRepositoryInterface::class, BannerRepository::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function () {
            return Limit::perMinute(60);
        });
    }
}
