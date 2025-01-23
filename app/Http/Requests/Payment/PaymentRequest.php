<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ajusta la lógica de autorización según tus necesidades
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|uuid|exists:orders,id',
            'payment_method' => 'required|in:credit_card,qr,cash_on_delivery',
            'provider' => 'nullable|string',
            'status' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string',
            'transaction_id' => 'nullable|string',
            'metadata' => 'nullable|array',
            'paid_at' => 'nullable|date'
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'El ID de la orden es obligatorio.',
            'order_id.uuid' => 'El ID de la orden debe ser un UUID válido.',
            'order_id.exists' => 'La orden no existe.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in' => 'El método de pago no es válido.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor o igual a 0.'
        ];
    }
}
