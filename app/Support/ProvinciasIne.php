<?php

namespace App\Support;

/**
 * Catálogo INE ↔ áreas de cosecha (mapa choropleth).
 */
final class ProvinciasIne
{
    /** @var list<array{codigo:string, nombre:string, etiqueta:string}>|null */
    private static ?array $filas = null;

    /**
     * @return list<array{codigo:string, nombre:string, etiqueta:string}>
     */
    public static function todas(): array
    {
        return self::$filas ??= require resource_path('data/provincias_ine.php');
    }

    /** @return array<string, string> nombre seeder → código INE */
    public static function nombreACodigo(): array
    {
        $mapa = [];
        foreach (self::todas() as $fila) {
            $mapa[$fila['nombre']] = $fila['codigo'];
        }

        return $mapa;
    }

    /** @return array<string, string> código INE → etiqueta visual */
    public static function codigoAEtiqueta(): array
    {
        $mapa = [];
        foreach (self::todas() as $fila) {
            $mapa[$fila['codigo']] = $fila['etiqueta'];
        }

        return $mapa;
    }

    public static function codigoDeNombre(string $nombre): ?string
    {
        return self::nombreACodigo()[$nombre] ?? null;
    }

    /**
     * Estados del mapa: claves INE, valores hecho|proceso|error|pendiente.
     *
     * @param  iterable<int, object{nombre:string, estado:string}>  $areas
     * @return array<string, string>
     */
    public static function statusesDesdeAreas(iterable $areas): array
    {
        $mapa = [];
        foreach ($areas as $area) {
            $codigo = self::codigoDeNombre((string) $area->nombre);
            if ($codigo === null) {
                continue;
            }
            $mapa[$codigo] = $area->estado === 'en_proceso' ? 'proceso' : (string) $area->estado;
        }

        return $mapa;
    }
}
