<?php
namespace App\Dtos;

class RelatedProductData
{
    public function __construct(
        public readonly string $productId,
        public readonly array $relatedProductIds,
        public readonly RelationshipTypeEnum $type,
    ) {}

    public static function fromRequest(RelatedProductRequest $request): self
    {
        return new self(
            productId: $request->product_id,
            relatedProductIds: $request->related_product_ids,
            type: RelationshipTypeEnum::from($request->type)
        );
    }
}