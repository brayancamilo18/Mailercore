<?php

namespace Database\Seeders;

use App\Models\HarvestArea;
use Illuminate\Database\Seeder;

/**
 * Siembra las 50 provincias + Ceuta y Melilla para el recorrido Overpass.
 *
 * Nombres alineados con etiquetas OSM habituales (name en español).
 * admin_level 6 = provincia; 4 = ciudad autónoma (Ceuta/Melilla).
 */
class HarvestAreaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->areas() as $area) {
            HarvestArea::query()->updateOrCreate(
                [
                    'name' => $area['name'],
                    'admin_level' => $area['admin_level'],
                ],
                [
                    'priority' => $area['priority'],
                    'status' => HarvestArea::STATUS_PENDIENTE,
                ]
            );
        }
    }

    /**
     * @return list<array{name: string, admin_level: int, priority: int}>
     */
    private function areas(): array
    {
        // Priority baja = se cosecha antes. Núcleos económicos primero.
        return [
            ['name' => 'Madrid', 'admin_level' => 6, 'priority' => 1],
            ['name' => 'Barcelona', 'admin_level' => 6, 'priority' => 2],
            ['name' => 'Valencia', 'admin_level' => 6, 'priority' => 3],
            ['name' => 'Sevilla', 'admin_level' => 6, 'priority' => 4],
            ['name' => 'Málaga', 'admin_level' => 6, 'priority' => 5],
            ['name' => 'Alicante', 'admin_level' => 6, 'priority' => 6],
            ['name' => 'Murcia', 'admin_level' => 6, 'priority' => 7],
            ['name' => 'Zaragoza', 'admin_level' => 6, 'priority' => 8],
            ['name' => 'Vizcaya', 'admin_level' => 6, 'priority' => 9],
            ['name' => 'A Coruña', 'admin_level' => 6, 'priority' => 10],
            ['name' => 'Las Palmas', 'admin_level' => 6, 'priority' => 11],
            ['name' => 'Santa Cruz de Tenerife', 'admin_level' => 6, 'priority' => 12],
            ['name' => 'Illes Balears', 'admin_level' => 6, 'priority' => 13],
            ['name' => 'Asturias', 'admin_level' => 6, 'priority' => 14],
            ['name' => 'Pontevedra', 'admin_level' => 6, 'priority' => 15],
            ['name' => 'Gipuzkoa', 'admin_level' => 6, 'priority' => 16],
            ['name' => 'Granada', 'admin_level' => 6, 'priority' => 17],
            ['name' => 'Tarragona', 'admin_level' => 6, 'priority' => 18],
            ['name' => 'Córdoba', 'admin_level' => 6, 'priority' => 19],
            ['name' => 'Girona', 'admin_level' => 6, 'priority' => 20],
            ['name' => 'Cádiz', 'admin_level' => 6, 'priority' => 21],
            ['name' => 'Toledo', 'admin_level' => 6, 'priority' => 22],
            ['name' => 'Badajoz', 'admin_level' => 6, 'priority' => 23],
            ['name' => 'Navarra', 'admin_level' => 6, 'priority' => 24],
            ['name' => 'Jaén', 'admin_level' => 6, 'priority' => 25],
            ['name' => 'Cantabria', 'admin_level' => 6, 'priority' => 26],
            ['name' => 'Castellón', 'admin_level' => 6, 'priority' => 27],
            ['name' => 'Valladolid', 'admin_level' => 6, 'priority' => 28],
            ['name' => 'Ciudad Real', 'admin_level' => 6, 'priority' => 29],
            ['name' => 'Huelva', 'admin_level' => 6, 'priority' => 30],
            ['name' => 'León', 'admin_level' => 6, 'priority' => 31],
            ['name' => 'Lleida', 'admin_level' => 6, 'priority' => 32],
            ['name' => 'Albacete', 'admin_level' => 6, 'priority' => 33],
            ['name' => 'Cáceres', 'admin_level' => 6, 'priority' => 34],
            ['name' => 'Burgos', 'admin_level' => 6, 'priority' => 35],
            ['name' => 'Salamanca', 'admin_level' => 6, 'priority' => 36],
            ['name' => 'Lugo', 'admin_level' => 6, 'priority' => 37],
            ['name' => 'Ourense', 'admin_level' => 6, 'priority' => 38],
            ['name' => 'Almería', 'admin_level' => 6, 'priority' => 39],
            ['name' => 'Guadalajara', 'admin_level' => 6, 'priority' => 40],
            ['name' => 'Araba/Álava', 'admin_level' => 6, 'priority' => 41],
            ['name' => 'Huesca', 'admin_level' => 6, 'priority' => 42],
            ['name' => 'Cuenca', 'admin_level' => 6, 'priority' => 43],
            ['name' => 'Zamora', 'admin_level' => 6, 'priority' => 44],
            ['name' => 'Ávila', 'admin_level' => 6, 'priority' => 45],
            ['name' => 'Segovia', 'admin_level' => 6, 'priority' => 46],
            ['name' => 'Teruel', 'admin_level' => 6, 'priority' => 47],
            ['name' => 'Soria', 'admin_level' => 6, 'priority' => 48],
            ['name' => 'Palencia', 'admin_level' => 6, 'priority' => 49],
            ['name' => 'La Rioja', 'admin_level' => 6, 'priority' => 50],
            ['name' => 'Ceuta', 'admin_level' => 4, 'priority' => 51],
            ['name' => 'Melilla', 'admin_level' => 4, 'priority' => 52],
        ];
    }
}
