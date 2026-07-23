<?php

namespace App\DTO;

readonly class ResultadoRastreo
{
    /**
     * @param  list<string>  $errores
     */
    public function __construct(
        public int $paginasVisitadas,
        public int $paginasGuardadas,
        public int $emailsGuardados,
        public int $emailsDescartados,
        public array $errores,
    ) {}
}
