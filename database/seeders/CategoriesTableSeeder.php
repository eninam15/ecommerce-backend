<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'id' => Str::uuid(),
                'parent_id' => null,
                'name' => 'Línea Lácteos',
                'slug' => 'linea-lacteos',
                'description' => 'Productos derivados de la leche.',
                'status' => true,
            ],
            [
                'id' => Str::uuid(),
                'parent_id' => null,
                'name' => 'Línea Apícola',
                'slug' => 'linea-apicola',
                'description' => 'Productos relacionados con la apicultura.',
                'status' => true,
            ],
            [
                'id' => Str::uuid(),
                'parent_id' => null,
                'name' => 'Línea Frutícolas',
                'slug' => 'linea-fruticolas',
                'description' => 'Productos derivados de frutas.',
                'status' => true,
            ],
            [
                'id' => Str::uuid(),
                'parent_id' => null,
                'name' => 'Línea Amazónicos',
                'slug' => 'linea-amazonicos',
                'description' => 'Productos provenientes de la región amazónica.',
                'status' => true,
            ],
            [
                'id' => Str::uuid(),
                'parent_id' => null,
                'name' => 'Línea Derivados',
                'slug' => 'linea-derivados',
                'description' => 'Diversos productos derivados.',
                'status' => true,
            ],
            [
                'id' => Str::uuid(),
                'parent_id' => null,
                'name' => 'Línea Estevia',
                'slug' => 'linea-estevia',
                'description' => 'Productos relacionados con la estevia.',
                'status' => true,
            ],
        ];

        // Insertar las categorías en la base de datos
        DB::table('categories')->insert($categories);
    }
}
