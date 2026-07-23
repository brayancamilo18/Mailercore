<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class PsiRendimientoMalo implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'psi_rendimiento';
    }

    public function peso(): int
    {
        return 35;
    }

    public function sectores(): ?array
    {
        return null;
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        if ($auditoria === null || $auditoria->psi_rendimiento === null) {
            return null;
        }

        $umbral = (int) config('outreach.auditoria.umbral_psi_rendimiento');
        if ($auditoria->psi_rendimiento >= $umbral) {
            return null;
        }

        $n = (int) $auditoria->psi_rendimiento;

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Rendimiento PageSpeed bajo',
            "Puntuación de rendimiento: {$n}",
            ['puntuacion' => $n],
        );
    }
}
