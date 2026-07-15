<?php

namespace App\Services\Sources;

/**
 * DTO uniforme que todas las fuentes de leads deben producir.
 */
readonly class LeadCandidate
{
    public function __construct(
        public string $name,
        public ?string $website,
        public string $source,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $address = null,
        /** Identificador externo de la fuente (p. ej. place_id OSM). */
        public ?string $externalId = null,
        /** Segmento de captación: agencia | negocio. */
        public string $segmento = 'agencia',
    ) {
    }
}
