<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class PsiSeoBajo implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'psi_seo';
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
        if ($auditoria === null || $auditoria->psi_seo === null) {
            return null;
        }

        $umbral = (int) config('outreach.auditoria.umbral_psi_seo');
        if ($auditoria->psi_seo >= $umbral) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'SEO PageSpeed bajo',
            "Puntuación SEO: {$auditoria->psi_seo}",
            ['puntuacion' => (int) $auditoria->psi_seo],
        );
    }
}
