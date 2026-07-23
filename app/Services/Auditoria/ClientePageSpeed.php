<?php

namespace App\Services\Auditoria;

use App\DTO\ResultadoPageSpeed;
use App\Excepciones\CuotaPageSpeedExcedida;
use Illuminate\Support\Facades\Http;

class ClientePageSpeed
{
    public function analizar(string $url): ?ResultadoPageSpeed
    {
        $endpoint = (string) config('outreach.pagespeed.endpoint');

        // Google no acepta category[]=...; hay que repetir category= a mano.
        $consulta = http_build_query([
            'url' => $url,
            'strategy' => config('outreach.pagespeed.estrategia'),
            'key' => config('outreach.pagespeed.api_key'),
        ]);

        $urlCompleta = $endpoint.'?'.$consulta
            .'&category=performance&category=seo&category=accessibility&category=best-practices';

        $respuesta = Http::timeout((int) config('outreach.pagespeed.timeout'))
            ->get($urlCompleta);

        if ($respuesta->status() === 429) {
            throw new CuotaPageSpeedExcedida('Cuota de PageSpeed agotada');
        }

        if (! $respuesta->successful()) {
            return null;   // web caída, bloqueada o no analizable
        }

        $lh = $respuesta->json('lighthouseResult');

        if (! is_array($lh)) {
            return null;
        }

        $puntuacion = fn (string $clave): ?int => isset($lh['categories'][$clave]['score'])
            ? (int) round(((float) $lh['categories'][$clave]['score']) * 100)
            : null;

        $numerico = fn (string $auditoria): ?float => isset($lh['audits'][$auditoria]['numericValue'])
            ? (float) $lh['audits'][$auditoria]['numericValue']
            : null;

        $peso = $numerico('total-byte-weight');

        return new ResultadoPageSpeed(
            rendimiento: $puntuacion('performance'),
            seo: $puntuacion('seo'),
            accesibilidad: $puntuacion('accessibility'),
            buenasPracticas: $puntuacion('best-practices'),
            lcpMs: ($v = $numerico('largest-contentful-paint')) !== null ? (int) round($v) : null,
            cls: ($v = $numerico('cumulative-layout-shift')) !== null ? round($v, 3) : null,
            tbtMs: ($v = $numerico('total-blocking-time')) !== null ? (int) round($v) : null,
            pesoKb: $peso !== null ? (int) round($peso / 1024) : null,
        );
    }
}
