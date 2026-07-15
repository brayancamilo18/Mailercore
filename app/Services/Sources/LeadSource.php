<?php

namespace App\Services\Sources;

/**
 * Contrato común para fuentes de leads (Overpass, directorios, etc.).
 */
interface LeadSource
{
    /**
     * Clave de la fuente (coincide con config/outreach.php → sources).
     */
    public function key(): string;

    /**
     * Devuelve candidatos uniformes listos para dedup + verificación.
     *
     * @return iterable<int, LeadCandidate>
     */
    public function fetch(): iterable;
}
