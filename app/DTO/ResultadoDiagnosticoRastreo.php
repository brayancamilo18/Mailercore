<?php

namespace App\DTO;

use App\Models\Pagina;
use Illuminate\Support\Collection;

/**
 * Resultado de un rastreo en seco: mismas decisiones que el real, sin tocar BD.
 *
 * @phpstan-type Visita array{
 *     ruta: string,
 *     origen: string,
 *     http_status: int|null,
 *     ms: int|null,
 *     bytes: int|null,
 *     truncada: bool|null,
 *     fallo_tipo: string|null,
 *     nota: string|null
 * }
 */
readonly class ResultadoDiagnosticoRastreo
{
    /**
     * @param  list<string>  $rutasFijas
     * @param  list<string>  $rutasDescubiertas
     * @param  list<Visita>  $visitas
     * @param  Collection<int, Pagina>  $paginas
     * @param  list<Hallazgo>  $hallazgos
     * @param  list<string>  $rutasOmitidas
     */
    public function __construct(
        public ResultadoRastreo $resultado,
        public array $rutasFijas,
        public array $rutasDescubiertas,
        public array $visitas,
        public Collection $paginas,
        public array $hallazgos,
        public array $rutasOmitidas,
    ) {}
}
