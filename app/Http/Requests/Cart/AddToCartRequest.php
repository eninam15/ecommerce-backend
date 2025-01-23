<?php

namespace App\Http\Requests\Cart;
use App\Rules\Uuid;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartrequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => [
                'required',
                new Uuid,
                'exists:products,id'
            ],
            'quantity' => ['required', 'integer', 'min:0']
        ];
    }
}
