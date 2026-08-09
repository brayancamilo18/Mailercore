<?php

namespace Database\Seeders;

use App\Models\AreaCosecha;
use App\Models\PaisCosecha;
use Illuminate\Database\Seeder;

class AreasCosechaSeeder extends Seeder
{
    /**
     * Siembra países + áreas desde resources/data/paises_cosecha.php.
     * Idempotente por (pais_codigo, nombre). Actualiza admin_level si cambia.
     */
    public function run(): void
    {
        /** @var array<string, array<string, mixed>> $catalogo */
        $catalogo = require resource_path('data/paises_cosecha.php');
        $maxCiclos = (int) config('outreach.cosecha.max_ciclos_pais', 3);
        $prioridadArea = 1;

        foreach ($catalogo as $codigo => $pais) {
            PaisCosecha::query()->updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $pais['nombre'],
                    'prioridad' => (int) $pais['prioridad'],
                    'max_ciclos' => $maxCiclos,
                    'mapa_motor' => $pais['mapa_motor'] ?? 'geojson',
                    'mapa_src' => $pais['mapa_src'] ?? null,
                ]
            );

            $adminDefault = (int) ($pais['admin_level'] ?? 4);
            /** @var array<string, int> $especiales */
            $especiales = $pais['admin_level_especial'] ?? [];
            /** @var list<string> $areas */
            $areas = $pais['areas'] ?? [];

            foreach ($areas as $nombre) {
                $adminLevel = $especiales[$nombre] ?? $adminDefault;

                $area = AreaCosecha::query()->firstOrNew([
                    'pais_codigo' => $codigo,
                    'nombre' => $nombre,
                ]);

                $nivelAnterior = $area->exists ? (int) $area->admin_level : null;

                $area->fill([
                    'admin_level' => $adminLevel,
                    'prioridad' => $prioridadArea,
                ]);

                if (! $area->exists) {
                    $area->estado = 'pendiente';
                }

                // Si el admin_level OSM cambia, hay que volver a cosechar el área.
                if ($nivelAnterior !== null && $nivelAnterior !== $adminLevel) {
                    $area->estado = 'pendiente';
                    $area->ultimo_error = null;
                }

                $area->save();
                $prioridadArea++;
            }
        }
    }
}
