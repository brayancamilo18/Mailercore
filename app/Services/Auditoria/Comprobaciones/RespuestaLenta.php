<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class RespuestaLenta implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'respuesta_lenta';
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
        if ($home === null || $home->respuesta_ms === null) {
            return null;
        }

        $umbral = (int) config('outreach.auditoria.umbral_respuesta_ms');
        if ($home->respuesta_ms <= $umbral) {
            return null;
        }

        $ms = (int) $home->respuesta_ms;

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Respuesta lenta',
            "La home tarda {$ms} ms",
            ['ms' => $ms, 'segundos' => round($ms / 1000, 1)],
        );
    }
}
