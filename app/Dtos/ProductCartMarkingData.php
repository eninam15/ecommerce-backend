<?php
namespace App\Dtos;

class ProductCartMarkingData {
    public function __construct(
        public ?string $userId = null,
        public ?array $cartItemProductIds = null
    ) {}
}
