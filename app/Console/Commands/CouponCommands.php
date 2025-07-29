<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AutoCouponService;
use App\Services\NotificationService;
use App\Services\CouponAnalyticsService;

class GenerateAutoCouponsCommand extends Command
{
    protected $signature = 'coupons:generate-auto
                            {type : Type of auto coupons (abandoned-cart, loyalty, seasonal, behavior)}';
    
    protected $description = 'Generate automatic coupons based on user behavior and patterns';

    public function __construct(
        protected AutoCouponService $autoCouponService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $type = $this->argument('type');
        
        $this->info("Generando cupones automáticos tipo: {$type}");
        
        $generatedCount = match($type) {
            'abandoned-cart' => $this->autoCouponService->generateAbandonedCartCoupons(),
            'loyalty' => $this->autoCouponService->generateLoyaltyCoupons(),
            'seasonal' => $this->autoCouponService->generateSeasonalCoupons(),
            'behavior' => $this->autoCouponService->generateBehaviorCoupons(),
            default => throw new \InvalidArgumentException("Tipo no válido: {$type}")
        };
        
        $this->info("✅ Se generaron {$generatedCount} cupones automáticos");
        
        return Command::SUCCESS;
    }
}

class SendCouponNotificationsCommand extends Command
{
    protected $signature = 'coupons:send-notifications
                            {type? : Type of notifications (expiring, scheduled, all)}';
    
    protected $description = 'Send coupon notifications to users';

    public function __construct(
        protected NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $type = $this->argument('type') ?? 'scheduled';
        
        $this->info("Enviando notificaciones de cupones tipo: {$type}");
        
        $sentCount = match($type) {
            'expiring' => $this->notificationService->sendExpiringCouponsNotification(),
            'scheduled' => $this->notificationService->processScheduledNotifications(),
            'all' => $this->sendAllNotifications(),
            default => throw new \InvalidArgumentException("Tipo no válido: {$type}")
        };
        
        $this->info("✅ Se enviaron {$sentCount} notificaciones");
        
        return Command::SUCCESS;
    }

    protected function sendAllNotifications(): int
    {
        $scheduled = $this->notificationService->processScheduledNotifications();
        $expiring = $this->notificationService->sendExpiringCouponsNotification();
        
        return $scheduled + $expiring;
    }
}

class CleanupCouponsCommand extends Command
{
    protected $signature = 'coupons:cleanup
                            {--dry-run : Show what would be cleaned without actually doing it}';
    
    protected $description = 'Cleanup expired coupons and invalid data';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 MODO DRY RUN - Solo mostrando qué se limpiaría...');
        }
        
        // 1. Limpiar cupones expirados de carritos
        $expiredCartCoupons = \App\Models\Cart::whereNotNull('coupon_id')
            ->whereHas('coupon', function ($query) {
                $query->where('expires_at', '<', now())
                      ->orWhere('status', false);
            })
            ->count();
            
        if ($expiredCartCoupons > 0) {
            $this->line("📊 Carritos con cupones expirados: {$expiredCartCoupons}");
            
            if (!$isDryRun) {
                \App\Jobs\CleanupExpiredCouponsJob::dispatch();
                $this->info("✅ Job de limpieza de carritos iniciado");
            }
        }
        
        // 2. Desactivar cupones expirados
        $expiredCoupons = \App\Models\Coupon::where('status', true)
            ->where('expires_at', '<', now())
            ->count();
            
        if ($expiredCoupons > 0) {
            $this->line("📊 Cupones expirados para desactivar: {$expiredCoupons}");
            
            if (!$isDryRun) {
                \App\Models\Coupon::where('status', true)
                    ->where('expires_at', '<', now())
                    ->update(['status' => false]);
                $this->info("✅ Cupones expirados desactivados");
            }
        }
        
        // 3. Limpiar notificaciones fallidas antiguas
        $oldFailedNotifications = \App\Models\CouponNotification::where('status', 'failed')
            ->where('created_at', '<', now()->subDays(30))
            ->count();
            
        if ($oldFailedNotifications > 0) {
            $this->line("📊 Notificaciones fallidas antiguas: {$oldFailedNotifications}");
            
            if (!$isDryRun) {
                \App\Models\CouponNotification::where('status', 'failed')
                    ->where('created_at', '<', now()->subDays(30))
                    ->delete();
                $this->info("✅ Notificaciones fallidas antiguas eliminadas");
            }
        }
        
        // 4. Estadísticas de limpieza
        if (!$isDryRun) {
            $this->newLine();
            $this->info('🎯 Resumen de limpieza completada:');
            $this->line("   - Carritos limpiados: {$expiredCartCoupons}");
            $this->line("   - Cupones desactivados: {$expiredCoupons}");
            $this->line("   - Notificaciones eliminadas: {$oldFailedNotifications}");
        }
        
        return Command::SUCCESS;
    }
}

class CouponAnalyticsCommand extends Command
{
    protected $signature = 'coupons:analytics
                            {period=30d : Period for analytics (7d, 30d, 90d, 1y)}
                            {--export= : Export format (json, csv)}';
    
    protected $description = 'Generate coupon analytics report';

