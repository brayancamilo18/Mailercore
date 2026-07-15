<?php

namespace App\Services\Sources;

use App\Services\OverpassClient;

/**
 * Adaptador: expone OverpassClient como LeadSource sin romper su API.
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
        yield from $this->mapRows($this->client->search('filters'));

        if (! $this->includeNegocios) {
            return;
        }

        foreach ($this->client->search('filters_negocios') as $row) {
            // Negocios sin web propia: no son leads útiles para webs/tiendas online.
            $website = $row['website'] ?? null;

            if ($website === null || trim((string) $website) === '') {
                continue;
            }

            yield $this->toCandidate($row);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return iterable<int, LeadCandidate>
     */
    private function mapRows(array $rows): iterable
    {
        foreach ($rows as $row) {
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
