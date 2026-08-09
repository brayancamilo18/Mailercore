<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\Models\Pagina;
use Illuminate\Support\Collection;

/**
 * Frase de evidencia para las comprobaciones que afirman una ausencia. Va en el
 * informe que lee el cliente, así que nombra las páginas donde se buscó en vez
 * de dar un número: «buscado en /, /carta, /reservas».
 */
trait DescribeCobertura
{
    /**
     * @param  Collection<int, Pagina>  $paginas
     */
    private function cobertura(Collection $paginas): string
    {
        // Más allá de unas pocas, la lista deja de leerse y pasa a ser un muro.
        $maximoNombradas = 6;

        // La home primero y el resto en orden alfabético: el informe se lee
        // igual cada vez, venga como venga la consulta.
        $rutas = $paginas
            ->filter(fn (Pagina $pagina): bool => $pagina->aportaCobertura())
            ->map(fn (Pagina $pagina): string => $this->nombreRuta($pagina))
            ->unique()
            ->sort(fn (string $a, string $b): int => match (true) {
                $a === '/' => -1,
                $b === '/' => 1,
                default => strcmp($a, $b),
            })
            ->values();

        if ($rutas->isEmpty()) {
            return 'sin páginas donde buscar';
        }

        if ($rutas->count() <= $maximoNombradas) {
            return 'buscado en '.$rutas->implode(', ');
        }

        $restantes = $rutas->count() - $maximoNombradas;

        return 'buscado en '.$rutas->take($maximoNombradas)->implode(', ')
            .' y '.$restantes.($restantes === 1 ? ' página más' : ' páginas más');
    }

    private function nombreRuta(Pagina $pagina): string
    {
        $ruta = trim((string) $pagina->ruta);

        return $ruta === '' ? '/' : $ruta;
    }
}
