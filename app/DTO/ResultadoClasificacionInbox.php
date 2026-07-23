<?php

namespace App\DTO;

readonly class ResultadoClasificacionInbox
{
    public function __construct(
        public string $tipo,
        public ?string $emailAfectado,
        public ?string $codigoSmtp,
        public ?int $mensajeId,
        public string $extracto,
    ) {}
}
