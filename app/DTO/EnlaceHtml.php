<?php

namespace App\DTO;

readonly class EnlaceHtml
{
    /**
     * @param  string  $href  valor crudo del atributo, sin espacios sobrantes
     * @param  string  $texto  texto ancla con los espacios colapsados
     * @param  string|null  $ruta  ruta normalizada si el enlace es interno y usable
     */
    public function __construct(
        public string $href,
        public string $texto,
        public bool $esInterno,
        public ?string $ruta,
    ) {}
}
