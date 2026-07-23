<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class WebAbandonada implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'web_abandonada';
    }

    public function peso(): int
    {
        return 18;
    }

    public function sectores(): ?array
    {
        return null;
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null || $home->anio_copyright === null) {
            return null;
        }

        $anios = (int) config('outreach.auditoria.anios_web_abandonada');
        $umbral = (int) now()->year - $anios;

        if ($home->anio_copyright <= $umbral) {
            return new Hallazgo(
                $this->codigo(),
                $this->peso(),
                'Web abandonada',
                "Copyright del año {$home->anio_copyright}",
                ['anio' => $home->anio_copyright],
            );
        }

        return null;
    }
}
