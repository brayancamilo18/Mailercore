<?php

namespace App\DTO;

readonly class ResultadoClasificacion
{
    public function __construct(
        public ?string $sector,
        public ?string $subsector,
        public string $metodo,
        public int $confianza,
    ) {}
}
