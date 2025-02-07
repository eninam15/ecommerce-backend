<?php
namespace App\Dtos;

class PromotionData
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly PromotionTypeEnum $type,
        public readonly DiscountTypeEnum $discountType,
        public readonly float $discountValue,
        public readonly DateTime $startsAt,
        public readonly DateTime $endsAt,
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
            startsAt: new DateTime($request->starts_at),
            endsAt: new DateTime($request->ends_at),
            status: $request->status,
            minQuantity: $request->min_quantity,
            maxQuantity: $request->max_quantity,
            products: $request->products,
        );
    }
}
