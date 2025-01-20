<?php

namespace App\Http\Requests;
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'status' => ['boolean'],
            'attributes' => ['nullable', 'array'],
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