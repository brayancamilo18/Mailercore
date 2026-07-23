<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreasCosechaSeeder extends Seeder
{
    /**
     * Siembra las 50 provincias españolas (admin_level = 6) + las ciudades
     * autónomas de Ceuta y Melilla (admin_level = 4).
     * Idempotente por (nombre, admin_level).
     */
    public function run(): void
    {
        // Ceuta y Melilla son ciudades autónomas: en OSM su límite es admin_level 4.
        $adminLevelEspecial = [
            'Ceuta' => 4,
            'Melilla' => 4,
        ];

        $areas = [
            ['nombre' => 'Madrid', 'prioridad' => 1],
            ['nombre' => 'Barcelona', 'prioridad' => 2],
            ['nombre' => 'Valencia', 'prioridad' => 3],
            ['nombre' => 'Sevilla', 'prioridad' => 4],
            ['nombre' => 'Málaga', 'prioridad' => 5],
            ['nombre' => 'Alicante', 'prioridad' => 6],
            ['nombre' => 'Vizcaya', 'prioridad' => 7],
            ['nombre' => 'Zaragoza', 'prioridad' => 8],
            ['nombre' => 'Murcia', 'prioridad' => 9],
            ['nombre' => 'Baleares', 'prioridad' => 10],
            ['nombre' => 'Las Palmas', 'prioridad' => 11],
            ['nombre' => 'Santa Cruz de Tenerife', 'prioridad' => 12],
            ['nombre' => 'A Coruña', 'prioridad' => 13],
            ['nombre' => 'Asturias', 'prioridad' => 14],
            ['nombre' => 'Cádiz', 'prioridad' => 15],
            ['nombre' => 'Pontevedra', 'prioridad' => 16],
            ['nombre' => 'Granada', 'prioridad' => 17],
            ['nombre' => 'Guipúzcoa', 'prioridad' => 18],
            ['nombre' => 'Tarragona', 'prioridad' => 19],
            ['nombre' => 'Girona', 'prioridad' => 20],
            // Resto por orden alfabético (prioridad 21+)
            ['nombre' => 'Álava', 'prioridad' => 21],
            ['nombre' => 'Albacete', 'prioridad' => 22],
            ['nombre' => 'Almería', 'prioridad' => 23],
            ['nombre' => 'Ávila', 'prioridad' => 24],
            ['nombre' => 'Badajoz', 'prioridad' => 25],
            ['nombre' => 'Burgos', 'prioridad' => 26],
            ['nombre' => 'Cáceres', 'prioridad' => 27],
            ['nombre' => 'Cantabria', 'prioridad' => 28],
            ['nombre' => 'Castellón', 'prioridad' => 29],
            ['nombre' => 'Ceuta', 'prioridad' => 30],
            ['nombre' => 'Ciudad Real', 'prioridad' => 31],
            ['nombre' => 'Córdoba', 'prioridad' => 32],
            ['nombre' => 'Cuenca', 'prioridad' => 33],
            ['nombre' => 'Guadalajara', 'prioridad' => 34],
            ['nombre' => 'Huelva', 'prioridad' => 35],
            ['nombre' => 'Huesca', 'prioridad' => 36],
            ['nombre' => 'Jaén', 'prioridad' => 37],
            ['nombre' => 'La Rioja', 'prioridad' => 38],
            ['nombre' => 'León', 'prioridad' => 39],
            ['nombre' => 'Lleida', 'prioridad' => 40],
            ['nombre' => 'Lugo', 'prioridad' => 41],
            ['nombre' => 'Melilla', 'prioridad' => 42],
            ['nombre' => 'Navarra', 'prioridad' => 43],
            ['nombre' => 'Ourense', 'prioridad' => 44],
            ['nombre' => 'Palencia', 'prioridad' => 45],
            ['nombre' => 'Salamanca', 'prioridad' => 46],
            ['nombre' => 'Segovia', 'prioridad' => 47],
            ['nombre' => 'Soria', 'prioridad' => 48],
            ['nombre' => 'Teruel', 'prioridad' => 49],
            ['nombre' => 'Toledo', 'prioridad' => 50],
            ['nombre' => 'Valladolid', 'prioridad' => 51],
            ['nombre' => 'Zamora', 'prioridad' => 52],
        ];

        $ahora = now();

        foreach ($areas as $area) {
            $adminLevel = $adminLevelEspecial[$area['nombre']] ?? 6;

            // Equivalente a updateOrCreate (aún no hay modelo Eloquent).
            DB::table('areas_cosecha')->updateOrInsert(
                [
                    'nombre' => $area['nombre'],
                    'admin_level' => $adminLevel,
                ],
                [
                    'prioridad' => $area['prioridad'],
                    'updated_at' => $ahora,
                    'created_at' => $ahora,
                ]
            );
        }
    }
}
