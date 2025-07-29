<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\CouponService;
use App\Http\Requests\Coupon\CouponRequest;
use App\Http\Requests\Coupon\CouponFilterRequest;
use App\Http\Resources\CouponResource;
use App\Http\Resources\CouponUsageResource;
use App\Http\Resources\CouponStatsResource;
use App\Dtos\CouponData;
use App\Dtos\CouponFilterData;
use App\Enums\CouponType;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * Listar cupones con filtros
     */
    public function index(CouponFilterRequest $request)
    {
        $filters = new CouponFilterData(
            search: $request->search,
            type: $request->type ? CouponType::from($request->type) : null,
            status: $request->boolean('status'),
            expired: $request->boolean('expired'),
            exhausted: $request->boolean('exhausted'),
            startsAfter: $request->starts_after ? new \DateTime($request->starts_after) : null,
            startsBefore: $request->starts_before ? new \DateTime($request->starts_before) : null,
            expiresAfter: $request->expires_after ? new \DateTime($request->expires_after) : null,
            expiresBefore: $request->expires_before ? new \DateTime($request->expires_before) : null,
            perPage: $request->per_page ?? 15
        );

        $coupons = $this->couponService->getCoupons($filters);

        return CouponResource::collection($coupons);
    }

    /**
     * Crear nuevo cupón
     */
    public function store(CouponRequest $request)
    {
        $coupon = $this->couponService->createCoupon(
            CouponData::fromRequest($request)
        );

        return response()->json([
            'message' => 'Cupón creado correctamente',
            'data' => new CouponResource($coupon)
        ], 201);
    }

    /**
     * Mostrar cupón específico
     */
    public function show(string $id)
    {
        $coupon = \App\Models\Coupon::with(['categories', 'products', 'usages.user', 'usages.order'])
            ->findOrFail($id);

        return new CouponResource($coupon);
    }

    /**
     * Actualizar cupón
     */
    public function update(CouponRequest $request, string $id)
    {
        $coupon = $this->couponService->updateCoupon(
            $id,
            CouponData::fromRequest($request)
        );

        return response()->json([
            'message' => 'Cupón actualizado correctamente',
            'data' => new CouponResource($coupon)
        ]);
    }

    /**
     * Eliminar cupón
     */
    public function destroy(string $id)
    {
        $success = $this->couponService->deleteCoupon($id);

        if ($success) {
            return response()->json([
                'message' => 'Cupón eliminado correctamente'
            ]);
        }

        return response()->json([
            'message' => 'Error al eliminar el cupón'
        ], 500);
    }

    /**
     * Generar código único
     */
    public function generateCode(Request $request)
    {
        $length = $request->input('length', 8);
        $code = $this->couponService->generateUniqueCode($length);

        return response()->json([
            'code' => $code
        ]);
    }

    /**
     * Obtener estadísticas de cupones
     */
    public function stats()
    {
        $stats = $this->couponService->getCouponStats();

        return new CouponStatsResource($stats);
    }

    /**
     * Obtener usos de un cupón específico
     */
    public function getUsages(string $id, Request $request)
    {
        $coupon = \App\Models\Coupon::findOrFail($id);
        
        $usages = $coupon->usages()
            ->with(['user', 'order'])
            ->when($request->start_date, function ($query, $startDate) {
                $query->where('created_at', '>=', $startDate);
            })
            ->when($request->end_date, function ($query, $endDate) {
                $query->where('created_at', '<=', $endDate);
            })
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return CouponUsageResource::collection($usages);
    }

    /**
     * Dashboard de cupones
     */
    public function dashboard()
    {
        $stats = $this->couponService->getCouponStats();
        
        // Cupones que expiran pronto (próximos 7 días)
        $expiringSoon = \App\Models\Coupon::where('expires_at', '>=', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->where('status', true)
            ->with(['categories', 'products'])
            ->get();

        // Cupones más usados en los últimos 30 días
        $topUsedRecent = \App\Models\Coupon::withCount([
                'usages' => function ($query) {
                    $query->where('created_at', '>=', now()->subDays(30));
                }
            ])
            ->having('usages_count', '>', 0)
            ->orderByDesc('usages_count')
            ->limit(5)
            ->get();

        // Cupones con poco uso (menos del 10% usado)
        $underperforming = \App\Models\Coupon::whereNotNull('usage_limit')
            ->whereRaw('(used_count / usage_limit) < 0.1')
            ->where('status', true)
            ->where('expires_at', '>', now())
            ->get();

        return response()->json([
            'stats' => new CouponStatsResource($stats),
            'expiring_soon' => CouponResource::collection($expiringSoon),
            'top_used_recent' => CouponResource::collection($topUsedRecent),
            'underperforming' => CouponResource::collection($underperforming),
            'alerts' => [
                'expiring_count' => $expiringSoon->count(),
                'underperforming_count' => $underperforming->count(),
                'expired_count' => \App\Models\Coupon::where('expires_at', '<', now())->count()
            ]
        ]);
    }

    /**
     * Duplicar cupón existente
     */
    public function duplicate(string $id)
    {
        $originalCoupon = \App\Models\Coupon::with(['categories', 'products'])->findOrFail($id);
        
        $newCode = $this->couponService->generateUniqueCode();
        
        $couponData = new CouponData(
            code: $newCode,
            name: $originalCoupon->name . ' (Copia)',
            description: $originalCoupon->description,
            type: $originalCoupon->type,
            discountValue: $originalCoupon->discount_value,
            minimumAmount: $originalCoupon->minimum_amount,
            maximumDiscount: $originalCoupon->maximum_discount,
            usageLimit: $originalCoupon->usage_limit,
            usageLimitPerUser: $originalCoupon->usage_limit_per_user,
            firstPurchaseOnly: $originalCoupon->first_purchase_only,
            status: false, // Crear inactivo por defecto
            startsAt: null,
            expiresAt: null,
            categoryIds: $originalCoupon->categories->pluck('id')->toArray(),
            productIds: $originalCoupon->products->pluck('id')->toArray()
        );

        $newCoupon = $this->couponService->createCoupon($couponData);

        return response()->json([
            'message' => 'Cupón duplicado correctamente',
            'data' => new CouponResource($newCoupon)
        ], 201);
    }

    /**
     * Activar/Desactivar cupón
     */
    public function toggleStatus(string $id)
    {
        $coupon = \App\Models\Coupon::findOrFail($id);
        $coupon->update(['status' => !$coupon->status]);

        return response()->json([
            'message' => $coupon->status ? 'Cupón activado' : 'Cupón desactivado',
            'data' => new CouponResource($coupon)
        ]);
    }

    /**
     * Obtener tipos de cupones disponibles
     */
    public function getTypes()
    {
        $types = collect(CouponType::cases())->map(function ($type) {
            return [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description()
            ];
        });

        return response()->json([
            'data' => $types
        ]);
    }
}