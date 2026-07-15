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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'website' => $this->website,
            'source' => $this->source,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'externalId' => $this->externalId,
            'segmento' => $this->segmento,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            website: isset($data['website']) ? (string) $data['website'] : null,
            source: (string) ($data['source'] ?? 'overpass'),
            phone: isset($data['phone']) ? (string) $data['phone'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            address: isset($data['address']) ? (string) $data['address'] : null,
            externalId: isset($data['externalId']) ? (string) $data['externalId'] : null,
            segmento: (string) ($data['segmento'] ?? 'agencia'),
        );
    }
}
