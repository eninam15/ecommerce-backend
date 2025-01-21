<?php
namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class ProductData extends DataTransferObject
{
    public string $category_id;
    public string $name;
    public ?string $description;
    public float $price;
    public int $stock;
    public bool $status;
    public ?array $attributes;
    public ?array $images; // Array de imágenes subidas
    public ?int $primary_image; // Índice de la imagen principal

    public static function fromRequest($request): self
    {
        // Procesar atributos dinámicos (attributes[])
        $attributes = [];
        foreach ($request->all() as $key => $value) {
            if (strpos($key, 'attributes[') === 0) {
                $attributeKey = str_replace(['attributes[', ']'], '', $key);
                $attributes[$attributeKey] = $value;
            }
        }

        return new self([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock' => $request->stock,
            'status' => $request->boolean('status', true), // Valor por defecto: true
            'attributes' => $attributes,
            'images' => $request->file('images', []), // Si no hay imágenes, devuelve un array vacío
            'primary_image' => $request->input('primary_image'), // Índice de la imagen principal
        ]);
    }
}
