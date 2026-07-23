<?php

namespace App\Services\Auditoria\Comprobaciones;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\ContratoComprobacion;
use Illuminate\Support\Collection;

class GeneradorObsoleto implements ContratoComprobacion
{
    public function codigo(): string
    {
        return 'generador_obsoleto';
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
        if ($home === null || $home->generador === null) {
            return null;
        }

        $generador = $home->generador;
        $obsoleto = in_array($generador, ['wix', 'joomla'], true)
            || ($generador === 'wordpress' && $home->tiene_viewport === false);

        if (! $obsoleto) {
            return null;
        }

        return new Hallazgo(
            $this->codigo(),
            $this->peso(),
            'Generador obsoleto',
            "Sitio generado con {$generador}",
            ['generador' => $generador],
        );
    }
}
