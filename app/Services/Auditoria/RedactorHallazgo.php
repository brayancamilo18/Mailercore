<?php

namespace App\Services\Auditoria;

use App\Models\Auditoria;
use App\Models\Lead;

class RedactorHallazgo
{
    /** @var array<string, array<string, array{asunto: string, apertura: string}>>|null */
    private static ?array $frasesCache = null;

    /**
     * @return array{asunto: string, apertura: string}|null
     */
    public function redactar(Lead $lead, Auditoria $auditoria, bool $secundario = false): ?array
    {
        $codigo = $secundario ? $auditoria->hallazgo_secundario_codigo : $auditoria->hallazgo_codigo;

        if ($codigo === null) {
            return null;
        }

        $frases = $this->frases();
        $bloque = $frases[$codigo][$lead->sector] ?? $frases[$codigo]['generico'] ?? null;

        if ($bloque === null) {
            return null;   // sin frase, el lead no se envía
        }

        $hallazgo = collect($auditoria->hallazgos ?? [])->firstWhere('codigo', $codigo);
        /** @var array<string, mixed> $datos */
        $datos = is_array($hallazgo) ? ($hallazgo['datos'] ?? []) : [];

        $sustituciones = array_merge($datos, [
            'nombre' => $lead->nombre,
            'dominio' => $lead->website_dominio ?? '',
        ]);

        $reemplazar = function (string $texto) use ($sustituciones): string {
            foreach ($sustituciones as $clave => $valor) {
                $texto = str_replace('{'.$clave.'}', $this->formatear($valor), $texto);
            }

            // Si queda algún marcador sin sustituir, la frase no sirve.
            return $texto;
        };

        $asunto = $reemplazar($bloque['asunto']);
        $apertura = $reemplazar($bloque['apertura']);

        if (str_contains($asunto, '{') || str_contains($apertura, '{')) {
            return null;
        }

        return ['asunto' => $asunto, 'apertura' => $apertura];
    }

    private function formatear(mixed $valor): string
    {
        if (is_float($valor)) {
            return number_format($valor, 1, ',', '');
        }

        return (string) $valor;
    }

    /**
     * @return array<string, array<string, array{asunto: string, apertura: string}>>
     */
    private function frases(): array
    {
        return self::$frasesCache ??= require resource_path('data/frases_hallazgo.php');
    }
}
