<?php

namespace Tests\Feature;

use App\Enums\PropositoRastreo;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Pagina;
use App\Services\Soporte\ComprobadorRobots;
use App\Services\Web\RastreadorSitio;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class RastreadorSitioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Los reintentos duermen 1 s y 4 s: sin esto la suite tardaría minutos.
        Sleep::fake();
        RateLimiter::clear('scrape:global');
        RateLimiter::clear('scrape:host:empresa-demo.es');
        config([
            'outreach.scraper.rutas' => [
                'email' => ['', '/contacto'],
                'auditoria_comunes' => ['', '/contacto'],
                'auditoria_sector' => [],
            ],
            'outreach.scraper.max_paginas' => [
                'email' => 2,
                'auditoria' => 2,
                'completo' => 2,
            ],
            'outreach.scraper.peticiones_por_minuto' => 100,
            'outreach.scraper.peticiones_por_dominio_por_minuto' => 100,
            'outreach.scraper.respetar_robots' => true,
        ]);
        $this->app->forgetInstance(ComprobadorRobots::class);
    }

    private function htmlConEmails(): string
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html lang="es"><head><title>Empresa Demo SL - servicios profesionales</title>
        <meta name="viewport" content="width=device-width">
        </head><body>
        <h1>Empresa Demo</h1>
        <a href="mailto:info@empresa-demo.es">info</a>
        <a href="mailto:maria.lopez@empresa-demo.es">maria</a>
        <p>comercial@empresa-demo.es</p>
        </body></html>
        HTML;
    }

    /** Home con tres correos de rol: suficiente para disparar el corte anticipado. */
    private function htmlConTresCorreosDeRol(): string
    {
        return <<<'HTML'
        <!DOCTYPE html>
        <html lang="es"><head><title>Empresa Demo SL</title></head><body>
        <h1>Empresa Demo</h1>
        <a href="mailto:info@empresa-demo.es">info</a>
        <a href="mailto:contacto@empresa-demo.es">contacto</a>
        <a href="mailto:reservas@empresa-demo.es">reservas</a>
        </body></html>
        HTML;
    }

    private function fakeHomeConTresCorreosDeRol(): void
    {
        $home = $this->htmlConTresCorreosDeRol();

        Http::fake(function (Request $request) use ($home) {
            $url = $request->url();

            if (str_contains($url, 'robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            if (str_contains($url, '/contacto')) {
                return Http::response(
                    '<html><body><h1>Contacto</h1></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                );
            }

            return Http::response($home, 200, ['Content-Type' => 'text/html']);
        });
    }

    private function leadDemo(): Lead
    {
        return Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
            'estado' => 'nuevo',
        ]);
    }

    private function fakeSitioOk(string $host = 'empresa-demo.es'): void
    {
        $html = $this->htmlConEmails();
        Http::fake(function (Request $request) use ($host, $html) {
            $url = $request->url();

            if (str_contains($url, 'robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            if (str_contains($url, '/contacto')) {
                return Http::response(
                    '<html><body><a href="mailto:info@'.$host.'">x</a></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                );
            }

            return Http::response($html, 200, ['Content-Type' => 'text/html']);
        });
    }

    public function test_guarda_una_fila_de_pagina_por_ruta_visitada(): void
    {
        $this->fakeSitioOk();
        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
            'estado' => 'nuevo',
        ]);

        $resultado = $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertSame(2, $resultado->paginasVisitadas);
        $this->assertSame(2, Pagina::query()->where('lead_id', $lead->id)->count());
    }

    public function test_guarda_paginas_con_error_404(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://empresa-demo.es' => Http::response($this->htmlConEmails(), 200, ['Content-Type' => 'text/html']),
            'https://empresa-demo.es/' => Http::response($this->htmlConEmails(), 200, ['Content-Type' => 'text/html']),
            'https://empresa-demo.es/contacto' => Http::response('Not Found', 404, ['Content-Type' => 'text/html']),
        ]);

        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
        ]);

        $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $pagina404 = Pagina::query()
            ->where('lead_id', $lead->id)
            ->where('ruta', '/contacto')
            ->first();

        $this->assertNotNull($pagina404);
        $this->assertSame(404, $pagina404->http_status);
        $this->assertNotNull($pagina404->error);
    }

    public function test_solo_guarda_emails_de_rol(): void
    {
        $this->fakeSitioOk();
        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
        ]);

        $resultado = $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $emails = LeadEmail::query()->where('lead_id', $lead->id)->pluck('email')->all();
        $this->assertContains('info@empresa-demo.es', $emails);
        $this->assertNotContains('maria.lopez@empresa-demo.es', $emails);
        $this->assertGreaterThan(0, $resultado->emailsGuardados);
        $this->assertGreaterThan(0, $resultado->emailsDescartados);
    }

    public function test_marca_el_primer_email_como_principal(): void
    {
        $this->fakeSitioOk();
        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
        ]);

        $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $principal = LeadEmail::query()
            ->where('lead_id', $lead->id)
            ->where('es_principal', true)
            ->first();

        $this->assertNotNull($principal);
        $this->assertSame(1, LeadEmail::query()->where('lead_id', $lead->id)->where('es_principal', true)->count());
        // info tiene prioridad 0
        $this->assertSame('info@empresa-demo.es', $principal->email);
    }

    public function test_respeta_robots_txt(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nDisallow: /contacto\n", 200),
            'https://empresa-demo.es' => Http::response($this->htmlConEmails(), 200, ['Content-Type' => 'text/html']),
            'https://empresa-demo.es/' => Http::response($this->htmlConEmails(), 200, ['Content-Type' => 'text/html']),
            'https://empresa-demo.es/contacto' => Http::response('no debería pedirse', 200),
        ]);

        // Rebind robots con cache limpia
        Cache::flush();
        $this->app->forgetInstance(ComprobadorRobots::class);

        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
        ]);

        $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertNull(
            Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/contacto')->first()
        );
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/contacto')
            && ! str_contains($request->url(), 'robots.txt'));
    }

    public function test_no_visita_ip_privada(): void
    {
        Http::fake();

        $lead = Lead::factory()->create([
            'website' => 'http://192.168.1.10',
            'website_dominio' => '192.168.1.10',
        ]);

        $resultado = $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertSame(0, $resultado->paginasVisitadas);
        $this->assertNotEmpty($resultado->errores);
        Http::assertNothingSent();
    }

    public function test_actualiza_rastreado_at(): void
    {
        $this->fakeSitioOk();
        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
            'estado' => 'nuevo',
            'rastreado_at' => null,
        ]);

        $this->app->make(RastreadorSitio::class)->rastrear($lead);
        $lead->refresh();

        $this->assertNotNull($lead->rastreado_at);
        $this->assertSame('rastreado', $lead->estado);
    }

    public function test_no_duplica_paginas_al_rastrear_dos_veces(): void
    {
        $this->fakeSitioOk();
        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
        ]);

        $rastreador = $this->app->make(RastreadorSitio::class);
        $rastreador->rastrear($lead);
        $rastreador->rastrear($lead->fresh());

        $this->assertSame(2, Pagina::query()->where('lead_id', $lead->id)->count());
    }

    public function test_auditoria_visita_todas_las_rutas_aunque_la_home_ya_tenga_tres_correos_de_rol(): void
    {
        $this->fakeHomeConTresCorreosDeRol();
        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::AUDITORIA);

        $this->assertSame(PropositoRastreo::AUDITORIA, $resultado->proposito);
        $this->assertSame(2, $resultado->paginasVisitadas);
        $this->assertSame(2, Pagina::query()->where('lead_id', $lead->id)->count());
        $this->assertSame([], $resultado->rutasOmitidas);

        // La home sola ya reunía tres correos de rol y aun así se pidió /contacto.
        $this->assertSame(3, LeadEmail::query()->where('lead_id', $lead->id)->count());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/contacto')
            && ! str_contains($request->url(), 'robots.txt'));
        $this->assertNotNull(
            Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/contacto')->first()
        );
    }

    public function test_completo_tampoco_corta_el_bucle_y_es_el_valor_por_defecto(): void
    {
        $this->fakeHomeConTresCorreosDeRol();
        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertSame(PropositoRastreo::COMPLETO, $resultado->proposito);
        $this->assertSame(2, $resultado->paginasVisitadas);
        $this->assertSame([], $resultado->rutasOmitidas);
    }

    public function test_email_corta_el_bucle_al_reunir_tres_correos_de_rol(): void
    {
        $this->fakeHomeConTresCorreosDeRol();
        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::EMAIL);

        $this->assertSame(PropositoRastreo::EMAIL, $resultado->proposito);
        $this->assertSame(1, $resultado->paginasVisitadas);
        $this->assertSame(1, Pagina::query()->where('lead_id', $lead->id)->count());
        $this->assertSame(['/contacto: corte anticipado: ya hay 3 correos de rol'], $resultado->rutasOmitidas);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/contacto')
            && ! str_contains($request->url(), 'robots.txt'));
    }

    public function test_email_no_corta_el_bucle_si_no_reune_correos_suficientes(): void
    {
        // La home de este fake solo trae un correo de rol (info) y uno personal.
        $this->fakeSitioOk();
        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::EMAIL);

        $this->assertSame(2, $resultado->paginasVisitadas);
        $this->assertSame([], $resultado->rutasOmitidas);
    }

    public function test_rutas_omitidas_recoge_las_bloqueadas_por_robots(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nDisallow: /contacto\n", 200),
            'https://empresa-demo.es' => Http::response($this->htmlConTresCorreosDeRol(), 200, ['Content-Type' => 'text/html']),
            'https://empresa-demo.es/' => Http::response($this->htmlConTresCorreosDeRol(), 200, ['Content-Type' => 'text/html']),
        ]);

        Cache::flush();
        $this->app->forgetInstance(ComprobadorRobots::class);

        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::AUDITORIA);

        $this->assertSame(['/contacto: bloqueada por robots.txt'], $resultado->rutasOmitidas);
        $this->assertSame(1, $resultado->paginasVisitadas);
    }

    public function test_rutas_omitidas_recoge_las_que_pasan_del_limite_de_paginas(): void
    {
        config([
            'outreach.scraper.rutas' => [
                'email' => ['', '/contacto', '/nosotros'],
                'auditoria_comunes' => ['', '/contacto', '/nosotros'],
                'auditoria_sector' => [],
            ],
            'outreach.scraper.max_paginas' => [
                'email' => 2,
                'auditoria' => 2,
                'completo' => 2,
            ],
        ]);
        $this->fakeHomeConTresCorreosDeRol();
        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::AUDITORIA);

        $this->assertSame(
            ['/nosotros: fuera del límite de max_paginas'],
            $resultado->rutasOmitidas
        );
        $this->assertSame(2, $resultado->paginasVisitadas);
    }

    public function test_auditoria_anade_rutas_del_sector_y_deduplica(): void
    {
        config([
            'outreach.scraper.rutas' => [
                'email' => [''],
                'auditoria_comunes' => ['', '/contacto', '/aviso-legal'],
                'auditoria_sector' => [
                    'hosteleria' => ['/contacto', '/reservas', '/carta'],
                ],
            ],
            'outreach.scraper.max_paginas' => [
                'email' => 10,
                'auditoria' => 10,
                'completo' => 10,
            ],
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            return Http::response(
                '<html><body><h1>ok</h1></body></html>',
                200,
                ['Content-Type' => 'text/html']
            );
        });

        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
            'sector' => 'hosteleria',
            'estado' => 'nuevo',
        ]);

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::AUDITORIA);

        // comunes + sector, con /contacto una sola vez: '', /contacto, /aviso-legal, /reservas, /carta
        $this->assertSame(5, $resultado->paginasVisitadas);
        $this->assertSame([], $resultado->rutasOmitidas);
        $this->assertNotNull(
            Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/reservas')->first()
        );
        $this->assertNotNull(
            Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/carta')->first()
        );
    }

    /** Home de restaurante cuyo enlace de reservas no está en el conjunto fijo. */
    private function fakeHomeConEnlaceDescubrible(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_contains($url, 'robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            if (str_contains($url, '/reserva-online')) {
                return Http::response(
                    '<html><body><h1>Reserva tu mesa</h1></body></html>',
                    200,
                    ['Content-Type' => 'text/html']
                );
            }

            return Http::response(
                '<html lang="es"><body><h1>Casa Demo</h1>
                <a href="/reserva-online">Reservar mesa</a>
                <a href="mailto:info@empresa-demo.es">info</a>
                </body></html>',
                200,
                ['Content-Type' => 'text/html']
            );
        });
    }

    public function test_auditoria_rastrea_las_rutas_descubiertas_en_la_home(): void
    {
        config([
            'outreach.scraper.rutas' => [
                'email' => [''],
                'auditoria_comunes' => [''],
                'auditoria_sector' => [],
            ],
            'outreach.scraper.max_paginas' => ['email' => 4, 'auditoria' => 4, 'completo' => 4],
        ]);
        $this->fakeHomeConEnlaceDescubrible();

        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
            'sector' => 'hosteleria',
            'estado' => 'nuevo',
        ]);

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::AUDITORIA);

        // La home más la ruta que solo se conoce mirando sus enlaces.
        $this->assertSame(2, $resultado->paginasVisitadas);
        $this->assertNotNull(
            Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/reserva-online')->first()
        );
    }

    public function test_email_no_descubre_rutas_nuevas(): void
    {
        config([
            'outreach.scraper.rutas' => [
                'email' => [''],
                'auditoria_comunes' => [''],
                'auditoria_sector' => [],
            ],
            'outreach.scraper.max_paginas' => ['email' => 4, 'auditoria' => 4, 'completo' => 4],
        ]);
        $this->fakeHomeConEnlaceDescubrible();

        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
            'sector' => 'hosteleria',
            'estado' => 'nuevo',
        ]);

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::EMAIL);

        $this->assertSame(1, $resultado->paginasVisitadas);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/reserva-online'));
    }

    public function test_auditoria_sin_sector_solo_usa_rutas_comunes(): void
    {
        config([
            'outreach.scraper.rutas' => [
                'email' => [''],
                'auditoria_comunes' => ['', '/contacto'],
                'auditoria_sector' => [
                    'hosteleria' => ['/reservas'],
                ],
            ],
            'outreach.scraper.max_paginas' => [
                'email' => 10,
                'auditoria' => 10,
                'completo' => 10,
            ],
        ]);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'robots.txt')) {
                return Http::response("User-agent: *\nAllow: /\n", 200);
            }

            return Http::response(
                '<html><body><h1>ok</h1></body></html>',
                200,
                ['Content-Type' => 'text/html']
            );
        });

        $lead = Lead::factory()->create([
            'website' => 'https://empresa-demo.es',
            'website_dominio' => 'empresa-demo.es',
            'sector' => null,
            'estado' => 'nuevo',
        ]);

        $resultado = $this->app->make(RastreadorSitio::class)
            ->rastrear($lead, PropositoRastreo::AUDITORIA);

        $this->assertSame(2, $resultado->paginasVisitadas);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/reservas'));
    }

    public function test_un_timeout_se_recupera_en_el_segundo_intento(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://empresa-demo.es/contacto' => Http::response(
                '<html><body>contacto</body></html>', 200, ['Content-Type' => 'text/html']
            ),
            'https://empresa-demo.es' => Http::sequence()
                ->pushFailedConnection('cURL error 28: Operation timed out after 10001 milliseconds')
                ->push($this->htmlConEmails(), 200, ['Content-Type' => 'text/html']),
        ]);

        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertNull($resultado->falloTipo);
        $this->assertNull($lead->fresh()->rastreo_fallido_at);

        $home = Pagina::query()->where('lead_id', $lead->id)->where('url', 'https://empresa-demo.es')->first();
        $this->assertNotNull($home);
        $this->assertSame(200, $home->http_status);
        $this->assertSame(2, $home->intentos);

        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn (CarbonInterval $espera): bool => (int) $espera->totalSeconds === 1);
    }

    public function test_un_429_espera_lo_que_pide_retry_after(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://empresa-demo.es/contacto' => Http::response(
                '<html><body>contacto</body></html>', 200, ['Content-Type' => 'text/html']
            ),
            'https://empresa-demo.es' => Http::sequence()
                ->push('Slow down', 429, ['Retry-After' => '7'])
                ->push($this->htmlConEmails(), 200, ['Content-Type' => 'text/html']),
        ]);

        $lead = $this->leadDemo();

        $this->app->make(RastreadorSitio::class)->rastrear($lead);

        // 7 y no 1: manda la cabecera, no el backoff.
        Sleep::assertSleptTimes(1);
        Sleep::assertSlept(fn (CarbonInterval $espera): bool => (int) $espera->totalSeconds === 7);

        $home = Pagina::query()->where('lead_id', $lead->id)->where('url', 'https://empresa-demo.es')->first();
        $this->assertNotNull($home);
        $this->assertSame(2, $home->intentos);
    }

    public function test_un_404_no_se_reintenta(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://empresa-demo.es' => Http::response(
                $this->htmlConEmails(), 200, ['Content-Type' => 'text/html']
            ),
            'https://empresa-demo.es/contacto' => Http::response(
                'Not Found', 404, ['Content-Type' => 'text/html']
            ),
        ]);

        $lead = $this->leadDemo();

        $this->app->make(RastreadorSitio::class)->rastrear($lead);

        Http::assertSentCount(3);
        Sleep::assertNeverSlept();

        $contacto = Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/contacto')->first();
        $this->assertNotNull($contacto);
        $this->assertSame(1, $contacto->intentos);
        $this->assertSame('http', $contacto->fallo_tipo);
    }

    public function test_home_caida_marca_el_lead_y_no_crea_paginas(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            '*' => Http::failedConnection('cURL error 6: Could not resolve host: empresa-demo.es'),
        ]);

        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertSame('dns', $resultado->falloTipo);
        $this->assertTrue($resultado->fallido());
        $this->assertSame(0, Pagina::query()->where('lead_id', $lead->id)->count());
        $this->assertNotNull($lead->fresh()->rastreo_fallido_at);

        // Con el host inaccesible no se insiste con el resto de rutas.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/contacto'));
    }

    public function test_una_home_con_404_tampoco_deja_fila_de_pagina(): void
    {
        Http::fake([
            'https://empresa-demo.es/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://empresa-demo.es' => Http::response('No existe', 404, ['Content-Type' => 'text/html']),
            'https://empresa-demo.es/contacto' => Http::response(
                '<html><body><a href="mailto:info@empresa-demo.es">x</a></body></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $lead = $this->leadDemo();

        $resultado = $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertSame('http', $resultado->falloTipo);
        $this->assertNotNull($lead->fresh()->rastreo_fallido_at);
        $this->assertSame(0, Pagina::query()->where('lead_id', $lead->id)->where('url', 'https://empresa-demo.es')->count());

        // El servidor responde, así que el resto de rutas sí se visitan.
        $this->assertSame(1, Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/contacto')->count());
    }

    public function test_un_rastreo_correcto_limpia_la_marca_de_fallo(): void
    {
        $this->fakeSitioOk();
        $lead = $this->leadDemo();
        $lead->forceFill(['rastreo_fallido_at' => now()->subDay()])->save();

        $this->app->make(RastreadorSitio::class)->rastrear($lead);

        $this->assertNull($lead->fresh()->rastreo_fallido_at);
    }

    public function test_las_peticiones_llevan_la_cabecera_from_del_user_agent(): void
    {
        config(['outreach.scraper.user_agent' => 'SilgoDevBot/2.0 (contacto@silgodev.es; +https://silgodev.es)']);
        $this->fakeSitioOk();

        $this->app->make(RastreadorSitio::class)->rastrear($this->leadDemo());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://empresa-demo.es'
            && $request->header('From') === ['contacto@silgodev.es']);
    }
}
