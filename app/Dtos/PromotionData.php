<?php
namespace App\Dtos;

use App\Enums\PromotionTypeEnum;
use App\Enums\DiscountTypeEnum;
use Carbon\Carbon;
use App\Http\Requests\Promotion\PromotionRequest;

class PromotionData
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly PromotionTypeEnum $type,
        public readonly DiscountTypeEnum $discountType,
        public readonly float $discountValue,
        public readonly Carbon $startsAt,
        public readonly Carbon $endsAt,
        public readonly bool $status,
        public readonly ?int $minQuantity,
        public readonly ?int $maxQuantity,
        public readonly array $products,
    ) {}

    public static function fromRequest(PromotionRequest $request): self
    {
        return new self(
            name: $request->name,
            description: $request->description,
            type: PromotionTypeEnum::from($request->type),
            discountType: DiscountTypeEnum::from($request->discount_type),
            discountValue: $request->discount_value,
            startsAt: Carbon::parse($request->starts_at), // ✅ Usa Carbon en vez de DateTime
            endsAt: Carbon::parse($request->ends_at), // ✅ Usa Carbon en vez de DateTime
            status: $request->status,
            minQuantity: $request->min_quantity,
            maxQuantity: $request->max_quantity,
            products: $request->products,
        );
    }
}
