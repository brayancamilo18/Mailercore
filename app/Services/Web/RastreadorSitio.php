<?php

namespace App\Services\Web;

use App\DTO\ResultadoRastreo;
use App\Excepciones\UrlNoPermitida;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Pagina;
use App\Models\Suppression;
use App\Services\Soporte\ComprobadorRobots;
use App\Services\Soporte\GuardiaUrl;
use App\Services\Soporte\LimitadorRitmo;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class RastreadorSitio
{
    public function __construct(
        private ExtractorMetadatos $extractor,
        private ClasificadorEmail $clasificadorEmail,
        private ComprobadorRobots $robots,
        private LimitadorRitmo $limitador,
        private GuardiaUrl $guardia,
    ) {}

    public function rastrear(Lead $lead): ResultadoRastreo
    {
        $paginasVisitadas = 0;
        $paginasGuardadas = 0;
        $emailsGuardados = 0;
        $emailsDescartados = 0;
        $errores = [];
        /** @var array<string, string> email => url_origen */
        $emailsAcumulados = [];

        if ($lead->website === null || trim($lead->website) === '') {
            return new ResultadoRastreo(0, 0, 0, 0, []);
        }

        $base = $this->normalizarBase($lead->website);

        try {
            $this->guardia->comprobar($base);
        } catch (UrlNoPermitida $e) {
            return new ResultadoRastreo(0, 0, 0, 0, [$e->getMessage()]);
        }

        $maxPaginas = (int) config('outreach.scraper.max_paginas_por_sitio');
        $rutas = array_slice(config('outreach.scraper.rutas', []), 0, $maxPaginas);
        $urlHome = null;
        $emailsRolEncontrados = 0;

        foreach ($rutas as $ruta) {
            if ($emailsRolEncontrados >= 3) {
                break;
            }

            if (! $this->robots->rutaPermitida($base, $ruta)) {
                continue;
            }

            $url = $base.$ruta;
            $this->limitador->esperar($url);
            $paginasVisitadas++;

            try {
                $inicio = microtime(true);
                $respuesta = Http::withHeaders([
                    'User-Agent' => config('outreach.scraper.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9',
                ])
                    ->timeout((int) config('outreach.scraper.timeout'))
                    ->withOptions(['allow_redirects' => [
                        'max' => (int) config('outreach.scraper.max_redirecciones'),
                        'track_redirects' => true,
                    ]])
                    ->get($url);
                $ms = (int) round((microtime(true) - $inicio) * 1000);
            } catch (\Throwable $e) {
                $errores[] = "Error HTTP en {$url}: ".$e->getMessage();

                continue;
            }

            $urlFinal = $this->urlFinalTrasRedirect($respuesta, $url);

            try {
                $this->guardia->comprobar($urlFinal);
            } catch (UrlNoPermitida $e) {
                $errores[] = $e->getMessage();

                continue;
            }

            $contentType = (string) $respuesta->header('Content-Type');
            $status = $respuesta->status();
            $esHtml = str_contains(strtolower($contentType), 'text/html')
                || str_contains(strtolower($contentType), 'application/xhtml');

            if (! $esHtml) {
                Pagina::query()->updateOrCreate(
                    ['lead_id' => $lead->id, 'url' => $urlFinal],
                    [
                        'ruta' => $ruta === '' ? '/' : $ruta,
                        'http_status' => $status,
                        'content_type' => $contentType !== '' ? $contentType : null,
                        'bytes' => strlen($respuesta->body()),
                        'respuesta_ms' => $ms,
                        'redirigida_a' => $urlFinal !== $url ? $urlFinal : null,
                        'es_https' => str_starts_with($urlFinal, 'https://'),
                        'capturada_at' => now(),
                    ]
                );
                $paginasGuardadas++;
                if ($ruta === '' || $ruta === '/') {
                    $urlHome = $urlFinal;
                }

                continue;
            }

            if ($status < 200 || $status > 299) {
                Pagina::query()->updateOrCreate(
                    ['lead_id' => $lead->id, 'url' => $urlFinal],
                    [
                        'ruta' => $ruta === '' ? '/' : $ruta,
                        'http_status' => $status,
                        'content_type' => $contentType !== '' ? $contentType : null,
                        'bytes' => strlen($respuesta->body()),
                        'respuesta_ms' => $ms,
                        'redirigida_a' => $urlFinal !== $url ? $urlFinal : null,
                        'error' => 'HTTP '.$status,
                        'es_https' => str_starts_with($urlFinal, 'https://'),
                        'capturada_at' => now(),
                    ]
                );
                $paginasGuardadas++;

                continue;
            }

            $metadatos = $this->extractor->extraer(
                $respuesta->body(),
                $urlFinal,
                $status,
                $ms,
                $contentType !== '' ? $contentType : null,
                $urlFinal !== $url ? $urlFinal : null,
            );

            $datos = $metadatos->aArrayBd();
            $datos['lead_id'] = $lead->id;
            $datos['es_https'] = str_starts_with($urlFinal, 'https://');
            $datos['capturada_at'] = now();

            Pagina::query()->updateOrCreate(
                ['lead_id' => $lead->id, 'url' => $urlFinal],
                $datos
            );
            $paginasGuardadas++;

            if ($ruta === '' || $ruta === '/') {
                $urlHome = $urlFinal;
            }

            foreach ($metadatos->emailsEncontrados ?? [] as $email) {
                $email = strtolower(trim((string) $email));
                if ($email === '' || isset($emailsAcumulados[$email])) {
                    continue;
                }
                $emailsAcumulados[$email] = $urlFinal;
                if ($this->clasificadorEmail->clasificar($email, $lead->website_dominio) === ClasificadorEmail::ROL) {
                    $emailsRolEncontrados++;
                }
            }
        }

        if ($urlHome !== null && ! app()->environment('testing')) {
            $this->actualizarCertificadoHome($lead, $urlHome, $base);
        }

        $candidatosRol = [];
        foreach ($emailsAcumulados as $email => $urlOrigen) {
            $tipo = $this->clasificadorEmail->clasificar($email, $lead->website_dominio);
            if ($tipo !== ClasificadorEmail::ROL) {
                $emailsDescartados++;

                continue;
            }
            if (Suppression::existe($email)) {
                $emailsDescartados++;

                continue;
            }
            if (LeadEmail::query()->where('email', $email)->exists()) {
                $emailsDescartados++;

                continue;
            }

            $candidatosRol[] = [
                'email' => $email,
                'url_origen' => $urlOrigen,
                'prioridad' => $this->clasificadorEmail->prioridad($email),
                'prefijo' => $this->clasificadorEmail->prefijo($email),
            ];
        }

        usort($candidatosRol, fn (array $a, array $b): int => $a['prioridad'] <=> $b['prioridad']);
        $maxEmails = (int) config('outreach.scraper.max_emails_por_lead', 3);
        $candidatosRol = array_slice($candidatosRol, 0, $maxEmails);

        foreach ($candidatosRol as $i => $candidato) {
            LeadEmail::query()->create([
                'lead_id' => $lead->id,
                'email' => $candidato['email'],
                'tipo' => ClasificadorEmail::ROL,
                'prefijo' => $candidato['prefijo'],
                'origen' => 'web',
                'url_origen' => $candidato['url_origen'],
                'es_principal' => $i === 0,
                'prioridad' => $candidato['prioridad'],
            ]);
            $emailsGuardados++;
        }

        $dominio = Suppression::dominioDeWeb($base) ?? $lead->website_dominio;
        $cambios = [
            'website_dominio' => $dominio,
            'rastreado_at' => now(),
        ];
        if ($emailsGuardados > 0 && $lead->estado === 'nuevo') {
            $cambios['estado'] = 'rastreado';
        }
        $lead->forceFill($cambios)->save();

        return new ResultadoRastreo(
            $paginasVisitadas,
            $paginasGuardadas,
            $emailsGuardados,
            $emailsDescartados,
            $errores,
        );
    }

    private function normalizarBase(string $website): string
    {
        $url = trim($website);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return rtrim($url, '/');
    }

    private function urlFinalTrasRedirect(Response $respuesta, string $urlOriginal): string
    {
        $historial = $respuesta->header('X-Guzzle-Redirect-History');
        if ($historial === '' || $historial === null) {
            return (string) ($respuesta->effectiveUri() ?? $urlOriginal);
        }

        $urls = is_array($historial) ? $historial : explode(',', $historial);
        $ultima = trim((string) end($urls));

        return $ultima !== '' ? $ultima : $urlOriginal;
    }

    private function actualizarCertificadoHome(Lead $lead, string $urlHome, string $base): void
    {
        $host = parse_url($base, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return;
        }

        $certValido = null;
        $certExpira = null;

        try {
            $contexto = stream_context_create(['ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]]);
            $socket = @stream_socket_client(
                "ssl://{$host}:443",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $contexto
            );

            if ($socket !== false) {
                $params = stream_context_get_params($socket);
                $cert = $params['options']['ssl']['peer_certificate'] ?? null;
                if ($cert) {
                    $info = openssl_x509_parse($cert);
                    if (is_array($info) && isset($info['validTo_time_t'])) {
                        $certValido = ((int) $info['validTo_time_t']) > time();
                        $certExpira = now()->setTimestamp((int) $info['validTo_time_t']);
                    }
                }
                fclose($socket);
            }
        } catch (\Throwable) {
            $certValido = null;
            $certExpira = null;
        }

        Pagina::query()
            ->where('lead_id', $lead->id)
            ->where(function ($q) use ($urlHome): void {
                $q->where('url', $urlHome)
                    ->orWhereIn('ruta', ['/', '']);
            })
            ->update([
                'cert_valido' => $certValido,
                'cert_expira_at' => $certExpira,
                'es_https' => str_starts_with($urlHome, 'https://'),
            ]);
    }
}
