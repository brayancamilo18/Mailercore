<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinAvisoLegal implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'sin_aviso_legal';
    }

    public function peso(): int
    {
        return 15;
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

        $ok = $paginas->contains(fn ($p) => $p->tiene_aviso_legal === true)
            || $paginas->contains(fn ($p) => $p->tiene_privacidad === true);

        if ($ok) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin aviso legal',
            'No hay aviso legal ni política de privacidad',
        );
    }
}
