<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinReservas implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'sin_reservas';
    }

    public function peso(): int
    {
        return 22;
    }

    public function sectores(): ?array
    {
        return ['hosteleria', 'salud', 'belleza'];
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null) {
            return null;
        }

        if ($paginas->contains(fn ($p) => $p->tiene_reservas === true)) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin sistema de reservas',
            'No se detectó sistema de reservas online',
        );
    }
}
