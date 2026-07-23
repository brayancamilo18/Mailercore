<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class SinMetaDescription implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'sin_meta_description';
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

        $longitud = $home->meta_desc_longitud;
        if ($home->meta_description === null || ($longitud !== null && $longitud < 50)) {
            return new Hallazgo(
                $this->codigo(),
                $this->peso(),
                'Sin meta description',
                $home->meta_description === null
                    ? 'No hay meta description'
                    : "Meta description de {$longitud} caracteres",
                ['longitud' => $longitud],
            );
        }

        return null;
    }
}
