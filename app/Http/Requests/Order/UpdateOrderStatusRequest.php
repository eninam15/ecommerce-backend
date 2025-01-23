<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\OrderStatus;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => [
                'required',
                Rule::in(OrderStatus::values()),
            ],
            'comment' => [
                'nullable',
                'string',
                'max:500', // Limita la longitud del comentario
            ],
        ];
    }
}
