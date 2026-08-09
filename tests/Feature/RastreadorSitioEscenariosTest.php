<?php

namespace Tests\Feature;

use App\DTO\ResultadoRastreo;
use App\Enums\PropositoRastreo;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Soporte\ComprobadorRobots;
use App\Services\Web\RastreadorSitio;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Escenarios de extremo a extremo sobre las rutas reales de config/outreach.php:
 * cada uno afirma tanto lo que queda en base de datos como el ResultadoRastreo.
 */
class RastreadorSitioEscenariosTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://empresa-demo.es';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Sleep::fake();
        RateLimiter::clear('scrape:global');
        RateLimiter::clear('scrape:host:empresa-demo.es');
        // Las cuotas reales (20/min, 4/dominio) meterían esperas activas de
        // segundos en escenarios de 10 páginas. Las rutas sí son las de config.
        config([
            'outreach.scraper.peticiones_por_minuto' => 1000,
            'outreach.scraper.peticiones_por_dominio_por_minuto' => 1000,
        ]);
        $this->app->forgetInstance(ComprobadorRobots::class);
    }

    private function lead(?string $sector = null): Lead
    {
        return Lead::factory()->create([
            'website' => self::BASE,
            'website_dominio' => 'empresa-demo.es',
            'sector' => $sector,
            'estado' => 'nuevo',
        ]);
    }

    private function rastrear(Lead $lead, PropositoRastreo $proposito): ResultadoRastreo
    {
        return $this->app->make(RastreadorSitio::class)->rastrear($lead, $proposito);
    }

    private function html(string $cuerpo): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
            .'<title>Empresa Demo</title></head><body>'.$cuerpo.'</body></html>';
    }

    /** @param  callable(Request): mixed  $responder */
    private function fakeSitio(callable $responder, string $robots = "User-agent: *\nAllow: /\n"): void
    {
        Http::fake(function (Request $request) use ($responder, $robots) {
            if (str_contains($request->url(), 'robots.txt')) {
                return Http::response($robots, 200);
            }

            return $responder($request);
        });
    }

    private function respuestaHtml(string $cuerpo): PromiseInterface
    {
        return Http::response($this->html($cuerpo), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Rutas pedidas al sitio, sin contar robots.txt, en el orden en que se pidieron.
     *
     * @return list<string>
     */
    private function rutasPedidas(): array
    {
        $rutas = [];

        foreach (Http::recorded() as [$peticion]) {
            $url = $peticion->url();

            if (str_contains($url, 'robots.txt')) {
                continue;
            }

            $rutas[] = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        }

        return $rutas;
    }

    // 1. EMAIL recolecta y se conforma con 6 rutas; AUDITORIA barre el sitio.

    public function test_email_visita_como_mucho_seis_rutas_y_guarda_el_correo(): void
    {
        $this->fakeSitio(fn (Request $r) => $this->respuestaHtml(
            str_contains($r->url(), '/contacto')
                ? '<h1>Contacto</h1><a href="mailto:info@empresa-demo.es">info</a>'
                : '<h1>Empresa Demo</h1><a href="mailto:info@empresa-demo.es">info</a>'
        ));

        $lead = $this->lead('hosteleria');

        $resultado = $this->rastrear($lead, PropositoRastreo::EMAIL);

        $this->assertSame(PropositoRastreo::EMAIL, $resultado->proposito);
        $this->assertNull($resultado->falloTipo);
        $this->assertLessThanOrEqual(6, $resultado->paginasVisitadas);
        $this->assertLessThanOrEqual(6, Pagina::query()->where('lead_id', $lead->id)->count());

        // rutas.email tiene 12 entradas y max_paginas.email son 6: el resto ni se pide.
        $this->assertNotEmpty(array_filter(
            $resultado->rutasOmitidas,
            fn (string $o): bool => str_contains($o, 'fuera del límite de max_paginas')
        ));

        $this->assertSame(1, $resultado->emailsGuardados);
        $this->assertDatabaseHas('lead_emails', [
            'lead_id' => $lead->id,
            'email' => 'info@empresa-demo.es',
            'es_principal' => true,
        ]);
    }

    public function test_auditoria_no_corta_aunque_la_home_traiga_tres_correos_de_rol(): void
    {
        $home = '<h1>Empresa Demo</h1>'
            .'<a href="mailto:info@empresa-demo.es">info</a>'
            .'<a href="mailto:contacto@empresa-demo.es">contacto</a>'
            .'<a href="mailto:reservas@empresa-demo.es">reservas</a>'
            .'<a href="/reserva-online">Reservar mesa</a>';

        $this->fakeSitio(fn (Request $r) => $this->respuestaHtml(
            $r->url() === self::BASE ? $home : '<h1>Página</h1>'
        ));

        $lead = $this->lead('hosteleria');

        $resultado = $this->rastrear($lead, PropositoRastreo::AUDITORIA);

        $rutas = $this->rutasPedidas();

        // Comunes + las del sector hostelería, todas visitadas pese a los correos.
        foreach (['', '/contacto', '/aviso-legal', '/politica-de-privacidad',
            '/reservas', '/reservar', '/carta', '/menu', '/la-carta'] as $ruta) {
            $this->assertContains($ruta, $rutas, "no se pidió {$ruta}");
        }

        // Y la descubierta en la home.
        $this->assertContains('/reserva-online', $rutas);

        $this->assertSame([], array_filter(
            $resultado->rutasOmitidas,
            fn (string $o): bool => str_contains($o, 'corte anticipado')
        ));
        $this->assertSame(10, $resultado->paginasVisitadas);
        $this->assertSame(10, Pagina::query()->where('lead_id', $lead->id)->count());
        $this->assertNull($resultado->falloTipo);
    }

    // 2. La ruta de reservas no está en la lista fija: hay que descubrirla.

    public function test_descubre_reserva_online_y_deja_tiene_reservas_a_true(): void
    {
        $home = '<h1>Casa Marés</h1><nav>'
            .'<a href="/reserva-online">Reservar mesa</a>'
            .'<a href="/contacto">Contacto</a>'
            .'</nav>';

        $this->fakeSitio(function (Request $r) use ($home) {
            if ($r->url() === self::BASE) {
                return $this->respuestaHtml($home);
            }

            if (str_contains($r->url(), '/reserva-online')) {
                return $this->respuestaHtml(
                    '<h1>Reservar mesa</h1><p>Reserva tu mesa con CoverManager.</p>'
                );
            }

            return $this->respuestaHtml('<h1>Página</h1>');
        });

        $lead = $this->lead('hosteleria');

        $resultado = $this->rastrear($lead, PropositoRastreo::AUDITORIA);

        $descubierta = Pagina::query()
            ->where('lead_id', $lead->id)
            ->where('ruta', '/reserva-online')
            ->first();

        $this->assertNotNull($descubierta, 'la ruta descubierta no se guardó');
        $this->assertSame(200, $descubierta->http_status);
        $this->assertTrue($descubierta->tiene_reservas);
        $this->assertSame('descubierta', $descubierta->origen_ruta);
        $this->assertSame('auditoria', $descubierta->proposito);

        $home = Pagina::query()->where('lead_id', $lead->id)->where('url', self::BASE)->first();
        $this->assertNotNull($home);
        $this->assertSame('fija', $home->origen_ruta);

        $paginas = Pagina::query()->where('lead_id', $lead->id)->get();
        $this->assertTrue(Pagina::señal($paginas, 'tiene_reservas', $lead->sector));
        $this->assertTrue($home->enlaces_senal['reservas'] ?? null, 'la home enlazaba a reservas');

        $this->assertContains('/reserva-online', $this->rutasPedidas());
        $this->assertNull($resultado->falloTipo);
        $this->assertSame($resultado->paginasVisitadas, $paginas->count());
    }

    // 3. robots.txt manda: la ruta prohibida ni se pide.

    public function test_robots_prohibe_tienda_y_el_carrito_queda_sin_observar(): void
    {
        $this->fakeSitio(
            fn (Request $r) => $this->respuestaHtml('<h1>Bazar Norte</h1><p>Artículos para el hogar.</p>'),
            "User-agent: *\nDisallow: /tienda\n"
        );

        $lead = $this->lead('retail');

        $resultado = $this->rastrear($lead, PropositoRastreo::AUDITORIA);

        $this->assertContains(
            '/tienda: bloqueada por robots.txt',
            $resultado->rutasOmitidas
        );
        $this->assertNotContains('/tienda', $this->rutasPedidas());
        $this->assertSame(
            0,
            Pagina::query()->where('lead_id', $lead->id)->where('ruta', '/tienda')->count()
        );

        // Ninguna página vio un carrito: la columna queda en null, nunca en false.
        $paginas = Pagina::query()->where('lead_id', $lead->id)->get();
        $this->assertGreaterThan(0, $paginas->count());
        foreach ($paginas as $pagina) {
            $this->assertNull($pagina->tiene_carrito, "{$pagina->ruta} escribió un false");
        }
    }

    // 4. Una cadena de redirecciones que acaba dentro de la red privada.

    public function test_tres_redirecciones_a_otro_dominio_las_para_el_guardia(): void
    {
        $historial = implode(',', [
            'https://empresa-demo.es/uno',
            'https://redirector.example/dos',
            'http://192.168.10.5/panel',
        ]);

        $this->fakeSitio(fn (Request $r) => Http::response(
            $this->html('<h1>Panel interno</h1>'),
            200,
            ['Content-Type' => 'text/html', 'X-Guzzle-Redirect-History' => $historial]
        ));

        $lead = $this->lead('hosteleria');

        $resultado = $this->rastrear($lead, PropositoRastreo::AUDITORIA);

        $this->assertSame(0, Pagina::query()->where('lead_id', $lead->id)->count());
        $this->assertSame(0, $resultado->paginasGuardadas);
        $this->assertGreaterThan(0, $resultado->paginasVisitadas);
        $this->assertNotEmpty($resultado->errores);
        $this->assertStringContainsString('192.168.10.5', implode(' ', $resultado->errores));

        // La home nunca se obtuvo: el lead queda marcado y el motivo es el guardia.
        $this->assertSame('guardia', $resultado->falloTipo);
        $this->assertTrue($resultado->fallido());
        $this->assertNotNull($lead->fresh()->rastreo_fallido_at);
    }

    // 5. Home viva y el resto caído: se guarda el fallo, pero nadie miró una
    // página de reservas, así que la señal no se puede negar.

    public function test_home_ok_y_resto_404_guarda_el_fallo_sin_negar_las_reservas(): void
    {
        $this->fakeSitio(function (Request $r) {
            if ($r->url() === self::BASE) {
                return $this->respuestaHtml('<h1>Empresa Demo</h1><p>Cocina de mercado.</p>');
            }

            return Http::response('No existe', 404, ['Content-Type' => 'text/html']);
        });

        $lead = $this->lead('hosteleria');

        $resultado = $this->rastrear($lead, PropositoRastreo::AUDITORIA);

        $paginas = Pagina::query()->where('lead_id', $lead->id)->get();
        $caidas = $paginas->where('http_status', 404);

        $this->assertGreaterThan(0, $caidas->count());
        foreach ($caidas as $pagina) {
            $this->assertSame('http', $pagina->fallo_tipo);
            $this->assertSame('HTTP 404', $pagina->error);
            $this->assertSame(1, $pagina->intentos, 'un 404 no se reintenta');
        }

        // La home sí se parseó: el rastreo no es fallido.
        $this->assertNull($resultado->falloTipo);
        $this->assertNull($lead->fresh()->rastreo_fallido_at);
        $this->assertSame($paginas->count(), $resultado->paginasGuardadas);

        // Un 404 no demuestra nada: la única página sana es la home, que no es
        // donde viven las reservas. Sin cobertura sectorial, no se afirma.
        $this->assertNull(Pagina::señal($paginas, 'tiene_reservas', $lead->sector));
        $this->assertSame(
            Pagina::MOTIVO_SIN_RUTA_SECTORIAL,
            Pagina::motivoSenal($paginas, 'tiene_reservas', $lead->sector)
        );
    }
}
