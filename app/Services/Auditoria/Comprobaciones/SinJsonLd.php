<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinJsonLd implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'sin_jsonld';
    }

    public function peso(): int
    {
        return 12;
    }

    public function sectores(): ?array
    {
        return null;
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null) {
            return null;
        }

        $alguna = $paginas->contains(fn ($p) => $p->tiene_jsonld === true);
        if ($alguna) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin datos estructurados',
            'Ninguna página incluye JSON-LD',
        );
    }
}
