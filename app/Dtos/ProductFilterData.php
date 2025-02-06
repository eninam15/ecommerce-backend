<?php
namespace App\Dtos;
use App\Enums\ProductSortEnum;

class ProductFilterData {
    public function __construct(
        public ?string $search = null,
        public ?array $categoryIds = null,
        public ?bool $status = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?ProductSortEnum $sortBy = null,
        public bool $onPromotion = false,
        public int $perPage = 100
    ) {}
}
