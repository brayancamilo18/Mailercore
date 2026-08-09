<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Enums\Certeza;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinFormularioContacto implements ContratoComprobacion
{
    use DescribeCobertura;

    public function codigo(): string
    {
        return 'sin_formulario';
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
        if ($home === null) {
            return null;
        }

        if (Pagina::señal($paginas, 'tiene_formulario', $lead->sector) !== false) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin formulario de contacto',
            'No hay formulario de contacto en el sitio',
            Certeza::OBSERVADO,
            evidencia: $this->cobertura($paginas).': ningún formulario de contacto',
        );
    }
}
