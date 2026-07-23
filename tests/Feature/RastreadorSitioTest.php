<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Pagina;
use App\Services\Soporte\ComprobadorRobots;
use App\Services\Web\RastreadorSitio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RastreadorSitioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('scrape:global');
        RateLimiter::clear('scrape:host:empresa-demo.es');
        config([
            'outreach.scraper.rutas' => ['', '/contacto'],
            'outreach.scraper.max_paginas_por_sitio' => 2,
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
}
