<?php

namespace App\DTO;

readonly class ResultadoPageSpeed
{
    public function __construct(
        public ?int $rendimiento,
        public ?int $seo,
        public ?int $accesibilidad,
        public ?int $buenasPracticas,
        public ?int $lcpMs,
        public ?float $cls,
        public ?int $tbtMs,
        public ?int $pesoKb,
    ) {}
}
