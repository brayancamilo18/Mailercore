<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinRedesSociales implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'sin_redes';
    }

    public function peso(): int
    {
        return 6;
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

        $alguna = $paginas->contains(function ($p): bool {
            return is_array($p->redes_sociales) && $p->redes_sociales !== [];
        });

        if ($alguna) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin redes sociales',
            'No se encontraron enlaces a redes sociales',
        );
    }
}
