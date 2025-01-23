<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'shipping_address_id' => ['nullable', 'uuid', 'exists:shipping_addresses,id'],
            'order_number' => ['required', 'string', 'unique:orders,order_number'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax' => ['required', 'numeric', 'min:0'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(['pending', 'paid', 'shipped', 'cancelled', 'completed'])], // Puedes ajustar los valores posibles
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
