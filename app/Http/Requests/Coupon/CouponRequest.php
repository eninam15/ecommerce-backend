<?php

namespace App\Http\Requests\Coupon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\CouponType;

class CouponRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->hasRole('admin');
    }

    public function rules()
    {
        $couponId = $this->route('coupon');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('coupons', 'code')->ignore($couponId)->whereNull('deleted_at')
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::enum(CouponType::class)],
            'discount_value' => [
                'required_unless:type,free_shipping',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($this->type === 'percentage' && $value > 100) {
                        $fail('El descuento por porcentaje no puede ser mayor a 100%');
                    }
                }
            ],
            'minimum_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount' => [
                'nullable',
                'numeric',
                'min:0',
                'required_if:type,percentage'
            ],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'first_purchase_only' => ['boolean'],
            'status' => ['boolean'],
            'starts_at' => ['nullable', 'date', 'before_or_equal:expires_at'],
            'expires_at' => ['nullable', 'date', 'after:starts_at'],
            
            // Asociaciones
            'category_ids' => [
                'nullable',
                'array',
                'required_if:type,category_discount'
            ],
            'category_ids.*' => ['exists:categories,id'],
            'product_ids' => [
                'nullable',
                'array',
                'required_if:type,product_discount'
            ],
            'product_ids.*' => ['exists:products,id']
        ];
    }

    public function messages()
    {
        return [
            'code.required' => 'El código del cupón es requerido',
            'code.unique' => 'Este código ya existe',
            'code.regex' => 'El código solo puede contener letras mayúsculas y números',
            'name.required' => 'El nombre del cupón es requerido',
            'type.required' => 'El tipo de cupón es requerido',
            'discount_value.required_unless' => 'El valor del descuento es requerido',
            'maximum_discount.required_if' => 'El descuento máximo es requerido para cupones de porcentaje',
            'category_ids.required_if' => 'Debe seleccionar al menos una categoría para cupones de categoría',
            'product_ids.required_if' => 'Debe seleccionar al menos un producto para cupones de producto',
            'starts_at.before_or_equal' => 'La fecha de inicio debe ser anterior a la fecha de expiración',
            'expires_at.after' => 'La fecha de expiración debe ser posterior a la fecha de inicio'
        ];
    }

    protected function prepareForValidation()
    {
        // Convertir código a mayúsculas
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->code)
            ]);
        }

        // Limpiar campos según el tipo
        if ($this->type === 'free_shipping') {
            $this->merge([
                'discount_value' => null,
                'maximum_discount' => null
            ]);
        }
    }
}

// ===== REQUEST PARA APLICAR CUPÓN =====

class ApplyCouponRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->check();
    }

    public function rules()
    {
        return [
            'coupon_code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9]+$/'
            ]
        ];
    }

    public function messages()
    {
        return [
            'coupon_code.required' => 'El código del cupón es requerido',
            'coupon_code.regex' => 'Formato de código inválido'
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('coupon_code')) {
            $this->merge([
                'coupon_code' => strtoupper($this->coupon_code)
            ]);
        }
    }
}

// ===== REQUEST PARA FILTROS DE CUPONES =====

class CouponFilterRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->hasRole('admin');
    }

    public function rules()
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::enum(CouponType::class)],
            'status' => ['nullable', 'boolean'],
            'expired' => ['nullable', 'boolean'],
            'exhausted' => ['nullable', 'boolean'],
            'starts_after' => ['nullable', 'date'],
            'starts_before' => ['nullable', 'date'],
            'expires_after' => ['nullable', 'date'],
            'expires_before' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100']
        ];
    }
}