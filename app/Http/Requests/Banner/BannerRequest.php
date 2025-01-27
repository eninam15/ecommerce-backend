<?php

namespace App\Http\Requests\Banner;

use Illuminate\Foundation\Http\FormRequest;

class BannerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link' => 'nullable|url',
            'text_button' => 'nullable|string|max:50'
        ];

        // Reglas adicionales para crear vs actualizar
        if ($this->isMethod('post')) {
            $rules['image'] = 'required|' . $rules['image'];
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'title.required' => 'El título es obligatorio',
            'image.required' => 'La imagen es obligatoria',
            'image.image' => 'El archivo debe ser una imagen',
            'link.url' => 'El enlace debe ser una URL válida'
        ];
    }
}