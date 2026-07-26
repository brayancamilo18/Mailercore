<?php

namespace App\Support;

use App\Models\AreaCosecha;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MapaCosecha
{
    /**
     * Estados indexados por código de mapa (si existe) y por nombre normalizado,
     * para que el choropleth encuentre la provincia/departamento.
     *
     * @param  Collection<int, AreaCosecha>  $areas
     * @return array<string, string>
     */
    public static function statusesDesdeAreas(Collection $areas): array
    {
        $out = [];

        foreach ($areas as $area) {
            $estado = match ($area->estado) {
                'hecho' => 'hecho',
                'en_proceso' => 'proceso',
                'error' => 'error',
                default => 'pendiente',
            };

            if (filled($area->codigo_mapa)) {
                $out[(string) $area->codigo_mapa] = $estado;
            }

            $out[self::claveNombre((string) $area->nombre)] = $estado;
            $out[(string) $area->nombre] = $estado;
        }

        // España: también códigos INE para <spain-map>
        foreach (ProvinciasIne::statusesDesdeAreas($areas) as $codigo => $estado) {
            $out[$codigo] = $estado;
        }

        return $out;
    }

    public static function claveNombre(string $nombre): string
    {
        return Str::of($nombre)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();
    }
}
