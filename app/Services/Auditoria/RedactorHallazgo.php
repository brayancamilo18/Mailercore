<?php

namespace App\Services\Auditoria;

use App\Models\Auditoria;
use App\Models\Lead;

class RedactorHallazgo
{
    /** @var array<string, array<string, array{asunto: string, apertura: string}>>|null */
    private static ?array $frasesCache = null;

    /**
     * Prioridad: hallazgo de auditoría → HTTPS confirmado → apertura genérica del sector.
     *
     * @return array{asunto: string, apertura: string}
     */
    public function redactar(Lead $lead, ?Auditoria $auditoria = null, bool $secundario = false): array
    {
        if ($auditoria !== null) {
            $desdeHallazgo = $this->desdeHallazgo($lead, $auditoria, $secundario);
            if ($desdeHallazgo !== null) {
                return $desdeHallazgo;
            }
        }

        // HTTPS solo en primer contacto y si no hubo frase de hallazgo usable.
        if (! $secundario && $this->faltaHttps($lead)) {
            $variantes = require resource_path('data/aperturas_https.php');

            return $this->elegirYRellenar($variantes, $lead, secundario: false);
        }

        $todas = require resource_path('data/aperturas_sector.php');
        $sector = $lead->sector ?? 'generico';
        $variantes = $todas[$sector] ?? $todas['generico'];

        return $this->elegirYRellenar($variantes, $lead, $secundario);
    }

    /**
     * @return array{asunto: string, apertura: string}|null
     */
    private function desdeHallazgo(Lead $lead, Auditoria $auditoria, bool $secundario): ?array
    {
        $codigo = $secundario ? $auditoria->hallazgo_secundario_codigo : $auditoria->hallazgo_codigo;

        if ($codigo === null || $codigo === '') {
            return null;
        }

        $frases = $this->frases();
        $sector = $lead->sector ?? 'generico';
        $bloque = $frases[$codigo][$sector] ?? $frases[$codigo]['generico'] ?? null;

        if ($bloque === null) {
            return null;
        }

        $hallazgo = collect($auditoria->hallazgos ?? [])->firstWhere('codigo', $codigo);
        /** @var array<string, mixed> $datos */
        $datos = is_array($hallazgo) ? ($hallazgo['datos'] ?? []) : [];

        $sustituciones = array_merge($datos, [
            'nombre' => $lead->nombre,
            'dominio' => $lead->website_dominio ?? 'tu web',
        ]);

        $asunto = $this->rellenar($bloque['asunto'], $sustituciones);
        $apertura = $this->rellenar($bloque['apertura'], $sustituciones);

        if (str_contains($asunto, '{') || str_contains($apertura, '{')) {
            return null;
        }

        return ['asunto' => $asunto, 'apertura' => $apertura];
    }

    /** @param  array<string, mixed>  $sustituciones */
    private function rellenar(string $texto, array $sustituciones): string
    {
        foreach ($sustituciones as $clave => $valor) {
            $texto = str_replace('{'.$clave.'}', $this->formatear($valor), $texto);
        }

        return $texto;
    }

    private function formatear(mixed $valor): string
    {
        if (is_float($valor)) {
            return number_format($valor, 1, ',', '');
        }

        return (string) $valor;
    }

    /** ¿La auditoría confirmó ausencia de HTTPS en la home? */
    private function faltaHttps(Lead $lead): bool
    {
        $home = $lead->paginas->firstWhere('ruta', '/') ?? $lead->paginas->first();

        return $home !== null && $home->es_https === false;
    }

    /**
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
            'dominio' => $lead->website_dominio ?? 'tu web',
            'nombre' => $lead->nombre ?? 'tu negocio',
        ];

        return [
            'asunto' => $this->rellenar($bloque['asunto'], $sustituciones),
            'apertura' => $this->rellenar($bloque['apertura'], $sustituciones),
        ];
    }

    /**
     * @return array<string, array<string, array{asunto: string, apertura: string}>>
     */
    private function frases(): array
    {
        return self::$frasesCache ??= require resource_path('data/frases_hallazgo.php');
    }
}
