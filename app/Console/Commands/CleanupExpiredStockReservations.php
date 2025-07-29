<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\StockService;

class CleanupExpiredStockReservations extends Command
{
    protected $signature = 'stock:cleanup-reservations';
    protected $description = 'Cleanup expired stock reservations';

    public function __construct(
        protected StockService $stockService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Iniciando limpieza de reservas expiradas...');
        
        $releasedCount = $this->stockService->releaseExpiredReservations();
        
        $this->info("Liberadas {$releasedCount} reservas expiradas.");
        
        return Command::SUCCESS;
    }
}