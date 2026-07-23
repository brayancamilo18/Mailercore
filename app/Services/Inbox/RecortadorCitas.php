<?php

namespace App\Services\Inbox;

class RecortadorCitas
{
    /** Marcadores que indican el comienzo del texto citado. */
    private const PATRONES = [
        '/^-{2,}\s*Mensaje original\s*-{2,}/im',
        '/^-{2,}\s*Original Message\s*-{2,}/im',
        '/^-{2,}\s*Forwarded message\s*-{2,}/im',
        '/^El .{5,80} escribi[oó]:\s*$/im',
        '/^On .{5,80} wrote:\s*$/im',
        '/^Le .{5,80} a écrit\s*:\s*$/im',
        '/^De:\s*.+$/im',
        '/^From:\s*.+$/im',
        '/^_{5,}\s*$/m',
        '/^Enviado desde mi /im',
        '/^Obtener Outlook para /im',
        '/^Sent from my /im',
    ];

    /**
     * Devuelve solo el texto nuevo del mensaje, sin la parte citada.
     *
     * @return array{texto:string,solo_citado:bool}
     */
    public function recortar(string $cuerpo): array
    {
        $cuerpo = str_replace(["\r\n", "\r"], "\n", $cuerpo);
        $corte = mb_strlen($cuerpo);

        foreach (self::PATRONES as $patron) {
            if (preg_match($patron, $cuerpo, $coincidencias, PREG_OFFSET_CAPTURE)) {
                $posicion = $coincidencias[0][1];

                if ($posicion < $corte) {
                    $corte = $posicion;
                }
            }
        }

        // Bloque de líneas que empiezan por ">"
        $lineas = explode("\n", $cuerpo);
        $desplazamiento = 0;

        foreach ($lineas as $linea) {
            if (preg_match('/^\s*>/', $linea)) {
                if ($desplazamiento < $corte) {
                    $corte = $desplazamiento;
                }
                break;
            }

            $desplazamiento += mb_strlen($linea) + 1;
        }

        $nuevo = trim(mb_substr($cuerpo, 0, $corte));

        if (mb_strlen($nuevo) < 5) {
            return ['texto' => trim($cuerpo), 'solo_citado' => true];
        }

        return ['texto' => $nuevo, 'solo_citado' => false];
    }
}
