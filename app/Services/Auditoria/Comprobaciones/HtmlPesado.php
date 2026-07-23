<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class HtmlPesado implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'html_pesado';
    }

    public function peso(): int
    {
        return 10;
    }

    public function sectores(): ?array
    {
        return null;
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null || $home->bytes === null) {
            return null;
        }

        $umbralBytes = (int) config('outreach.auditoria.umbral_html_kb') * 1024;
        if ($home->bytes <= $umbralBytes) {
            return null;
        }

        $kb = (int) round($home->bytes / 1024);

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'HTML demasiado pesado',
            "La home pesa {$kb} KB",
            ['kb' => $kb],
        );
    }
}
