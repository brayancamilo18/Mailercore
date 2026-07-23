<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class H1Incorrecto implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'h1_incorrecto';
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

        $total = (int) ($home->h1_total ?? 0);
        if ($total === 0 || $total > 1) {
            return new Hallazgo(
                $this->codigo(),
                $this->peso(),
                'H1 incorrecto',
                $total === 0 ? 'No hay H1' : "Hay {$total} H1",
                ['total' => $total],
            );
        }

        return null;
    }
}
