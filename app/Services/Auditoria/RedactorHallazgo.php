<?php

namespace App\Services\Auditoria;

use App\Models\Auditoria;
use App\Models\Lead;

class RedactorHallazgo
{
    /**
     * Elige la apertura del correo: HTTPS solo si está confirmado y es el primer
     * contacto; en cualquier otro caso, apertura genérica del sector.
     *
     * @return array{asunto: string, apertura: string}
     */
    public function redactar(Lead $lead, ?Auditoria $auditoria = null, bool $secundario = false): array
    {
        // 1. ¿El sistema confirmó que NO usa HTTPS? Es lo único verificable que
        //    podemos mencionar. Solo en el primer contacto, no en el seguimiento.
        if (! $secundario && $this->faltaHttps($lead)) {
            $variantes = require resource_path('data/aperturas_https.php');

            return $this->elegirYRellenar($variantes, $lead, secundario: false);
        }

        // 2. En cualquier otro caso, apertura genérica del sector del lead.
        $todas = require resource_path('data/aperturas_sector.php');
        $sector = $lead->sector ?? 'generico';
        $variantes = $todas[$sector] ?? $todas['generico'];

        return $this->elegirYRellenar($variantes, $lead, $secundario);
    }

    /** ¿La auditoría confirmó ausencia de HTTPS en la home? */
    private function faltaHttps(Lead $lead): bool
    {
        $home = $lead->paginas->firstWhere('ruta', '/') ?? $lead->paginas->first();

        return $home !== null && $home->es_https === false;
    }

    /**
     * Elige una variante de forma estable por lead (para que si se reenvía salga
     * la misma) y rellena {dominio} y {nombre}.
     * En seguimiento ($secundario) usa la otra variante para no repetir el correo 1.
     *
     * @param  list<array{asunto: string, apertura: string}>  $variantes
     * @return array{asunto: string, apertura: string}
     */
    private function elegirYRellenar(array $variantes, Lead $lead, bool $secundario = false): array
    {
        $indice = $secundario
            ? ($lead->id + 1) % count($variantes)
            : $lead->id % count($variantes);

        $bloque = $variantes[$indice];

        $sustituciones = [
            '{dominio}' => $lead->website_dominio ?? 'tu web',
            '{nombre}' => $lead->nombre ?? 'tu negocio',
        ];

        return [
            'asunto' => strtr($bloque['asunto'], $sustituciones),
            'apertura' => strtr($bloque['apertura'], $sustituciones),
        ];
    }
}