    public function __construct(
        protected CouponAnalyticsService $analyticsService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $period = $this->argument('period');
        $exportFormat = $this->option('export');
        
        $this->info("Generando analytics de cupones para período: {$period}");
        
        $analytics = $this->analyticsService->getDashboardAnalytics($period);
        
        // Mostrar resumen en consola
        $this->displaySummary($analytics);
        
        // Exportar si se especifica formato
        if ($exportFormat) {
            $this->exportAnalytics($analytics, $exportFormat, $period);
        }
        
        return Command::SUCCESS;
    }

    protected function displaySummary(array $analytics): void
    {
        $overview = $analytics['overview'];
        $performance = $analytics['performance'];
        $revenue = $analytics['revenue_impact'];
        
        $this->newLine();
        $this->info('📊 RESUMEN DE ANALYTICS DE CUPONES');
        $this->line('═══════════════════════════════════════');
        
        $this->line("💰 Ingresos con cupones: $" . number_format($revenue['orders_with_coupons']['revenue'], 2));
        $this->line("🎫 Total de usos: " . number_format($overview['usages_in_period']));
        $this->line("💸 Descuentos dados: $" . number_format($overview['total_discount_given'], 2));
        $this->line("📈 ROI de cupones: " . $revenue['coupon_roi_percentage'] . "%");
        $this->line("👥 Tasa de adopción: " . $analytics['user_engagement']['engagement_rate_percentage'] . "%");
        
        $this->newLine();
        $this->info('🏆 RENDIMIENTO POR TIPO:');
        foreach ($performance as $type) {
            $this->line("   {$type['type_label']}: {$type['usage_count']} usos");
        }
        
        $topCoupons = $analytics['top_performers']['top_by_usage'] ?? [];
        if (!empty($topCoupons)) {
            $this->newLine();
            $this->info('🥇 TOP CUPONES POR USO:');
            foreach (array_slice($topCoupons->toArray(), 0, 5) as $coupon) {
                $this->line("   {$coupon['code']}: {$coupon['usage_count']} usos");
            }
        }
    }

    protected function exportAnalytics(array $analytics, string $format, string $period): void
    {
        $filename = "coupon_analytics_{$period}_" . now()->format('Y-m-d_H-i-s');
        
        switch ($format) {
            case 'json':
                $filepath = storage_path("app/exports/{$filename}.json");
                file_put_contents($filepath, json_encode($analytics, JSON_PRETTY_PRINT));
                $this->info("📁 Analytics exportados a: {$filepath}");
                break;
                
            case 'csv':
                $this->exportToCsv($analytics, $filename);
                break;
                
            default:
                $this->error("Formato de exportación no válido: {$format}");
        }
    }

    protected function exportToCsv(array $analytics, string $filename): void
    {
        $filepath = storage_path("app/exports/{$filename}.csv");
        $handle = fopen($filepath, 'w');
        
        // Headers
        fputcsv($handle, ['Métrica', 'Valor']);
        
        // Overview data
        $overview = $analytics['overview'];
        fputcsv($handle, ['Total de Cupones', $overview['total_coupons'] ?? 0]);
        fputcsv($handle, ['Cupones Activos', $overview['active_coupons'] ?? 0]);
        fputcsv($handle, ['Usos en Período', $overview['usages_in_period'] ?? 0]);
        fputcsv($handle, ['Descuentos Dados', $overview['total_discount_given'] ?? 0]);
        
        fclose($handle);
        $this->info("📁 Analytics exportados a CSV: {$filepath}");
    }
}

class SendWelcomeCouponsCommand extends Command
{
    protected $signature = 'coupons:send-welcome
                            {--user-id= : Send to specific user ID}
                            {--recent-users= : Send to users registered in last X days (default: 1)}';
    
    protected $description = 'Send welcome coupons to new users';

    public function __construct(
        protected AutoCouponService $autoCouponService,
        protected NotificationService $notificationService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $userId = $this->option('user-id');
        $recentDays = $this->option('recent-users') ?? 1;
        
        if ($userId) {
            $user = \App\Models\User::find($userId);
            if (!$user) {
                $this->error("Usuario no encontrado: {$userId}");
                return Command::FAILURE;
            }
            
            $this->sendWelcomeCouponToUser($user);
        } else {
            $this->sendWelcomeCouponsToRecentUsers($recentDays);
        }
        
        return Command::SUCCESS;
    }

    protected function sendWelcomeCouponToUser(\App\Models\User $user): void
    {
        $this->info("Enviando cupón de bienvenida a: {$user->name} ({$user->email})");
        
        $coupon = $this->autoCouponService->generateWelcomeCoupon($user);
        
        if ($coupon) {
            $this->notificationService->sendWelcomeCoupon($user);
            $this->info("✅ Cupón de bienvenida enviado: {$coupon->code}");
        } else {
            $this->warn("⚠️  Usuario ya tiene cupón de bienvenida");
        }
    }

    protected function sendWelcomeCouponsToRecentUsers(int $days): void
    {
        $recentUsers = \App\Models\User::where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('email_verified_at')
            ->get();
            
        $this->info("Enviando cupones de bienvenida a {$recentUsers->count()} usuarios registrados en los últimos {$days} días");
        
        $sentCount = 0;
        $bar = $this->output->createProgressBar($recentUsers->count());
        
        foreach ($recentUsers as $user) {
            $coupon = $this->autoCouponService->generateWelcomeCoupon($user);
            
            if ($coupon) {
                $this->notificationService->sendWelcomeCoupon($user);
                $sentCount++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("✅ Se enviaron {$sentCount} cupones de bienvenida");
    }
}