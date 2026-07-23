<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class CertificadoCaduca implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'cert_caduca';
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
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null || $home->cert_expira_at === null) {
            return null;
        }

        $limite = now()->addDays(30);
        if ($home->cert_expira_at->greaterThanOrEqualTo($limite)) {
            return null;
        }

        $dias = (int) now()->diffInDays($home->cert_expira_at, false);

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Certificado a punto de caducar',
            $dias < 0
                ? 'El certificado ya ha caducado'
                : "El certificado caduca en {$dias} días",
            ['dias' => $dias],
        );
    }
}
