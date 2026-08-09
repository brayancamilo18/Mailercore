<?php

namespace App\DTO;

use App\Enums\Certeza;

readonly class Hallazgo
{
    /**
     * @param  Certeza  $certeza  cuánto se puede defender la afirmación
     * @param  array<string, mixed>  $datos
     * @param  string|null  $evidencia  en qué nos basamos, para poder auditar el correo
     */
    public function __construct(
        public string $codigo,
        public int $peso,
        public string $titulo,
        public string $detalle,
        public Certeza $certeza,
        public array $datos = [],
        public ?string $evidencia = null,
    ) {}
}
