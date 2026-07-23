<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class ImagenesSinAlt implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'imagenes_sin_alt';
    }

    public function peso(): int
    {
        return 8;
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

        $total = (int) ($home->imagenes_total ?? 0);
        $sinAlt = (int) ($home->imagenes_sin_alt ?? 0);
        if ($total < 5) {
            return null;
        }

        $ratio = $sinAlt / $total;
        $umbral = (float) config('outreach.auditoria.umbral_imagenes_sin_alt');

        if ($ratio > $umbral) {
            $porcentaje = (int) round($ratio * 100);

            return new Hallazgo(
                $this->codigo(),
                $this->peso(),
                'Imágenes sin alt',
                "{$porcentaje}% de las imágenes sin texto alternativo",
                ['porcentaje' => $porcentaje],
            );
        }

        return null;
    }
}
