<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'id')->whereNull('deleted_at')
            ],
            'status' => ['boolean']
        ];
    }
}
