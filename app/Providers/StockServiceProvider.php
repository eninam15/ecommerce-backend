<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Interfaces\StockRepositoryInterface;
use App\Repositories\Eloquent\StockRepository;

class StockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registrar repositorios
        $this->app->bind(
            StockRepositoryInterface::class,
            StockRepository::class
        );
    }

    public function boot(): void
    {
        // Registrar eventos de modelo para auditoría automática
        \App\Models\Product::observe(\App\Observers\ProductStockObserver::class);
    }
}