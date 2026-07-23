<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinCookies implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'sin_cookies';
    }

    public function peso(): int
    {
        return 8;
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

        if ($paginas->contains(fn ($p) => $p->tiene_cookies === true)) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin política de cookies',
            'No se encontró aviso de cookies',
        );
    }
}
