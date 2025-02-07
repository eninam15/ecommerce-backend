<?php

namespace App\Http\Requests\Product;
use App\Rules\Uuid;

use Illuminate\Foundation\Http\FormRequest;

class RelatedProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'related_product_ids' => ['required', 'array'],
            'related_product_ids.*' => ['exists:products,id', 'different:product_id'],
            'type' => ['required', 'string', Rule::enum(RelationshipTypeEnum::class)]
        ];
    }
}