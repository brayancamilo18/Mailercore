<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class PsiAccesibilidadBaja implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'psi_accesibilidad';
    }

    public function peso(): int
    {
        return 12;
    }

    public function sectores(): ?array
    {
        return null;
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        if ($auditoria === null || $auditoria->psi_accesibilidad === null) {
            return null;
        }

        if ($auditoria->psi_accesibilidad >= 70) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Accesibilidad baja',
            "Puntuación de accesibilidad: {$auditoria->psi_accesibilidad}",
            ['puntuacion' => (int) $auditoria->psi_accesibilidad],
        );
    }
}
