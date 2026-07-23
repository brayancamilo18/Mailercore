<?php

namespace App\DTO;

readonly class Hallazgo
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        public string $codigo,
        public int $peso,
        public string $titulo,
        public string $detalle,
        public array $datos = [],
    ) {}
}
