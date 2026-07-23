<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class TitleMalo implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'title_malo';
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

        if ($home->title === null) {
            return new Hallazgo(
                $this->codigo(),
                $this->peso(),
                'Título incorrecto',
                'Sin título',
                ['longitud' => 0],
            );
        }

        $n = (int) ($home->title_longitud ?? mb_strlen($home->title));
        if ($n > 65 || $n < 15) {
            return new Hallazgo(
                $this->codigo(),
                $this->peso(),
                'Título incorrecto',
                "Título de {$n} caracteres",
                ['longitud' => $n],
            );
        }

        return null;
    }
}
