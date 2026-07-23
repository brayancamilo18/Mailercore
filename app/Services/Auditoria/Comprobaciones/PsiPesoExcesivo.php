<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class PsiPesoExcesivo implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'psi_peso';
    }

    public function peso(): int
    {
        return 20;
    }

    public function sectores(): ?array
    {
        return null;
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        if ($auditoria === null || $auditoria->psi_peso_kb === null) {
            return null;
        }

        if ($auditoria->psi_peso_kb <= 3000) {
            return null;
        }

        $kb = (int) $auditoria->psi_peso_kb;

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Peso de página excesivo',
            'La página pesa '.round($kb / 1024, 1).' MB',
            ['mb' => round($kb / 1024, 1)],
        );
    }
}
