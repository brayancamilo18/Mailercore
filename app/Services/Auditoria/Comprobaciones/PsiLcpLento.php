<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class PsiLcpLento implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'psi_lcp';
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
        if ($auditoria === null || $auditoria->psi_lcp_ms === null) {
            return null;
        }

        $umbral = (int) config('outreach.auditoria.umbral_lcp_ms');
        if ($auditoria->psi_lcp_ms <= $umbral) {
            return null;
        }

        $ms = (int) $auditoria->psi_lcp_ms;

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'LCP demasiado lento',
            "LCP de {$ms} ms",
            ['ms' => $ms, 'segundos' => round($ms / 1000, 1)],
        );
    }
}
