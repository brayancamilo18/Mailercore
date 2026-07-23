<?php

namespace App\DTO;

readonly class ItemBandeja
{
    public function __construct(
        public string $id,
        public MensajeEntrante $entrante,
    ) {}
}
