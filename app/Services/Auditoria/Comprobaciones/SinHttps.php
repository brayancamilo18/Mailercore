<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinHttps implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'sin_https';
    }

    public function peso(): int
    {
        return 30;
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

        if ($home->es_https === false || $home->cert_valido === false) {
            $detalle = $home->es_https === false
                ? 'La home no usa HTTPS'
                : 'El certificado SSL no es válido';

            return new Hallazgo(
                $this->codigo(),
                $this->peso(),
                'Sin HTTPS seguro',
                $detalle,
            );
        }

        return null;
    }
}
