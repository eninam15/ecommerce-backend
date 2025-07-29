<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CouponAnalyticsService;
use Illuminate\Http\Request;

class CouponAnalyticsController extends Controller
{
    public function __construct(
        protected CouponAnalyticsService $analyticsService
    ) {}

    /**
     * Dashboard principal de analytics
     */
    public function dashboard(Request $request)
    {
        $period = $request->input('period', '30d');
        $analytics = $this->analyticsService->getDashboardAnalytics($period);

        return response()->json([
            'data' => $analytics,
            'period' => $period,
            'generated_at' => now()
        ]);
    }

    /**
     * Análisis de segmentación de usuarios
     */
    public function userSegmentation(Request $request)
    {
        $period = $request->input('period', '90d');
        $segmentation = $this->analyticsService->getUserSegmentationAnalysis($period);

        return response()->json([
            'data' => $segmentation,
            'period' => $period
        ]);
    }

    /**
     * Efectividad por canal de notificación
     */
    public function channelEffectiveness(Request $request)
    {
        $period = $request->input('period', '30d');
        $effectiveness = $this->analyticsService->getChannelEffectivenessAnalysis($period);

        return response()->json([
            'data' => $effectiveness,
            'period' => $period
        ]);
    }

    /**
     * Predicciones y recomendaciones
     */
    public function predictions()
    {
        $predictions = $this->analyticsService->getPredictionsAndRecommendations();

        return response()->json([
            'data' => $predictions
        ]);
    }

    /**
     * Generar reporte detallado
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'period' => 'string|in:7d,30d,90d,1y',
            'format' => 'string|in:json,pdf,excel'
        ]);

        $period = $request->input('period', '30d');
        $format = $request->input('format', 'json');

        $report = $this->analyticsService->generateDetailedReport($period, $format);

        switch ($format) {
            case 'json':
                return response()->json($report);
                
            case 'pdf':
                // Implementar generación de PDF
                return response()->json([
                    'message' => 'PDF generation not implemented yet',
                    'download_url' => null
                ]);
                
            case 'excel':
                // Implementar generación de Excel
                return response()->json([
                    'message' => 'Excel generation not implemented yet',
                    'download_url' => null
                ]);
                
            default:
                return response()->json($report);
        }
    }

    /**
     * Comparar períodos
     */
    public function comparePerios(Request $request)
    {
        $request->validate([
            'current_period' => 'required|string',
            'comparison_period' => 'required|string'
        ]);

        $currentAnalytics = $this->analyticsService->getDashboardAnalytics(
            $request->current_period
        );
        
        $comparisonAnalytics = $this->analyticsService->getDashboardAnalytics(
            $request->comparison_period
        );

        // Calcular diferencias porcentuales
        $comparison = $this->calculatePeriodComparison($currentAnalytics, $comparisonAnalytics);

        return response()->json([
            'current_period' => [
                'period' => $request->current_period,
                'data' => $currentAnalytics
            ],
            'comparison_period' => [
                'period' => $request->comparison_period,
                'data' => $comparisonAnalytics
            ],
            'comparison' => $comparison
        ]);
    }

    /**
     * Métricas en tiempo real
     */
    public function realTimeMetrics()
    {
        $today = now()->startOfDay();
        
        $todayUsages = \App\Models\CouponUsage::where('created_at', '>=', $today)->count();
        $todayRevenue = \App\Models\Order::whereNotNull('coupon_id')
            ->where('created_at', '>=', $today)
            ->sum('total');
        $todayDiscounts = \App\Models\CouponUsage::where('created_at', '>=', $today)
            ->sum('discount_amount');

        // Comparar con ayer
        $yesterday = now()->subDay()->startOfDay();
        $yesterdayUsages = \App\Models\CouponUsage::whereBetween('created_at', [
            $yesterday, $yesterday->copy()->endOfDay()
        ])->count();

        $usageChange = $yesterdayUsages > 0 
            ? (($todayUsages - $yesterdayUsages) / $yesterdayUsages) * 100 
            : 0;

        // Últimos cupones usados
        $recentUsages = \App\Models\CouponUsage::with(['coupon', 'user', 'order'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'today_metrics' => [
                'usages' => $todayUsages,
                'revenue' => $todayRevenue,
                'discounts_given' => $todayDiscounts,
                'usage_change_vs_yesterday' => round($usageChange, 2)
            ],
            'recent_activities' => \App\Http\Resources\CouponUsageResource::collection($recentUsages),
            'updated_at' => now()
        ]);
    }

    /**
     * Análisis de A/B testing (estructura para futuro)
     */
    public function abTestResults(Request $request)
    {
        // Placeholder para A/B testing de cupones
        return response()->json([
            'message' => 'A/B testing functionality coming soon',
            'suggested_tests' => [
                'discount_amount_test' => '10% vs 15% vs 20% discount effectiveness',
                'expiry_urgency_test' => '24h vs 7d vs 30d expiry impact',
                'notification_timing_test' => 'Immediate vs 1h vs 24h delay',
                'email_subject_test' => 'Different email subject line performance'
            ]
        ]);
    }

    /**
     * Calcular comparación entre períodos
     */
    protected function calculatePeriodComparison(array $current, array $comparison): array
    {
        $metrics = [
            'usages_in_period' => $current['overview']['usages_in_period'] ?? 0,
            'total_discount_given' => $current['overview']['total_discount_given'] ?? 0,
            'redemption_rate' => $current['overview']['redemption_rate'] ?? 0,
            'engagement_rate' => $current['user_engagement']['engagement_rate_percentage'] ?? 0
        ];

        $comparisonMetrics = [
            'usages_in_period' => $comparison['overview']['usages_in_period'] ?? 0,
            'total_discount_given' => $comparison['overview']['total_discount_given'] ?? 0,
            'redemption_rate' => $comparison['overview']['redemption_rate'] ?? 0,
            'engagement_rate' => $comparison['user_engagement']['engagement_rate_percentage'] ?? 0
        ];

        $changes = [];
        foreach ($metrics as $key => $value) {
            $comparisonValue = $comparisonMetrics[$key];
            $change = $comparisonValue > 0 
                ? (($value - $comparisonValue) / $comparisonValue) * 100 
                : 0;
                
            $changes[$key] = [
                'current' => $value,
                'comparison' => $comparisonValue,
                'change_percentage' => round($change, 2),
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'stable')
            ];
        }

        return $changes;
    }
}