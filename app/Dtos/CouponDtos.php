<?php

namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;
use App\Enums\CouponType;
use App\Enums\CouponValidationResult;
use App\Http\Requests\Coupon\CouponRequest;

class CouponData extends DataTransferObject
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description,
        public CouponType $type,
        public ?float $discountValue,
        public ?float $minimumAmount,
        public ?float $maximumDiscount,
        public ?int $usageLimit,
        public ?int $usageLimitPerUser,
        public bool $firstPurchaseOnly,
        public bool $status,
        public ?\DateTime $startsAt,
        public ?\DateTime $expiresAt,
        public ?array $categoryIds = [],
        public ?array $productIds = []
    ) {}

    public static function fromRequest(CouponRequest $request): self
    {
        return new self(
            code: strtoupper($request->code),
            name: $request->name,
            description: $request->description,
            type: CouponType::from($request->type),
            discountValue: $request->discount_value,
            minimumAmount: $request->minimum_amount,
            maximumDiscount: $request->maximum_discount,
            usageLimit: $request->usage_limit,
            usageLimitPerUser: $request->usage_limit_per_user,
            firstPurchaseOnly: $request->boolean('first_purchase_only'),
            status: $request->boolean('status', true),
            startsAt: $request->starts_at ? \DateTime::createFromFormat('Y-m-d H:i:s', $request->starts_at) : null,
            expiresAt: $request->expires_at ? \DateTime::createFromFormat('Y-m-d H:i:s', $request->expires_at) : null,
            categoryIds: $request->category_ids ?? [],
            productIds: $request->product_ids ?? []
        );
    }
}

class CouponValidationData extends DataTransferObject
{
    public function __construct(
        public string $couponCode,
        public string $userId,
        public float $cartSubtotal,
        public array $cartItems,
        public bool $isFirstPurchase = false,
        public ?string $cartId = null
    ) {}
}

class CouponDiscountData extends DataTransferObject
{
    public function __construct(
        public string $couponId,
        public string $couponCode,
        public CouponType $type,
        public float $discountAmount,
        public float $originalSubtotal,
        public float $finalSubtotal,
        public bool $freeShipping = false,
        public ?array $applicableItems = []
    ) {}

    public function getDiscountPercentage(): float
    {
        return $this->originalSubtotal > 0 
            ? ($this->discountAmount / $this->originalSubtotal) * 100 
            : 0;
    }
}

class CouponValidationResulta extends DataTransferObject
{
    public function __construct(
        public CouponValidationResult $result,
        public string $message,
        public bool $isValid,
        public ?CouponDiscountData $discountData = null,
        public ?array $validationDetails = []
    ) {}

    public static function valid(CouponDiscountData $discountData): self
    {
        return new self(
            result: \App\Enums\CouponValidationResult::VALID,
            message: 'Cupón aplicado correctamente',
            isValid: true,
            discountData: $discountData
        );
    }

    public static function invalid(\App\Enums\CouponValidationResult $result, array $details = []): self
    {
        return new self(
            result: $result,
            message: $result->message(),
            isValid: false,
            validationDetails: $details
        );
    }
}

class ApplyCouponData extends DataTransferObject
{
    public function __construct(
        public string $couponCode,
        public string $userId,
        public ?string $cartId = null
    ) {}
}

class CouponUsageData extends DataTransferObject
{
    public function __construct(
        public string $couponId,
        public string $userId,
        public string $orderId,
        public float $discountAmount
    ) {}
}

class CouponFilterData extends DataTransferObject
{
    public function __construct(
        public ?string $search = null,
        public ?CouponType $type = null,
        public ?bool $status = null,
        public ?bool $expired = null,
        public ?bool $exhausted = null,
        public ?\DateTime $startsAfter = null,
        public ?\DateTime $startsBefore = null,
        public ?\DateTime $expiresAfter = null,
        public ?\DateTime $expiresBefore = null,
        public int $perPage = 15
    ) {}
}