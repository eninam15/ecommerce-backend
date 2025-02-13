<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\Uuid;
use Illuminate\Support\Facades\Log;

class BulkProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // Log los errores de validación
        Log::error('Validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->all()
        ]);

        parent::failedValidation($validator);
    }

    protected function prepareForValidation()
    {
        // Log los datos recibidos antes de la validación
        Log::info('Request data received', [
            'data' => $this->all(),
            'headers' => $this->headers->all()
        ]);

        return parent::prepareForValidation();
    }

    public function rules()
    {
        // Log las reglas que se están aplicando
        Log::info('Applying validation rules');

        return [
            'products' => ['required', 'array', 'min:1'],
            'products.*.category_id' => [
                'required',
                new Uuid,
                'exists:categories,id'
            ],
            'products.*.code' => ['required', 'distinct', 'unique:products,code'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.description' => ['nullable', 'string'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
            'products.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.weight' => ['nullable', 'numeric', 'min:0'],
            'products.*.volume' => ['nullable', 'numeric', 'min:0'],
            'products.*.flavor' => ['nullable', 'string', 'max:255'],
            'products.*.presentation' => ['nullable', 'string', 'max:255'],
            'products.*.stock' => ['required', 'integer', 'min:0'],
            'products.*.min_stock' => ['nullable', 'integer', 'min:0'],
            'products.*.sku' => ['nullable', 'string', 'alpha_num'],
            'products.*.barcode' => ['nullable', 'string', 'max:255'],
            'products.*.status' => ['boolean'],
            'products.*.featured' => ['boolean'],
            'products.*.is_seasonal' => ['boolean'],
            'products.*.manufacture_date' => ['nullable', 'date'],
            'products.*.expiry_date' => ['nullable', 'date'],
            'products.*.nutritional_info' => ['nullable', 'array'],
            'products.*.ingredients' => ['nullable', 'array'],
        ];
    }

    public function messages()
    {
        return [
            'products.required' => 'El array de productos es requerido',
            'products.array' => 'Debe enviar un array de productos',
            'products.min' => 'Debe enviar al menos un producto',
            'products.*.category_id.required' => 'La categoría es requerida para cada producto',
            'products.*.category_id.exists' => 'La categoría seleccionada no existe',
            'products.*.code.required' => 'El código es requerido para cada producto',
            'products.*.code.unique' => 'El código ya existe en otro producto',
            // ... agregar más mensajes personalizados según necesites
        ];
    }
}
