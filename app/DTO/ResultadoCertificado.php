<?php

namespace App\DTO;

readonly class ResultadoCertificado
{
    /**
     * @param  bool|null  $cadenaValida  true si OpenSSL aceptó la cadena y el nombre
     * @param  bool|null  $noCaducado  true si validTo está en el futuro (cert_valido)
     */
    public function __construct(
        public ?bool $cadenaValida,
        public ?bool $noCaducado,
        public ?\DateTimeInterface $expiraAt,
    ) {}
}
