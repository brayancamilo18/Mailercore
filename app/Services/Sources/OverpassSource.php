<?php

namespace App\Services\Sources;

use App\Services\OverpassClient;

/**
 * Adaptador: expone OverpassClient como LeadSource sin romper su API.
 * Emite candidatos en streaming (filtro a filtro) para ir mostrando leads al vuelo.
 */
class OverpassSource implements LeadSource
{
    public function __construct(
        private OverpassClient $client,
        private bool $includeNegocios = false,
    ) {
    }

    public function key(): string
    {
        return 'overpass';
    }

    /**
     * @return iterable<int, LeadCandidate>
     */
    public function fetch(): iterable
    {
        foreach ($this->client->searchStream('filters') as $row) {
            yield $this->toCandidate($row);
        }

        if (! $this->includeNegocios) {
            return;
        }

        foreach ($this->client->searchStream('filters_negocios') as $row) {
            // Negocios sin web propia: no son leads útiles para webs/tiendas online.
            $website = $row['website'] ?? null;

            if ($website === null || trim((string) $website) === '') {
                continue;
            }

            yield $this->toCandidate($row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toCandidate(array $row): LeadCandidate
    {
        return new LeadCandidate(
            name: $row['name'],
            website: $row['website'] ?? null,
            source: $this->key(),
            phone: $row['phone'] ?? null,
            email: $row['email'] ?? null,
            address: $row['address'] ?? null,
            externalId: $row['place_id'] ?? null,
            segmento: $row['segmento'] ?? 'agencia',
        );
    }
}
