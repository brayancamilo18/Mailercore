<?php

namespace App\Services\Auditoria;

use App\DTO\Hallazgo;
use App\Models\Auditoria;
use App\Models\Lead;

class MotorAuditoria
{
    /** @var list<ContratoComprobacion> */
    private array $comprobaciones;

    /** @param  list<ContratoComprobacion>  $comprobaciones */
    public function __construct(array $comprobaciones)
    {
        $this->comprobaciones = $comprobaciones;
    }

    public function auditar(Lead $lead): ?Auditoria
    {
        $paginas = $lead->paginas()->orderByDesc('capturada_at')->get();

        if ($paginas->isEmpty()) {
            return null;
        }

        // Nos quedamos con la captura más reciente de cada ruta.
        $paginas = $paginas->unique('ruta')->values();

        $auditoria = $lead->auditoria;
        $hallazgos = [];

        foreach ($this->comprobaciones as $comprobacion) {
            $sectores = $comprobacion->sectores();

            if ($sectores !== null && ! in_array($lead->sector, $sectores, true)) {
                continue;
            }

            $hallazgo = $comprobacion->evaluar($lead, $paginas, $auditoria);

            if ($hallazgo !== null) {
                $hallazgos[] = $hallazgo;
            }
        }

        usort($hallazgos, fn (Hallazgo $a, Hallazgo $b): int => $b->peso <=> $a->peso);

        $puntuacion = min(
            (int) config('outreach.auditoria.peso_maximo'),
            array_sum(array_map(fn (Hallazgo $h): int => $h->peso, $hallazgos))
        );

        $principal = $hallazgos[0] ?? null;
        $secundario = $hallazgos[1] ?? null;

        return Auditoria::updateOrCreate(
            ['lead_id' => $lead->id],
            [
                'puntuacion' => $puntuacion,
                'hallazgo_codigo' => $principal?->codigo,
                'hallazgo_principal' => $principal?->detalle,
                'hallazgo_secundario_codigo' => $secundario?->codigo,
                'hallazgo_secundario' => $secundario?->detalle,
                'hallazgos' => array_map(fn (Hallazgo $h): array => [
                    'codigo' => $h->codigo,
                    'peso' => $h->peso,
                    'titulo' => $h->titulo,
                    'detalle' => $h->detalle,
                    'datos' => $h->datos,
                ], $hallazgos),
                'auditada_at' => now(),
            ]
        );
    }
}
