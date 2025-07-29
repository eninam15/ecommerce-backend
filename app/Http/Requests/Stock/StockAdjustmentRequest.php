<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize()
    {
        return auth()->user()->hasRole('admin');
    }

    public function rules()
    {
        return [
            'new_stock' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
            'force' => ['boolean'] // Para forzar ajustes que puedan causar stock negativo
        ];
    }

    public function messages()
    {
        return [
            'new_stock.required' => 'El nuevo stock es requerido',
            'new_stock.integer' => 'El stock debe ser un número entero',
            'new_stock.min' => 'El stock no puede ser negativo',
            'reason.max' => 'La razón no puede exceder 500 caracteres'
        ];
    }
}