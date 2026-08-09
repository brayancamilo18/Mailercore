<?php

namespace App\Services\Web;

use App\DTO\IntentoHttp;
use App\DTO\ResultadoDiagnosticoRastreo;
use App\DTO\ResultadoRastreo;
use App\Enums\PropositoRastreo;
use App\Excepciones\UrlNoPermitida;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Pagina;
use App\Models\Suppression;
use App\Services\Soporte\ComprobadorRobots;
use App\Services\Soporte\GuardiaUrl;
use App\Services\Soporte\InspectorCertificado;
use App\Services\Soporte\LimitadorRitmo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class RastreadorSitio
{
    /** Correos de rol a partir de los cuales el propósito EMAIL deja de rastrear. */
    private const EMAILS_ROL_SUFICIENTES = 3;

    /** Intentos totales por URL, incluido el primero. */
    private const INTENTOS_MAXIMOS = 3;

    /** Espera entre intentos, en segundos. Solo se usa si no hay Retry-After. */
    private const BACKOFF_SEGUNDOS = [1, 4];

    /** Códigos que merecen otro intento; el resto de 4xx nunca se reintenta. */
    private const ESTADOS_REINTENTABLES = [429, 502, 503, 504];

    /** Tope de cortesía para Retry-After: más allá, no esperamos. */
    private const RETRY_AFTER_MAXIMO = 60;

    /** Si es true, el bucle no escribe ni en paginas ni en leads. */
    private bool $simulando = false;

    /** @var list<Pagina> */
    private array $paginasSimuladas = [];

    /**
     * @var list<array{
     *     ruta: string,
     *     origen: string,
     *     http_status: int|null,
     *     ms: int|null,
     *     bytes: int|null,
     *     truncada: bool|null,
     *     fallo_tipo: string|null,
     *     nota: string|null
     * }>
     */
    private array $visitas = [];

    /** @var list<string> */
    private array $rutasFijasPlan = [];

    /** @var list<string> */
    private array $rutasDescubiertasPlan = [];

    public function __construct(
        private ExtractorMetadatos $extractor,
        private ClasificadorEmail $clasificadorEmail,
        private ComprobadorRobots $robots,
        private LimitadorRitmo $limitador,
        private GuardiaUrl $guardia,
        private DescubridorRutas $descubridor,
        private InspectorCertificado $inspectorCertificado,
        private DetectorEnlacesSenal $detectorEnlacesSenal,
    ) {}

    /**
     * Mismo rastreo que rastrear(), pero sin persistir nada. Devuelve la traza
     * que el comando de diagnóstico imprime.
     */
    public function simular(Lead $lead, PropositoRastreo $proposito = PropositoRastreo::COMPLETO): ResultadoDiagnosticoRastreo
    {
        $this->simulando = true;
        $this->paginasSimuladas = [];
        $this->visitas = [];
        $this->rutasFijasPlan = [];
        $this->rutasDescubiertasPlan = [];

        try {
            $resultado = $this->rastrear($lead, $proposito);

            return new ResultadoDiagnosticoRastreo(
                resultado: $resultado,
                rutasFijas: $this->rutasFijasPlan,
                rutasDescubiertas: $this->rutasDescubiertasPlan,
                visitas: $this->visitas,
                paginas: collect($this->paginasSimuladas)->values(),
                hallazgos: [],
                rutasOmitidas: $resultado->rutasOmitidas,
            );
        } finally {
            $this->simulando = false;
            $this->paginasSimuladas = [];
            $this->visitas = [];
            $this->rutasFijasPlan = [];
            $this->rutasDescubiertasPlan = [];
        }
    }

    public function rastrear(Lead $lead, PropositoRastreo $proposito = PropositoRastreo::COMPLETO): ResultadoRastreo
    {
        $paginasVisitadas = 0;
        $paginasGuardadas = 0;
        $emailsGuardados = 0;
        $emailsDescartados = 0;
        $errores = [];
        /** @var list<string> */
        $rutasOmitidas = [];
        /** @var array<string, string> email => url_origen */
        $emailsAcumulados = [];

        if ($lead->website === null || trim($lead->website) === '') {
            return new ResultadoRastreo(0, 0, 0, 0, [], $proposito, [], 'guardia');
        }

        $base = $this->normalizarBase($lead->website);

        try {
            $this->guardia->comprobar($base);
        } catch (UrlNoPermitida $e) {
            return new ResultadoRastreo(0, 0, 0, 0, [$e->getMessage()], $proposito, [], 'guardia');
        }

        $maxPaginas = max(1, (int) config('outreach.scraper.max_paginas.'.$proposito->value, 6));
        $rutasPrevistas = $this->rutasPara($proposito, $lead);
        $rutas = array_slice($rutasPrevistas, 0, $maxPaginas);
        $this->rutasFijasPlan = array_map(
            fn (string $r): string => $r === '' ? '/' : $r,
            $rutas
        );

        foreach (array_slice($rutasPrevistas, count($rutas)) as $descartada) {
            $rutasOmitidas[] = $this->omitida($descartada, 'fuera del límite de max_paginas');
        }

        $crawlDelay = $this->robots->crawlDelayDe($base);
        $urlHome = null;
        $homeIntentada = false;
        $falloHome = null;
        $emailsRolEncontrados = 0;
        $yaDescubierto = false;
        // Cola de trabajo: la home puede alargarla con las rutas que descubra.
        $pendientes = $rutas;
        /** @var list<string> rutas que no venían de config, para origen_ruta */
        $descubiertas = [];

        for ($indice = 0; $indice < count($pendientes); $indice++) {
            $ruta = $pendientes[$indice];
            $esHome = $ruta === '' || $ruta === '/';
            // Trazabilidad de la fila: con qué propósito se pidió y de dónde
            // salió la ruta. Pagina::señal() necesita el propósito para poder
            // afirmar una ausencia.
            $trazabilidad = [
                'proposito' => $proposito->value,
                'origen_ruta' => in_array($ruta, $descubiertas, true) ? 'descubierta' : 'fija',
            ];

            if ($proposito->permiteCorteAnticipadoPorCorreos()
                && $emailsRolEncontrados >= self::EMAILS_ROL_SUFICIENTES) {
                foreach (array_slice($pendientes, $indice) as $pendiente) {
                    $rutasOmitidas[] = $this->omitida(
                        $pendiente,
                        'corte anticipado: ya hay '.$emailsRolEncontrados.' correos de rol'
                    );
                }

                break;
            }

            if (! $this->robots->rutaPermitida($base, $ruta)) {
                $rutasOmitidas[] = $this->omitida($ruta, 'bloqueada por robots.txt');
                $this->registrarVisita(
                    $ruta,
                    $trazabilidad['origen_ruta'],
                    null,
                    null,
                    null,
                    null,
                    'robots',
                    'bloqueada por robots.txt'
                );

                if ($esHome) {
                    $homeIntentada = true;
                    $falloHome = 'robots';
                }

                continue;
            }

            $url = $base.$ruta;
            $this->limitador->esperar($url, $crawlDelay);
            $paginasVisitadas++;

            if ($esHome) {
                $homeIntentada = true;
            }

            $intento = $this->pedirConReintentos($url);

            if (! $intento->huboRespuesta()) {
                $errores[] = "Error HTTP en {$url}: ".($intento->mensaje ?? 'sin respuesta');
                $this->registrarVisita(
                    $ruta,
                    $trazabilidad['origen_ruta'],
                    null,
                    $intento->ms > 0 ? $intento->ms : null,
                    null,
                    null,
                    $intento->falloTipo,
                    $intento->mensaje
                );

                if ($esHome) {
                    $falloHome = $intento->falloTipo;
                }

                // Sin respuesta no hay nada que observar: no se crea fila, para
                // que la auditoría no lea el hueco como una ausencia.
                if ($esHome && $this->esFalloDeTransporte($intento->falloTipo)) {
                    // El host no responde; el resto de rutas fallaría igual.
                    foreach (array_slice($pendientes, $indice + 1) as $pendiente) {
                        $rutasOmitidas[] = $this->omitida($pendiente, 'host inaccesible: '.$intento->falloTipo);
                    }

                    break;
                }

                continue;
            }

            /** @var Response $respuesta */
            $respuesta = $intento->respuesta;
            $ms = $intento->ms;

            $urlFinal = $this->urlFinalTrasRedirect($respuesta, $url);

            try {
                $this->guardia->comprobar($urlFinal);
            } catch (UrlNoPermitida $e) {
                $errores[] = $e->getMessage();
                $this->registrarVisita(
                    $ruta,
                    $trazabilidad['origen_ruta'],
                    $respuesta->status(),
                    $ms,
                    strlen($respuesta->body()),
                    null,
                    'guardia',
                    $e->getMessage()
                );

                if ($esHome) {
                    $falloHome = 'guardia';
                }

                continue;
            }

            $contentType = (string) $respuesta->header('Content-Type');
            $status = $respuesta->status();
            $esHtml = str_contains(strtolower($contentType), 'text/html')
                || str_contains(strtolower($contentType), 'application/xhtml');

            if (! $esHtml) {
                if ($esHome) {
                    // La home no es HTML: no sabemos nada de la web y no
                    // guardamos una fila que parezca «sin señales».
                    $falloHome = 'http';
                    $errores[] = "Contenido no HTML en {$urlFinal}: ".($contentType !== '' ? $contentType : 'sin content-type');
                    $this->registrarVisita(
                        $ruta,
                        $trazabilidad['origen_ruta'],
                        $status,
                        $ms,
                        strlen($respuesta->body()),
                        null,
                        'http',
                        'contenido no HTML'
                    );

                    continue;
                }

                $this->persistirPagina(
                    ['lead_id' => $lead->id, 'url' => $urlFinal],
                    $trazabilidad + [
                        'ruta' => $ruta === '' ? '/' : $ruta,
                        'http_status' => $status,
                        'content_type' => $contentType !== '' ? $contentType : null,
                        'bytes' => strlen($respuesta->body()),
                        'respuesta_ms' => $ms,
                        'redirigida_a' => $urlFinal !== $url ? $urlFinal : null,
                        'es_https' => str_starts_with($urlFinal, 'https://'),
                        'intentos' => $intento->intentos,
                        'capturada_at' => now(),
                    ]
                );
                $paginasGuardadas++;

                continue;
            }

            if ($status < 200 || $status > 299) {
                if ($esHome) {
                    $falloHome = 'http';
                    $errores[] = "HTTP {$status} en la home {$urlFinal}";
                    $this->registrarVisita(
                        $ruta,
                        $trazabilidad['origen_ruta'],
                        $status,
                        $ms,
                        strlen($respuesta->body()),
                        null,
                        'http',
                        'HTTP '.$status
                    );

                    continue;
                }

                $this->persistirPagina(
                    ['lead_id' => $lead->id, 'url' => $urlFinal],
                    $trazabilidad + [
                        'ruta' => $ruta === '' ? '/' : $ruta,
                        'http_status' => $status,
                        'content_type' => $contentType !== '' ? $contentType : null,
                        'bytes' => strlen($respuesta->body()),
                        'respuesta_ms' => $ms,
                        'redirigida_a' => $urlFinal !== $url ? $urlFinal : null,
                        'error' => 'HTTP '.$status,
                        'fallo_tipo' => 'http',
                        'intentos' => $intento->intentos,
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
            $datos['intentos'] = $intento->intentos;
            $datos['fallo_tipo'] = null;
            $datos += $trazabilidad;

            if ($esHome) {
                // Solo en la home: es la única página cuyos enlaces representan
                // lo que el sitio ofrece, y la auditoría no conserva el HTML.
                $datos['enlaces_senal'] = $this->detectorEnlacesSenal->detectar($respuesta->body(), $urlFinal);
            }

            $this->persistirPagina(['lead_id' => $lead->id, 'url' => $urlFinal], $datos);
            $paginasGuardadas++;

            if ($esHome) {
                $urlHome = $urlFinal;
                $falloHome = null;

                if (! $yaDescubierto && $proposito->permiteDescubrirRutas()) {
                    $yaDescubierto = true;
                    $this->encolarDescubiertas(
                        $this->descubridor->descubrir($respuesta->body(), $urlFinal, $lead->sector),
                        $pendientes,
                        $rutasOmitidas,
                        $maxPaginas,
                        $descubiertas,
                    );
                }
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

        if ($this->simulando) {
            return new ResultadoRastreo(
                $paginasVisitadas,
                $paginasGuardadas,
                0,
                0,
                $errores,
                $proposito,
                $rutasOmitidas,
                ($homeIntentada && $urlHome === null) ? ($falloHome ?? 'http') : null,
            );
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
        // Solo es fallo si llegamos a intentar la home y no trajo HTML usable.
        $rastreoFallido = $homeIntentada && $urlHome === null;
        $cambios = [
            'website_dominio' => $dominio,
            'rastreado_at' => now(),
            'rastreo_fallido_at' => $rastreoFallido ? now() : null,
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
            $proposito,
            $rutasOmitidas,
            $rastreoFallido ? ($falloHome ?? 'http') : null,
        );
    }

    /**
     * Pide una URL reintentando solo lo que puede arreglarse esperando:
     * timeouts, cortes de conexión y 429/502/503/504. Un 404 o un 403 se
     * devuelven tal cual, porque reintentarlos no cambia nada.
     */
    private function pedirConReintentos(string $url): IntentoHttp
    {
        $ultimoMensaje = null;
        $ultimoFallo = null;

        for ($intento = 1; $intento <= self::INTENTOS_MAXIMOS; $intento++) {
            $inicio = microtime(true);

            try {
                $respuesta = $this->peticion()->get($url);
                $ms = (int) round((microtime(true) - $inicio) * 1000);
            } catch (\Throwable $e) {
                $ultimoMensaje = $e->getMessage();
                $ultimoFallo = $this->clasificarExcepcion($e);

                if ($intento === self::INTENTOS_MAXIMOS) {
                    break;
                }

                $this->dormir($this->backoff($intento));

                continue;
            }

            $estado = $respuesta->status();

            if (! in_array($estado, self::ESTADOS_REINTENTABLES, true)
                || $intento === self::INTENTOS_MAXIMOS) {
                return new IntentoHttp($respuesta, $ms, $intento, null, null);
            }

            $this->dormir($this->retryAfter($respuesta) ?? $this->backoff($intento));
        }

        return new IntentoHttp(null, 0, self::INTENTOS_MAXIMOS, $ultimoFallo ?? 'http', $ultimoMensaje);
    }

    /** Espera antes del siguiente intento, en segundos. */
    private function backoff(int $intento): int
    {
        $esperas = self::BACKOFF_SEGUNDOS;

        return $esperas[$intento - 1] ?? (int) end($esperas);
    }

    private function peticion(): PendingRequest
    {
        $cabeceras = [
            'User-Agent' => config('outreach.scraper.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'es-ES,es;q=0.9',
        ];

        $contacto = $this->correoDeContacto();
        if ($contacto !== null) {
            // RFC 9110: quién es responsable del bot, por si hay que quejarse.
            $cabeceras['From'] = $contacto;
        }

        return Http::withHeaders($cabeceras)
            ->timeout((int) config('outreach.scraper.timeout'))
            ->withOptions(['allow_redirects' => [
                'max' => (int) config('outreach.scraper.max_redirecciones'),
                'track_redirects' => true,
            ]]);
    }

    /** Correo que el propio user agent anuncia, p. ej. "Bot/2.0 (correo@dominio)". */
    private function correoDeContacto(): ?string
    {
        $userAgent = (string) config('outreach.scraper.user_agent');

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $userAgent, $m) === 1) {
            return rtrim($m[0], '.');
        }

        return null;
    }

    /** Segundos que pide la respuesta antes del siguiente intento, si son razonables. */
    private function retryAfter(Response $respuesta): ?int
    {
        $cabecera = trim((string) $respuesta->header('Retry-After'));

        if ($cabecera === '') {
            return null;
        }

        if (ctype_digit($cabecera)) {
            $segundos = (int) $cabecera;
        } else {
            $fecha = strtotime($cabecera);

            if ($fecha === false) {
                return null;
            }

            $segundos = $fecha - time();
        }

        if ($segundos <= 0) {
            return 0;
        }

        return min($segundos, self::RETRY_AFTER_MAXIMO);
    }

    /**
     * Traduce el error de transporte a uno de los tipos que guardamos.
     * cURL no expone el código a través del cliente HTTP, así que miramos el texto.
     */
    private function clasificarExcepcion(\Throwable $e): string
    {
        $mensaje = strtolower($e->getMessage());

        if (str_contains($mensaje, 'timed out')
            || str_contains($mensaje, 'timeout')
            || str_contains($mensaje, 'curl error 28')) {
            return 'timeout';
        }

        if (str_contains($mensaje, 'resolve host')
            || str_contains($mensaje, 'resolve proxy')
            || str_contains($mensaje, 'name or service not known')
            || str_contains($mensaje, 'curl error 6')) {
            return 'dns';
        }

        if (str_contains($mensaje, 'ssl')
            || str_contains($mensaje, 'tls')
            || str_contains($mensaje, 'certificate')
            || str_contains($mensaje, 'curl error 35')
            || str_contains($mensaje, 'curl error 60')) {
            return 'tls';
        }

        return 'http';
    }

    /** El host no llegó a responder, así que el resto del sitio tampoco lo hará. */
    private function esFalloDeTransporte(?string $falloTipo): bool
    {
        return in_array($falloTipo, ['dns', 'tls', 'timeout'], true);
    }

    private function dormir(int|float $segundos): void
    {
        if ($segundos > 0) {
            Sleep::for($segundos)->seconds();
        }
    }

    /**
     * Añade a la cola las rutas descubiertas en la home que quepan dentro de
     * max_paginas. Pasan por el mismo bucle, así que siguen sujetas a robots y
     * al limitador de ritmo.
     *
     * @param  list<string>  $descubiertas
     * @param  list<string>  $pendientes
     * @param  list<string>  $rutasOmitidas
     * @param  list<string>  $encoladas  se rellena con las que entran, para origen_ruta
     */
    private function encolarDescubiertas(
        array $descubiertas,
        array &$pendientes,
        array &$rutasOmitidas,
        int $maxPaginas,
        array &$encoladas,
    ): void {
        foreach ($descubiertas as $descubierta) {
            if (in_array($descubierta, $pendientes, true)) {
                continue;
            }

            if (count($pendientes) >= $maxPaginas) {
                $rutasOmitidas[] = $this->omitida($descubierta, 'descubierta, fuera del límite de max_paginas');

                continue;
            }

            $pendientes[] = $descubierta;
            $encoladas[] = $descubierta;
            $this->rutasDescubiertasPlan[] = $descubierta === '' ? '/' : $descubierta;
        }
    }

    /**
     * Rutas que este propósito quiere visitar, antes de aplicar el límite.
     *
     * @return list<string>
     */
    private function rutasPara(PropositoRastreo $proposito, Lead $lead): array
    {
        if ($proposito === PropositoRastreo::EMAIL) {
            return array_values(config('outreach.scraper.rutas.email', []));
        }

        $comunes = config('outreach.scraper.rutas.auditoria_comunes', []);
        $comunes = is_array($comunes) ? $comunes : [];

        $sector = is_string($lead->sector) && $lead->sector !== '' ? $lead->sector : null;
        $deSector = $sector !== null
            ? config('outreach.scraper.rutas.auditoria_sector.'.$sector, [])
            : [];
        $deSector = is_array($deSector) ? $deSector : [];

        $vistas = [];
        $rutas = [];
        foreach (array_merge($comunes, $deSector) as $ruta) {
            if (! is_string($ruta) || isset($vistas[$ruta])) {
                continue;
            }
            $vistas[$ruta] = true;
            $rutas[] = $ruta;
        }

        return $rutas;
    }

    private function omitida(string $ruta, string $motivo): string
    {
        return ($ruta === '' ? '/' : $ruta).': '.$motivo;
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

        $resultado = $this->inspectorCertificado->inspeccionar($host);
        $certificados = [
            'cert_valido' => $resultado->noCaducado,
            'cert_cadena_valida' => $resultado->cadenaValida,
            'cert_expira_at' => $resultado->expiraAt,
            'es_https' => str_starts_with($urlHome, 'https://'),
        ];

        if ($this->simulando) {
            foreach ($this->paginasSimuladas as $pagina) {
                if ($pagina->url === $urlHome || in_array($pagina->ruta, ['/', ''], true)) {
                    $pagina->forceFill($certificados);
                }
            }

            return;
        }

        Pagina::query()
            ->where('lead_id', $lead->id)
            ->where(function ($q) use ($urlHome): void {
                $q->where('url', $urlHome)
                    ->orWhereIn('ruta', ['/', '']);
            })
            ->update($certificados);
    }

    /**
     * @param  array{lead_id: int, url: string}  $clave
     * @param  array<string, mixed>  $datos
     */
    private function persistirPagina(array $clave, array $datos): void
    {
        $ruta = (string) ($datos['ruta'] ?? '/');
        $origen = (string) ($datos['origen_ruta'] ?? 'fija');

        $this->registrarVisita(
            $ruta,
            $origen,
            isset($datos['http_status']) ? (int) $datos['http_status'] : null,
            isset($datos['respuesta_ms']) ? (int) $datos['respuesta_ms'] : null,
            isset($datos['bytes']) ? (int) $datos['bytes'] : null,
            array_key_exists('contenido_truncado', $datos)
                ? ($datos['contenido_truncado'] === null ? null : (bool) $datos['contenido_truncado'])
                : null,
            isset($datos['fallo_tipo']) ? (is_string($datos['fallo_tipo']) ? $datos['fallo_tipo'] : null) : null,
            isset($datos['error']) && is_string($datos['error']) ? $datos['error'] : null,
        );

        if ($this->simulando) {
            $this->paginasSimuladas[] = new Pagina(array_merge($clave, $datos));

            return;
        }

        Pagina::query()->updateOrCreate($clave, $datos);
    }

    private function registrarVisita(
        string $ruta,
        string $origen,
        ?int $status,
        ?int $ms,
        ?int $bytes,
        ?bool $truncada,
        ?string $falloTipo,
        ?string $nota = null,
    ): void {
        if (! $this->simulando) {
            return;
        }

        $this->visitas[] = [
            'ruta' => $ruta === '' ? '/' : $ruta,
            'origen' => $origen,
            'http_status' => $status,
            'ms' => $ms,
            'bytes' => $bytes,
            'truncada' => $truncada,
            'fallo_tipo' => $falloTipo,
            'nota' => $nota,
        ];
    }
}
