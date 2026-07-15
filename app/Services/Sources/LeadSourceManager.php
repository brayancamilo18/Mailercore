<?php

namespace App\Services\Sources;

use App\Services\OverpassClient;
use InvalidArgumentException;

/**
 * Resuelve las fuentes activas definidas en config/outreach.php → sources.
 */
class LeadSourceManager
{
    /**
     * @param  array{negocios?: bool}  $options
     * @return list<LeadSource>
     */
    public function active(array $options = []): array
    {
        $sources = [];

        foreach (config('outreach.sources', []) as $key => $definition) {
            if (! ($definition['enabled'] ?? false)) {
                continue;
            }

            $sources[] = $this->resolve(
                (string) $key,
                is_array($definition) ? $definition : [],
                $options
            );
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{negocios?: bool}  $options
     */
    private function resolve(string $key, array $definition, array $options = []): LeadSource
    {
        return match ($key) {
            'overpass' => new OverpassSource(
                new OverpassClient(config('outreach.overpass')),
                includeNegocios: (bool) ($options['negocios'] ?? false),
            ),
            'association_directory' => new AssociationDirectorySource($definition),
            default => throw new InvalidArgumentException(
                "Fuente de leads desconocida: {$key}. Añádela al LeadSourceManager."
            ),
        };
    }
}
