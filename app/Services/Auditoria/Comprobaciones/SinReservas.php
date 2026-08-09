<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Enums\Certeza;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinReservas implements ContratoComprobacion
{
    use DescribeCobertura;

    public function codigo(): string
    {
        return 'sin_reservas';
    }

    public function peso(): int
    {
        return 22;
    }

    public function sectores(): ?array
    {
        return ['hosteleria', 'salud', 'belleza'];
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null) {
            return null;
        }

        // null = nadie abrió una página donde vivirían las reservas, así que no
        // sabemos si las hay. Callar es mejor que inventarse la ausencia.
        if (Pagina::señal($paginas, 'tiene_reservas', $lead->sector) !== false) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin sistema de reservas',
            'No se detectó sistema de reservas online',
            Certeza::OBSERVADO,
            evidencia: $this->cobertura($paginas).': ningún sistema de reservas',
        );
    }
}
