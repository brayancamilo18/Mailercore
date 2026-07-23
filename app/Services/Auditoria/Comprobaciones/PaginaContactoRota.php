<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class PaginaContactoRota implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'contacto_roto';
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
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null) {
            return null;
        }

        $rota = $paginas->first(function ($p): bool {
            return in_array($p->ruta, ['/contacto', '/contact'], true)
                && $p->http_status !== null
                && $p->http_status >= 400
                && $p->http_status <= 599;
        });

        if ($rota === null) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Página de contacto rota',
            "La página de contacto responde HTTP {$rota->http_status}",
            ['status' => (int) $rota->http_status],
        );
    }
}
