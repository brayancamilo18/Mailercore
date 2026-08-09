<?php

namespace App\DTO;

use App\Enums\PropositoRastreo;

readonly class ResultadoRastreo
{
    /**
     * @param  list<string>  $errores
     * @param  list<string>  $rutasOmitidas  rutas que nunca se pidieron, en formato "ruta: motivo"
     * @param  string|null  $falloTipo  motivo por el que el rastreo no sirve: 'dns', 'tls',
     *                                  'timeout', 'http', 'robots' o 'guardia'. null si la home se obtuvo.
     */
    public function __construct(
        public int $paginasVisitadas,
        public int $paginasGuardadas,
        public int $emailsGuardados,
        public int $emailsDescartados,
        public array $errores,
        public PropositoRastreo $proposito = PropositoRastreo::COMPLETO,
        public array $rutasOmitidas = [],
        public ?string $falloTipo = null,
    ) {}

    /** El rastreo no consiguió la home, así que no dice nada de la web. */
    public function fallido(): bool
    {
        return $this->falloTipo !== null;
    }
}
