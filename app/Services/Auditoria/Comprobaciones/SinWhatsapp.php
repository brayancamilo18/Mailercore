<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Enums\Certeza;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinWhatsapp implements ContratoComprobacion
{
    use DescribeCobertura;

    public function codigo(): string
    {
        return 'sin_whatsapp';
    }

    public function peso(): int
    {
        return 5;
    }

    public function sectores(): ?array
    {
        return ['oficios', 'belleza'];
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null) {
            return null;
        }

        if (Pagina::señal($paginas, 'tiene_whatsapp', $lead->sector) !== false) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin WhatsApp',
            'No hay enlace a WhatsApp',
            Certeza::OBSERVADO,
            evidencia: $this->cobertura($paginas).': ningún enlace a WhatsApp',
        );
    }
}
