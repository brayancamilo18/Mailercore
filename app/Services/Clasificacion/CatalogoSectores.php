<?php

namespace App\Services\Clasificacion;

class CatalogoSectores
{
    /** @var array<string, array<string, string>>|null */
    private static ?array $subsectoresCache = null;

    /**
     * @return array<string, array{etiqueta: string, prioridad: int, plantilla: string, tags: array, schema: array}>
     */
    public function familias(): array
    {
        $familias = config('sectores');

        uasort($familias, fn (array $a, array $b): int => $a['prioridad'] <=> $b['prioridad']);

        return $familias;
    }

    /**
     * @return list<string> claves de familia ordenadas por prioridad
     */
    public function claves(): array
    {
        return array_keys($this->familias());
    }

    /** Familia que corresponde a un par tag/valor concreto. */
    public function porTag(string $tag, string $valor): ?string
    {
        foreach ($this->familias() as $clave => $familia) {
            foreach ($familia['tags'] as [$t, $v]) {
                if ($t === $tag && $v === $valor) {
                    return $clave;
                }
            }
        }

        return null;
    }

    /**
     * Recorre TODOS los tags de un elemento OSM y devuelve la familia de mayor
     * prioridad (número menor) que coincida.
     *
     * @param  array<string, string>  $tags
     */
    public function porTagsRaw(array $tags): ?string
    {
        foreach ($this->familias() as $clave => $familia) {
            foreach ($familia['tags'] as [$t, $v]) {
                if (isset($tags[$t]) && $tags[$t] === $v) {
                    return $clave;
                }
            }
        }

        return null;
    }

    /** Familia según un tipo de schema.org. */
    public function porTipoSchema(string $tipo): ?string
    {
        $tipoLower = strtolower($tipo);

        foreach ($this->familias() as $clave => $familia) {
            foreach ($familia['schema'] as $schema) {
                if (strtolower($schema) === $tipoLower) {
                    return $clave;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tipos
     */
    public function porTiposSchema(array $tipos): ?string
    {
        $tiposLower = array_map('strtolower', $tipos);

        foreach ($this->familias() as $clave => $familia) {
            foreach ($familia['schema'] as $schema) {
                if (in_array(strtolower($schema), $tiposLower, true)) {
                    return $clave;
                }
            }
        }

        return null;
    }

    /** Etiqueta legible del subsector, p. ej. "Clínica dental". */
    public function subsector(string $tag, string $valor): ?string
    {
        $mapa = self::$subsectoresCache ??= require resource_path('data/subsectores.php');

        return $mapa[$tag][$valor] ?? null;
    }

    public function etiqueta(string $familia): ?string
    {
        return config("sectores.{$familia}.etiqueta");
    }

    public function plantilla(string $familia): ?string
    {
        return config("sectores.{$familia}.plantilla");
    }

    public function existe(string $familia): bool
    {
        return config("sectores.{$familia}") !== null;
    }
}
