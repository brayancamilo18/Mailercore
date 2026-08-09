<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Enums\Certeza;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinCarrito implements ContratoComprobacion
{
    use DescribeCobertura;

    public function codigo(): string
    {
        return 'sin_carrito';
    }

    public function peso(): int
    {
        return 20;
    }

    public function sectores(): ?array
    {
        return ['retail'];
    }

    public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
    {
        $home = $paginas->firstWhere('ruta', '/') ?? $paginas->first();
        if ($home === null) {
            return null;
        }

        if (Pagina::señal($paginas, 'tiene_carrito', $lead->sector) !== false) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Sin carrito de compra',
            'No se detectó carrito o checkout',
            Certeza::OBSERVADO,
            evidencia: $this->cobertura($paginas).': ni carrito ni checkout',
        );
    }
}
