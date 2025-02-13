<?php

namespace App\Http\Requests\Product;
use App\Rules\Uuid;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'category_id' => [
                'required',
                new Uuid,
                'exists:categories,id'
            ],
            'code' => ['required', 'unique:products,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'volume' => ['nullable', 'numeric', 'min:0'],
            'flavor' => ['nullable', 'string', 'max:255'],
            'flavor' => ['nullable', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'sku' => ['nullable', 'string', 'alpha_num'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'status' => ['boolean'],
            'featured' => ['boolean'],
            'is_seasonal' => ['boolean'],
            'manufacture_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],

            'nutritional_info' => ['nullable', 'array'],
            'ingredients' => ['nullable', 'array'],

            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:2048'], // 2MB max
            'primary_image' => ['nullable', 'integer', 'min:0']
        ];
    }

    protected function prepareForValidation()
    {
        // Convertir strings 'true'/'false' a booleanos
        if ($this->has('status')) {
            $this->merge([
                'status' => $this->status === 'true' || $this->status === '1' || $this->status === true,
            ]);
        }

        // Asegurar que price sea numérico
        if ($this->has('price')) {
            $this->merge([
                'price' => (float) $this->price,
            ]);
        }

        // Asegurar que stock sea entero
        if ($this->has('stock')) {
            $this->merge([
                'stock' => (int) $this->stock,
            ]);
        }
    }
}
