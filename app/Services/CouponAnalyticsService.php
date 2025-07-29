<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CouponNotification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CouponAnalyticsService
{
    /**
     * Dashboard principal de analytics de cupones
     */
    public function getDashboardAnalytics(string $period = '30d'): array
    {
        $dateRange = $this->getDateRange($period);

        return [
            'overview' => $this->getOverviewStats($dateRange),
            'performance' => $this->getPerformanceStats($dateRange),
            'revenue_impact' => $this->getRevenueImpact($dateRange),
            'user_engagement' => $this->getUserEngagementStats($dateRange),
            'trends' => $this->getTrendAnalysis($dateRange),
            'top_performers' => $this->getTopPerformers($dateRange),
            'conversion_funnel' => $this->getConversionFunnel($dateRange)
        ];
    }

    /**
     * Estadísticas generales
     */
    protected function getOverviewStats(array $dateRange): array
    {
        $totalCoupons = Coupon::count();
        $activeCoupons = Coupon::where('status', true)->count();
        
        $usagesInPeriod = CouponUsage::whereBetween('created_at', $dateRange)->count();
        $previousPeriodUsages = CouponUsage::whereBetween('created_at', [
            Carbon::parse($dateRange[0])->subDays(Carbon::parse($dateRange[1])->diffInDays(Carbon::parse($dateRange[0]))),
            Carbon::parse($dateRange[0])
        ])->count();

        $usageGrowth = $previousPeriodUsages > 0 
            ? (($usagesInPeriod - $previousPeriodUsages) / $previousPeriodUsages) * 100 
            : 0;

        $totalDiscountGiven = CouponUsage::whereBetween('created_at', $dateRange)
            ->sum('discount_amount');

        $averageDiscountPerUse = $usagesInPeriod > 0 
            ? $totalDiscountGiven / $usagesInPeriod 
            : 0;

        return [
            'total_coupons' => $totalCoupons,
            'active_coupons' => $activeCoupons,
            'usages_in_period' => $usagesInPeriod,
            'usage_growth_percentage' => round($usageGrowth, 2),
            'total_discount_given' => $totalDiscountGiven,
            'average_discount_per_use' => round($averageDiscountPerUse, 2),
            'redemption_rate' => $this->calculateRedemptionRate($dateRange)
        ];
    }

    /**
     * Estadísticas de rendimiento por tipo de cupón
     */
    protected function getPerformanceStats(array $dateRange): array
    {
        $performanceByType = CouponUsage::select(
                'coupons.type',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('SUM(coupon_usages.discount_amount) as total_discount'),
                DB::raw('AVG(coupon_usages.discount_amount) as avg_discount'),
                DB::raw('COUNT(DISTINCT coupon_usages.user_id) as unique_users')
            )
            ->join('coupons', 'coupon_usages.coupon_id', '=', 'coupons.id')
            ->whereBetween('coupon_usages.created_at', $dateRange)
            ->groupBy('coupons.type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'type_label' => \App\Enums\CouponType::from($item->type)->label(),
                    'usage_count' => $item->usage_count,
                    'total_discount' => $item->total_discount,
                    'avg_discount' => round($item->avg_discount, 2),
                    'unique_users' => $item->unique_users,
                    'avg_uses_per_user' => $item->unique_users > 0 
                        ? round($item->usage_count / $item->unique_users, 2) 
                        : 0
                ];
            });

        return $performanceByType->toArray();
    }

    /**
     * Impacto en ingresos
     */
    protected function getRevenueImpact(array $dateRange): array
    {
        // Órdenes con cupones vs sin cupones
        $ordersWithCoupons = Order::whereNotNull('coupon_id')
            ->whereBetween('created_at', $dateRange)
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('SUM(coupon_discount) as total_discounts'),
                DB::raw('AVG(total) as avg_order_value')
            )
            ->first();

        $ordersWithoutCoupons = Order::whereNull('coupon_id')
            ->whereBetween('created_at', $dateRange)
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(total) as avg_order_value')
            )
            ->first();

        // Calcular ROI de cupones (asumiendo que sin cupones no habrían comprado)
        $couponROI = $ordersWithCoupons->total_discounts > 0 
            ? (($ordersWithCoupons->revenue - $ordersWithCoupons->total_discounts) / $ordersWithCoupons->total_discounts) * 100
            : 0;

        return [
            'orders_with_coupons' => [
                'count' => $ordersWithCoupons->count ?? 0,
                'revenue' => $ordersWithCoupons->revenue ?? 0,
                'total_discounts' => $ordersWithCoupons->total_discounts ?? 0,
                'avg_order_value' => round($ordersWithCoupons->avg_order_value ?? 0, 2)
            ],
            'orders_without_coupons' => [
                'count' => $ordersWithoutCoupons->count ?? 0,
                'revenue' => $ordersWithoutCoupons->revenue ?? 0,
                'avg_order_value' => round($ordersWithoutCoupons->avg_order_value ?? 0, 2)
            ],
            'coupon_roi_percentage' => round($couponROI, 2),
            'revenue_with_vs_without_coupons' => [
                'percentage_with_coupons' => $this->calculatePercentage(
                    $ordersWithCoupons->revenue ?? 0,
                    ($ordersWithCoupons->revenue ?? 0) + ($ordersWithoutCoupons->revenue ?? 0)
                ),
                'avg_order_lift' => $this->calculateOrderValueLift(
                    $ordersWithCoupons->avg_order_value ?? 0,
                    $ordersWithoutCoupons->avg_order_value ?? 0
                )
            ]
        ];
    }

    /**
     * Estadísticas de engagement de usuarios
     */
    protected function getUserEngagementStats(array $dateRange): array
    {
        // Usuarios que usaron cupones
        $couponUsers = CouponUsage::whereBetween('created_at', $dateRange)
            ->distinct('user_id')
            ->count();

        // Usuarios totales activos (con órdenes)
        $totalActiveUsers = Order::whereBetween('created_at', $dateRange)
            ->distinct('user_id')
            ->count();

        $engagementRate = $totalActiveUsers > 0 
            ? ($couponUsers / $totalActiveUsers) * 100 
            : 0;

        // Usuarios que usan cupones múltiples veces
        $repeatCouponUsers = CouponUsage::select('user_id')
            ->whereBetween('created_at', $dateRange)
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $repeatRate = $couponUsers > 0 
            ? ($repeatCouponUsers / $couponUsers) * 100 
            : 0;

        // Segmentación por frecuencia de uso
        $userSegmentation = CouponUsage::select(
                'user_id',
                DB::raw('COUNT(*) as usage_count')
            )
            ->whereBetween('created_at', $dateRange)
            ->groupBy('user_id')
            ->get()
            ->groupBy(function ($item) {
                return match(true) {
                    $item->usage_count >= 5 => 'heavy_users',
                    $item->usage_count >= 3 => 'regular_users',
                    $item->usage_count >= 2 => 'occasional_users',
                    default => 'single_use_users'
                };
            })
            ->map->count();

        return [
            'coupon_users' => $couponUsers,
            'total_active_users' => $totalActiveUsers,
            'engagement_rate_percentage' => round($engagementRate, 2),
            'repeat_users' => $repeatCouponUsers,
            'repeat_rate_percentage' => round($repeatRate, 2),
            'user_segmentation' => [
                'heavy_users' => $userSegmentation['heavy_users'] ?? 0,
                'regular_users' => $userSegmentation['regular_users'] ?? 0,
                'occasional_users' => $userSegmentation['occasional_users'] ?? 0,
                'single_use_users' => $userSegmentation['single_use_users'] ?? 0
            ]
        ];
    }

    /**
     * Análisis de tendencias temporales
     */
    protected function getTrendAnalysis(array $dateRange): array
    {
        $period = CarbonPeriod::create($dateRange[0], '1 day', $dateRange[1]);
        
        $dailyUsage = [];
        $dailyRevenue = [];
        $dailyDiscounts = [];

        foreach ($period as $date) {
            $dayStart = $date->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $usageCount = CouponUsage::whereBetween('created_at', [$dayStart, $dayEnd])->count();
            
            $dayRevenue = Order::whereNotNull('coupon_id')
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('total');
                
            $dayDiscounts = CouponUsage::whereBetween('created_at', [$dayStart, $dayEnd])
                ->sum('discount_amount');

            $dailyUsage[] = [
                'date' => $date->format('Y-m-d'),
                'usage_count' => $usageCount
            ];

            $dailyRevenue[] = [
                'date' => $date->format('Y-m-d'),
                'revenue' => $dayRevenue
            ];

            $dailyDiscounts[] = [
                'date' => $date->format('Y-m-d'),
                'discounts' => $dayDiscounts
            ];
        }

        return [
            'daily_usage' => $dailyUsage,
            'daily_revenue' => $dailyRevenue,
            'daily_discounts' => $dailyDiscounts,
            'peak_usage_day' => collect($dailyUsage)->sortByDesc('usage_count')->first(),
            'peak_revenue_day' => collect($dailyRevenue)->sortByDesc('revenue')->first()
        ];
    }

    /**
     * Top cupones con mejor rendimiento
     */
    protected function getTopPerformers(array $dateRange): array
    {
        $topByCoverage = CouponUsage::select(
                'coupons.id',
                'coupons.code',
                'coupons.name',
                'coupons.type',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('COUNT(DISTINCT coupon_usages.user_id) as unique_users'),
                DB::raw('SUM(coupon_usages.discount_amount) as total_discount')
            )
            ->join('coupons', 'coupon_usages.coupon_id', '=', 'coupons.id')
            ->whereBetween('coupon_usages.created_at', $dateRange)
            ->groupBy('coupons.id', 'coupons.code', 'coupons.name', 'coupons.type')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get();

        $topByRevenue = Order::select(
                'coupons.id',
                'coupons.code',
                'coupons.name',
                'coupons.type',
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(orders.total) as revenue_generated'),
                DB::raw('SUM(orders.coupon_discount) as total_discount'),
                DB::raw('AVG(orders.total) as avg_order_value')
            )
            ->join('coupons', 'orders.coupon_id', '=', 'coupons.id')
            ->whereBetween('orders.created_at', $dateRange)
            ->groupBy('coupons.id', 'coupons.code', 'coupons.name', 'coupons.type')
            ->orderByDesc('revenue_generated')
            ->limit(10)
            ->get();

        $topByROI = Order::select(
                'coupons.id',
                'coupons.code',
                'coupons.name',
                DB::raw('SUM(orders.total - orders.coupon_discount) as net_revenue'),
                DB::raw('SUM(orders.coupon_discount) as total_discount'),
                DB::raw('((SUM(orders.total - orders.coupon_discount) / SUM(orders.coupon_discount)) * 100) as roi_percentage')
            )
            ->join('coupons', 'orders.coupon_id', '=', 'coupons.id')
            ->whereBetween('orders.created_at', $dateRange)
            ->groupBy('coupons.id', 'coupons.code', 'coupons.name')
            ->havingRaw('SUM(orders.coupon_discount) > 0')
            ->orderByDesc('roi_percentage')
            ->limit(10)
            ->get();

        return [
            'top_by_usage' => $topByCoverage,
            'top_by_revenue' => $topByRevenue,
            'top_by_roi' => $topByROI
        ];
    }

    /**
     * Análisis del embudo de conversión
     */
    protected function getConversionFunnel(array $dateRange): array
    {
        // Notificaciones enviadas
        $notificationsSent = CouponNotification::where('status', 'sent')
            ->whereBetween('sent_at', $dateRange)
            ->count();

        // Notificaciones clickeadas
        $notificationsClicked = CouponNotification::where('status', 'clicked')
            ->whereBetween('clicked_at', $dateRange)
            ->count();

        // Cupones aplicados (únicos por usuario)
        $couponsApplied = CouponUsage::whereBetween('created_at', $dateRange)
            ->distinct('user_id')
            ->count();

        // Conversiones a órdenes
        $ordersFromCoupons = Order::whereNotNull('coupon_id')
            ->whereBetween('created_at', $dateRange)
            ->count();

        // Calcular tasas de conversión
        $clickRate = $notificationsSent > 0 
            ? ($notificationsClicked / $notificationsSent) * 100 
            : 0;

        $applicationRate = $notificationsClicked > 0 
            ? ($couponsApplied / $notificationsClicked) * 100 
            : 0;

        $conversionRate = $couponsApplied > 0 
            ? ($ordersFromCoupons / $couponsApplied) * 100 
            : 0;

        $overallConversionRate = $notificationsSent > 0 
            ? ($ordersFromCoupons / $notificationsSent) * 100 
            : 0;

        return [
            'funnel_steps' => [
                'notifications_sent' => $notificationsSent,
                'notifications_clicked' => $notificationsClicked,
                'coupons_applied' => $couponsApplied,
                'orders_completed' => $ordersFromCoupons
            ],
            'conversion_rates' => [
                'click_rate' => round($clickRate, 2),
                'application_rate' => round($applicationRate, 2),
                'conversion_rate' => round($conversionRate, 2),
                'overall_conversion_rate' => round($overallConversionRate, 2)
            ]
        ];
    }

    /**
     * Análisis de segmentación de usuarios
     */
    public function getUserSegmentationAnalysis(string $period = '90d'): array
    {
        $dateRange = $this->getDateRange($period);

        // Nuevos usuarios vs usuarios existentes
        $newUserOrders = Order::whereNotNull('coupon_id')
            ->whereBetween('created_at', $dateRange)
            ->whereHas('user', function ($query) use ($dateRange) {
                $query->where('created_at', '>=', $dateRange[0]);
            })
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(total) as avg_order_value'),
                DB::raw('SUM(coupon_discount) as total_discounts')
            )
            ->first();

        $existingUserOrders = Order::whereNotNull('coupon_id')
            ->whereBetween('created_at', $dateRange)
            ->whereHas('user', function ($query) use ($dateRange) {
                $query->where('created_at', '<', $dateRange[0]);
            })
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(total) as avg_order_value'),
                DB::raw('SUM(coupon_discount) as total_discounts')
            )
            ->first();

        // Análisis por valor de cliente (CLV)
        $clvSegments = User::select(
                'users.id',
                DB::raw('SUM(orders.total) as total_spent'),
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('COUNT(coupon_usages.id) as coupon_usage_count')
            )
            ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
            ->leftJoin('coupon_usages', 'users.id', '=', 'coupon_usages.user_id')
            ->whereBetween('orders.created_at', $dateRange)
            ->groupBy('users.id')
            ->get()
            ->groupBy(function ($user) {
                return match(true) {
                    $user->total_spent >= 1000 => 'high_value',
                    $user->total_spent >= 500 => 'medium_value',
                    $user->total_spent >= 100 => 'low_value',
                    default => 'minimal_value'
                };
            })
            ->map(function ($segment, $key) {
                return [
                    'segment' => $key,
                    'user_count' => $segment->count(),
                    'avg_spent' => round($segment->avg('total_spent'), 2),
                    'avg_orders' => round($segment->avg('order_count'), 2),
                    'avg_coupon_usage' => round($segment->avg('coupon_usage_count'), 2),
                    'total_revenue' => $segment->sum('total_spent')
                ];
            });

        return [
            'new_vs_existing' => [
                'new_users' => [
                    'orders' => $newUserOrders->count ?? 0,
                    'revenue' => $newUserOrders->revenue ?? 0,
                    'avg_order_value' => round($newUserOrders->avg_order_value ?? 0, 2),
                    'total_discounts' => $newUserOrders->total_discounts ?? 0
                ],
                'existing_users' => [
                    'orders' => $existingUserOrders->count ?? 0,
                    'revenue' => $existingUserOrders->revenue ?? 0,
                    'avg_order_value' => round($existingUserOrders->avg_order_value ?? 0, 2),
                    'total_discounts' => $existingUserOrders->total_discounts ?? 0
                ]
            ],
            'clv_segments' => $clvSegments->values()->toArray()
        ];
    }

    /**
     * Análisis de efectividad por canal de notificación
     */
    public function getChannelEffectivenessAnalysis(string $period = '30d'): array
    {
        $dateRange = $this->getDateRange($period);

        $channelStats = CouponNotification::select(
                'channel',
                DB::raw('COUNT(*) as sent_count'),
                DB::raw('SUM(CASE WHEN status = "clicked" THEN 1 ELSE 0 END) as clicked_count'),
                DB::raw('SUM(CASE WHEN status = "sent" OR status = "clicked" THEN 1 ELSE 0 END) as delivered_count'),
                DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count')
            )
            ->whereBetween('created_at', $dateRange)
            ->groupBy('channel')
            ->get()
            ->map(function ($item) {
                $deliveryRate = $item->sent_count > 0 
                    ? ($item->delivered_count / $item->sent_count) * 100 
                    : 0;
                
                $clickRate = $item->delivered_count > 0 
                    ? ($item->clicked_count / $item->delivered_count) * 100 
                    : 0;

                return [
                    'channel' => $item->channel,
                    'sent_count' => $item->sent_count,
                    'delivered_count' => $item->delivered_count,
                    'clicked_count' => $item->clicked_count,
                    'failed_count' => $item->failed_count,
                    'delivery_rate' => round($deliveryRate, 2),
                    'click_rate' => round($clickRate, 2),
                    'effectiveness_score' => round($deliveryRate * $clickRate / 100, 2)
                ];
            });

        return $channelStats->toArray();
    }

    /**
     * Reporte de predicción y recomendaciones
     */
    public function getPredictionsAndRecommendations(): array
    {
        $currentMonth = $this->getDateRange('30d');
        $previousMonth = $this->getDateRange('60d', '30d');

        // Tendencia de uso
        $currentUsage = CouponUsage::whereBetween('created_at', $currentMonth)->count();
        $previousUsage = CouponUsage::whereBetween('created_at', $previousMonth)->count();
        
        $usageTrend = $previousUsage > 0 
            ? (($currentUsage - $previousUsage) / $previousUsage) * 100 
            : 0;

        // Predicción para próximo mes
        $predictedUsage = $currentUsage * (1 + ($usageTrend / 100));

        // Análisis de rendimiento por tipo
        $typePerformance = $this->analyzeTypePerformance();

        // Recomendaciones automáticas
        $recommendations = $this->generateRecommendations($typePerformance, $usageTrend);

        return [
            'predictions' => [
                'next_month_usage' => round($predictedUsage),
                'usage_trend_percentage' => round($usageTrend, 2),
                'trend_direction' => $usageTrend > 0 ? 'up' : ($usageTrend < 0 ? 'down' : 'stable')
            ],
            'type_performance' => $typePerformance,
            'recommendations' => $recommendations,
            'optimization_opportunities' => $this->getOptimizationOpportunities()
        ];
    }

    /**
     * Generar reporte completo en PDF/Excel (estructura)
     */
    public function generateDetailedReport(string $period = '30d', string $format = 'array'): array
    {
        $analytics = $this->getDashboardAnalytics($period);
        $segmentation = $this->getUserSegmentationAnalysis($period);
        $channelEffectiveness = $this->getChannelEffectivenessAnalysis($period);
        $predictions = $this->getPredictionsAndRecommendations();

        $report = [
            'report_metadata' => [
                'generated_at' => now(),
                'period' => $period,
                'date_range' => $this->getDateRange($period),
                'format' => $format
            ],
            'executive_summary' => $this->generateExecutiveSummary($analytics),
            'detailed_analytics' => $analytics,
            'user_segmentation' => $segmentation,
            'channel_effectiveness' => $channelEffectiveness,
            'predictions_and_recommendations' => $predictions
        ];

        // En implementación real, aquí generarías PDF o Excel
        return $report;
    }

    // ===== MÉTODOS AUXILIARES =====

    protected function getDateRange(string $period, string $offset = null): array
    {
        $end = $offset ? now()->sub($offset) : now();
        
        $start = match($period) {
            '7d' => $end->copy()->subDays(7),
            '30d' => $end->copy()->subDays(30),
            '90d' => $end->copy()->subDays(90),
            '1y' => $end->copy()->subYear(),
            default => $end->copy()->subDays(30)
        };

        return [$start, $end];
    }

    protected function calculatePercentage(float $value, float $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0;
    }

    protected function calculateOrderValueLift(float $withCoupons, float $withoutCoupons): float
    {
        return $withoutCoupons > 0 
            ? round((($withCoupons - $withoutCoupons) / $withoutCoupons) * 100, 2) 
            : 0;
    }

    protected function calculateRedemptionRate(array $dateRange): float
    {
        $couponsIssued = CouponNotification::where('status', 'sent')
            ->whereBetween('sent_at', $dateRange)
            ->count();

        $couponsUsed = CouponUsage::whereBetween('created_at', $dateRange)->count();

        return $couponsIssued > 0 
            ? round(($couponsUsed / $couponsIssued) * 100, 2) 
            : 0;
    }

    protected function analyzeTypePerformance(): array
    {
        return CouponUsage::select(
                'coupons.type',
                DB::raw('COUNT(*) as usage_count'),
                DB::raw('AVG(coupon_usages.discount_amount) as avg_discount'),
                DB::raw('COUNT(DISTINCT coupon_usages.user_id) as unique_users')
            )
            ->join('coupons', 'coupon_usages.coupon_id', '=', 'coupons.id')
            ->where('coupon_usages.created_at', '>=', now()->subDays(90))
            ->groupBy('coupons.type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->type,
                    'performance_score' => $this->calculatePerformanceScore($item),
                    'usage_count' => $item->usage_count,
                    'avg_discount' => round($item->avg_discount, 2),
                    'unique_users' => $item->unique_users
                ];
            })
            ->toArray();
    }

    protected function calculatePerformanceScore($typeData): float
    {
        // Score basado en uso, usuarios únicos y eficiencia del descuento
        $usageScore = min($typeData->usage_count / 100, 1) * 40; // max 40 puntos
        $userScore = min($typeData->unique_users / 50, 1) * 30; // max 30 puntos
        $efficiencyScore = (100 - min($typeData->avg_discount, 100)) * 0.3; // max 30 puntos

        return round($usageScore + $userScore + $efficiencyScore, 1);
    }

    protected function generateRecommendations(array $typePerformance, float $usageTrend): array
    {
        $recommendations = [];

        // Recomendaciones basadas en tendencia
        if ($usageTrend < -10) {
            $recommendations[] = [
                'type' => 'urgent',
                'title' => 'Uso de cupones en declive',
                'description' => 'El uso de cupones ha bajado significativamente. Considera crear promociones más atractivas.',
                'action' => 'create_attractive_coupons'
            ];
        } elseif ($usageTrend > 20) {
            $recommendations[] = [
                'type' => 'opportunity',
                'title' => 'Alto crecimiento en uso',
                'description' => 'El uso de cupones está creciendo. Aprovecha para lanzar cupones premium.',
                'action' => 'scale_up_campaigns'
            ];
        }

        // Recomendaciones basadas en performance por tipo
        $bestPerforming = collect($typePerformance)->sortByDesc('performance_score')->first();
        if ($bestPerforming) {
            $recommendations[] = [
                'type' => 'optimization',
                'title' => "Expandir cupones tipo: {$bestPerforming['type']}",
                'description' => "Los cupones de tipo {$bestPerforming['type']} tienen el mejor rendimiento.",
                'action' => 'create_more_of_type',
                'data' => ['type' => $bestPerforming['type']]
            ];
        }

        return $recommendations;
    }

    protected function getOptimizationOpportunities(): array
    {
        $opportunities = [];

        // Cupones con bajo uso
        $underperformingCoupons = Coupon::where('status', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->withCount('usages')
            ->having('usages_count', '<', 5)
            ->count();

        if ($underperformingCoupons > 0) {
            $opportunities[] = [
                'type' => 'low_usage',
                'title' => 'Cupones con bajo rendimiento',
                'description' => "{$underperformingCoupons} cupones tienen menos de 5 usos en 30 días",
                'action' => 'review_and_optimize'
            ];
        }

        // Oportunidades de segmentación
        $totalUsers = User::count();
        $couponUsers = CouponUsage::distinct('user_id')->count();
        $untappedUsers = $totalUsers - $couponUsers;

        if ($untappedUsers > ($totalUsers * 0.5)) {
            $opportunities[] = [
                'type' => 'user_expansion',
                'title' => 'Usuarios sin usar cupones',
                'description' => "{$untappedUsers} usuarios nunca han usado cupones",
                'action' => 'create_onboarding_campaign'
            ];
        }

        return $opportunities;
    }

    protected function generateExecutiveSummary(array $analytics): array
    {
        $overview = $analytics['overview'];
        $performance = $analytics['performance'];
        $revenue = $analytics['revenue_impact'];

        return [
            'key_metrics' => [
                'total_revenue_impact' => $revenue['orders_with_coupons']['revenue'],
                'total_discounts_given' => $overview['total_discount_given'],
                'coupon_adoption_rate' => $analytics['user_engagement']['engagement_rate_percentage'],
                'roi_percentage' => $revenue['coupon_roi_percentage']
            ],
            'highlights' => [
                'best_performing_type' => collect($performance)->sortByDesc('usage_count')->first()['type_label'] ?? 'N/A',
                'growth_trend' => $overview['usage_growth_percentage'] > 0 ? 'positive' : 'negative',
                'conversion_rate' => $analytics['conversion_funnel']['conversion_rates']['overall_conversion_rate']
            ],
            'summary_text' => $this->generateSummaryText($analytics)
        ];
    }

    protected function generateSummaryText(array $analytics): string
    {
        $growth = $analytics['overview']['usage_growth_percentage'];
        $roi = $analytics['revenue_impact']['coupon_roi_percentage'];
        $engagement = $analytics['user_engagement']['engagement_rate_percentage'];

        $growthText = $growth > 0 ? "crecimiento del {$growth}%" : "declive del " . abs($growth) . "%";
        
        return "En el período analizado, los cupones mostraron un {$growthText} en su uso, " .
               "generando un ROI del {$roi}% y una tasa de adopción del {$engagement}% entre usuarios activos. " .
               "Las estrategias de cupones están " . ($roi > 100 ? "siendo muy rentables" : "necesitando optimización") . ".";
    }
}