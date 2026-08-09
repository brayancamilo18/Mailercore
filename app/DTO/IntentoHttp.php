<?php

namespace App\DTO;

use Illuminate\Http\Client\Response;

/**
 * Resultado de pedir una URL, ya con los reintentos consumidos.
 */
readonly class IntentoHttp
{
    public function __construct(
        public ?Response $respuesta,
        public int $ms,
        public int $intentos,
        public ?string $falloTipo,
        public ?string $mensaje,
    ) {}

    /** Hubo respuesta del servidor, sea cual sea su código. */
    public function huboRespuesta(): bool
    {
        return $this->respuesta !== null;
    }
}
