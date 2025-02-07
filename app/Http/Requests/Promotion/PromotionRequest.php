<?php

namespace App\Http\Requests\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\Uuid;
use App\Enums\PromotionTypeEnum;
use App\Enums\DiscountTypeEnum;

class PromotionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::enum(PromotionTypeEnum::class)],
            'discount_type' => ['required', 'string', Rule::enum(DiscountTypeEnum::class)],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', 'boolean'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'after:min_quantity'],
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'exists:products,id'],
            'products.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'products.*.quantity_required' => ['nullable', 'integer', 'min:1']
        ];
    }
}
