<?php

namespace Tests\Unit\Comprobaciones;

use App\Enums\Certeza;
use App\Enums\PropositoRastreo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\Comprobaciones\HtmlPesado;
use App\Services\Auditoria\Comprobaciones\PaginaContactoRota;
use App\Services\Auditoria\Comprobaciones\PsiAccesibilidadBaja;
use App\Services\Auditoria\Comprobaciones\PsiLcpLento;
use App\Services\Auditoria\Comprobaciones\PsiPesoExcesivo;
use App\Services\Auditoria\Comprobaciones\PsiRendimientoMalo;
use App\Services\Auditoria\Comprobaciones\PsiSeoBajo;
use App\Services\Auditoria\Comprobaciones\RespuestaLenta;
use App\Services\Auditoria\Comprobaciones\SinAvisoLegal;
use App\Services\Auditoria\Comprobaciones\SinCarrito;
use App\Services\Auditoria\Comprobaciones\SinCookies;
use App\Services\Auditoria\Comprobaciones\SinFormularioContacto;
use App\Services\Auditoria\Comprobaciones\SinRedesSociales;
use App\Services\Auditoria\Comprobaciones\SinReservas;
use App\Services\Auditoria\Comprobaciones\SinWhatsapp;
use Tests\TestCase;

class ComprobacionesSectorialesTest extends TestCase
{
    private function lead(?string $sector = null): Lead
    {
        return $sector === null
            ? Lead::factory()->make()
            : Lead::factory()->conSector($sector)->make();
    }

    private function home(array $attrs = []): Pagina
    {
        return Pagina::factory()->make(array_merge([
            'lead_id' => 1,
            'ruta' => '/',
            // Sin propósito de auditoría, las señales de ausencia no se pueden
            // afirmar y las comprobaciones OBSERVADO callan.
            'proposito' => PropositoRastreo::AUDITORIA->value,
            'origen_ruta' => 'fija',
            'tiene_aviso_legal' => true,
            'tiene_privacidad' => true,
            'tiene_cookies' => true,
            'redes_sociales' => ['instagram' => 'https://instagram.com/x'],
            'tiene_formulario' => true,
            'tiene_reservas' => true,
            'tiene_carrito' => true,
            'tiene_whatsapp' => true,
            'bytes' => 50_000,
            'respuesta_ms' => 800,
            'http_status' => 200,
        ], $attrs));
    }

    /**
     * Página hallada entre los enlaces de la home. Es la que da cobertura a las
     * señales sectoriales sea cual sea el sector del lead.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function descubierta(array $attrs = []): Pagina
    {
        return Pagina::factory()->make(array_merge([
            'lead_id' => 1,
            'ruta' => '/mas-informacion',
            'proposito' => PropositoRastreo::AUDITORIA->value,
            'origen_ruta' => Pagina::ORIGEN_DESCUBIERTA,
            'http_status' => 200,
            'tiene_formulario' => null,
            'tiene_reservas' => null,
            'tiene_carrito' => null,
            'tiene_whatsapp' => null,
        ], $attrs));
    }

    public function test_sin_reservas_aplica_a_hosteleria(): void
    {
        $this->assertContains('hosteleria', (new SinReservas)->sectores());

        $paginas = collect([$this->home(['tiene_reservas' => null]), $this->descubierta()]);
        $h = (new SinReservas)->evaluar($this->lead('hosteleria'), $paginas, null);

        $this->assertNotNull($h);
        $this->assertSame(Certeza::OBSERVADO, $h->certeza);
        $this->assertNotNull($h->evidencia);
    }

    /**
     * Test 1 del encargo: /reservas dio 404 y la home no enlaza a ninguna
     * reserva. Nadie ha mirado nunca una página de reservas.
     */
    public function test_sin_reservas_calla_si_la_ruta_de_reservas_esta_caida(): void
    {
        $paginas = collect([
            $this->home([
                'tiene_reservas' => null,
                'enlaces_senal' => ['reservas' => false, 'carrito' => false],
            ]),
            $this->descubierta([
                'ruta' => '/contacto',
                'origen_ruta' => 'fija',
            ]),
            $this->descubierta([
                'ruta' => '/reservas',
                'origen_ruta' => 'fija',
                'http_status' => 404,
                'fallo_tipo' => 'http',
            ]),
        ]);

        $this->assertNull((new SinReservas)->evaluar($this->lead('hosteleria'), $paginas, null));
    }

    /** Test 2 del encargo: la home enlaza «Reservar mesa» a una ruta sin visitar. */
    public function test_sin_reservas_calla_si_la_home_enlaza_a_reservas(): void
    {
        $paginas = collect([
            $this->home([
                'tiene_reservas' => null,
                'enlaces_senal' => ['reservas' => true, 'carrito' => false],
            ]),
            $this->descubierta(['ruta' => '/contacto', 'origen_ruta' => 'fija']),
        ]);

        $this->assertNull((new SinReservas)->evaluar($this->lead('hosteleria'), $paginas, null));
    }

    /**
     * Test 3 del encargo: /carta es ruta de hostelería y se leyó entera, así que
     * la ausencia se puede afirmar y la evidencia nombra dónde se buscó.
     */
    public function test_sin_reservas_emite_observado_nombrando_las_rutas_miradas(): void
    {
        $paginas = collect([
            $this->home(['tiene_reservas' => null, 'enlaces_senal' => ['reservas' => false]]),
            $this->descubierta(['ruta' => '/carta', 'origen_ruta' => 'fija']),
        ]);

        $h = (new SinReservas)->evaluar($this->lead('hosteleria'), $paginas, null);

        $this->assertNotNull($h);
        $this->assertSame(Certeza::OBSERVADO, $h->certeza);
        $this->assertStringContainsString('buscado en /, /carta', (string) $h->evidencia);
    }

    public function test_la_evidencia_no_nombra_las_paginas_caidas(): void
    {
        $paginas = collect([
            $this->home(['tiene_reservas' => null]),
            $this->descubierta(['ruta' => '/carta', 'origen_ruta' => 'fija']),
            $this->descubierta([
                'ruta' => '/reservas',
                'origen_ruta' => 'fija',
                'http_status' => 404,
                'fallo_tipo' => 'http',
            ]),
        ]);

        $h = (new SinReservas)->evaluar($this->lead('hosteleria'), $paginas, null);

        $this->assertNotNull($h);
        $this->assertStringNotContainsString('/reservas', (string) $h->evidencia);
    }

    /**
     * El caso que motivó todo esto: si solo se rastreó buscando correos, nadie
     * miró la página de reservas y afirmar que no las hay es inventárselo.
     */
    public function test_sin_reservas_calla_si_no_se_visito_ninguna_pagina_de_auditoria(): void
    {
        $paginas = collect([$this->home([
            'tiene_reservas' => null,
            'proposito' => PropositoRastreo::EMAIL->value,
        ])]);

        $this->assertNull((new SinReservas)->evaluar($this->lead(), $paginas, null));
    }

    public function test_sin_reservas_calla_si_las_paginas_no_registran_proposito(): void
    {
        $paginas = collect([$this->home(['tiene_reservas' => null, 'proposito' => null])]);

        $this->assertNull((new SinReservas)->evaluar($this->lead(), $paginas, null));
    }

    public function test_las_comprobaciones_de_ausencia_callan_sin_cobertura(): void
    {
        $sinCobertura = fn (string $campo) => collect([$this->home([
            $campo => null,
            'proposito' => PropositoRastreo::EMAIL->value,
        ])]);

        $this->assertNull((new SinCarrito)->evaluar($this->lead(), $sinCobertura('tiene_carrito'), null));
        $this->assertNull((new SinFormularioContacto)->evaluar($this->lead(), $sinCobertura('tiene_formulario'), null));
        $this->assertNull((new SinWhatsapp)->evaluar($this->lead(), $sinCobertura('tiene_whatsapp'), null));
    }

    public function test_las_comprobaciones_de_ausencia_son_observado(): void
    {
        $conCobertura = fn (string $campo) => collect([
            $this->home([$campo => null]),
            $this->descubierta(),
        ]);

        foreach ([
            [new SinReservas, 'tiene_reservas'],
            [new SinCarrito, 'tiene_carrito'],
            [new SinFormularioContacto, 'tiene_formulario'],
            [new SinWhatsapp, 'tiene_whatsapp'],
        ] as [$comprobacion, $campo]) {
            $h = $comprobacion->evaluar($this->lead(), $conCobertura($campo), null);

            $this->assertNotNull($h, $campo);
            $this->assertSame(Certeza::OBSERVADO, $h->certeza, $campo);
            $this->assertStringContainsString('buscado en /', (string) $h->evidencia, $campo);
        }
    }

    public function test_las_comprobaciones_de_ausencia_callan_sin_ruta_donde_mirar(): void
    {
        $soloHome = fn (string $campo) => collect([$this->home([$campo => null])]);

        $this->assertNull((new SinReservas)->evaluar($this->lead('hosteleria'), $soloHome('tiene_reservas'), null));
        $this->assertNull((new SinCarrito)->evaluar($this->lead('retail'), $soloHome('tiene_carrito'), null));
        $this->assertNull((new SinFormularioContacto)->evaluar($this->lead('oficios'), $soloHome('tiene_formulario'), null));
        $this->assertNull((new SinWhatsapp)->evaluar($this->lead('belleza'), $soloHome('tiene_whatsapp'), null));
    }

    public function test_sin_reservas_no_aplica_a_retail(): void
    {
        $this->assertNotContains('retail', (new SinReservas)->sectores() ?? []);
    }

    public function test_sin_carrito_solo_retail(): void
    {
        $this->assertSame(['retail'], (new SinCarrito)->sectores());

        $h = (new SinCarrito)->evaluar(
            $this->lead('retail'),
            collect([$this->home(['tiene_carrito' => null]), $this->descubierta()]),
            null
        );
        $this->assertNotNull($h);
        $this->assertNull(
            (new SinCarrito)->evaluar(
                $this->lead('retail'),
                collect([$this->home(['tiene_carrito' => true]), $this->descubierta()]),
                null
            )
        );
    }

    public function test_psi_lcp_calcula_segundos(): void
    {
        $auditoria = Auditoria::factory()->make(['lead_id' => 1, 'psi_lcp_ms' => 5200]);
        $h = (new PsiLcpLento)->evaluar($this->lead(), collect([$this->home()]), $auditoria);
        $this->assertNotNull($h);
        $this->assertSame(5.2, $h->datos['segundos']);
        $this->assertSame(Certeza::HECHO, $h->certeza);
    }

    public function test_psi_no_se_evalua_sin_datos(): void
    {
        $auditoria = Auditoria::factory()->make(['lead_id' => 1, 'psi_lcp_ms' => null]);
        $this->assertNull(
            (new PsiLcpLento)->evaluar($this->lead(), collect([$this->home()]), $auditoria)
        );
        $this->assertNull(
            (new PsiLcpLento)->evaluar($this->lead(), collect([$this->home()]), null)
        );
    }

    public function test_sin_aviso_legal_detecta_cuando_falta(): void
    {
        $h = (new SinAvisoLegal)->evaluar($this->lead(), collect([$this->home([
            'tiene_aviso_legal' => null,
            'tiene_privacidad' => null,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame(Certeza::INDICIO, $h->certeza);
    }

    public function test_las_comprobaciones_legales_son_indicio(): void
    {
        $cookies = (new SinCookies)->evaluar($this->lead(), collect([$this->home(['tiene_cookies' => null])]), null);
        $redes = (new SinRedesSociales)->evaluar($this->lead(), collect([$this->home(['redes_sociales' => []])]), null);

        $this->assertNotNull($cookies);
        $this->assertNotNull($redes);
        $this->assertSame(Certeza::INDICIO, $cookies->certeza);
        $this->assertSame(Certeza::INDICIO, $redes->certeza);
    }

    public function test_sin_aviso_legal_null_cuando_existe(): void
    {
        $this->assertNull(
            (new SinAvisoLegal)->evaluar($this->lead(), collect([$this->home()]), null)
        );
    }

    public function test_sin_cookies_detecta_cuando_falta(): void
    {
        $this->assertNotNull(
            (new SinCookies)->evaluar($this->lead(), collect([$this->home(['tiene_cookies' => false])]), null)
        );
    }

    public function test_sin_cookies_null_cuando_existe(): void
    {
        $this->assertNull(
            (new SinCookies)->evaluar($this->lead(), collect([$this->home()]), null)
        );
    }

    public function test_sin_redes_detecta_cuando_falta(): void
    {
        $this->assertNotNull(
            (new SinRedesSociales)->evaluar($this->lead(), collect([$this->home(['redes_sociales' => []])]), null)
        );
    }

    public function test_sin_redes_null_cuando_existe(): void
    {
        $this->assertNull(
            (new SinRedesSociales)->evaluar($this->lead(), collect([$this->home()]), null)
        );
    }

    public function test_sin_formulario_detecta_cuando_falta(): void
    {
        $this->assertNotNull(
            (new SinFormularioContacto)->evaluar(
                $this->lead(),
                collect([$this->home(['tiene_formulario' => null]), $this->descubierta()]),
                null
            )
        );
    }

    public function test_sin_formulario_null_cuando_existe(): void
    {
        $this->assertNull(
            (new SinFormularioContacto)->evaluar($this->lead(), collect([$this->home()]), null)
        );
    }

    public function test_contacto_roto_detecta_404(): void
    {
        $h = (new PaginaContactoRota)->evaluar($this->lead(), collect([
            $this->home(),
            Pagina::factory()->make(['lead_id' => 1, 'ruta' => '/contacto', 'http_status' => 404]),
        ]), null);
        $this->assertNotNull($h);
        $this->assertSame(404, $h->datos['status']);
        $this->assertSame(Certeza::HECHO, $h->certeza);
        $this->assertStringContainsString('404', (string) $h->evidencia);
    }

    public function test_contacto_roto_null_si_ok(): void
    {
        $this->assertNull(
            (new PaginaContactoRota)->evaluar($this->lead(), collect([
                $this->home(),
                Pagina::factory()->make(['lead_id' => 1, 'ruta' => '/contacto', 'http_status' => 200]),
            ]), null)
        );
    }

    public function test_sin_reservas_null_cuando_existe(): void
    {
        $this->assertNull(
            (new SinReservas)->evaluar($this->lead(), collect([$this->home(['tiene_reservas' => true])]), null)
        );
    }

    public function test_sin_whatsapp_detecta_y_sectores(): void
    {
        $this->assertSame(['oficios', 'belleza'], (new SinWhatsapp)->sectores());
        $this->assertNotNull(
            (new SinWhatsapp)->evaluar(
                $this->lead('belleza'),
                collect([$this->home(['tiene_whatsapp' => null]), $this->descubierta()]),
                null
            )
        );
        $this->assertNull(
            (new SinWhatsapp)->evaluar(
                $this->lead('belleza'),
                collect([$this->home(['tiene_whatsapp' => true]), $this->descubierta()]),
                null
            )
        );
    }

    public function test_html_pesado_detecta_cuando_excede(): void
    {
        $umbralKb = (int) config('outreach.auditoria.umbral_html_kb');
        $h = (new HtmlPesado)->evaluar($this->lead(), collect([$this->home([
            'bytes' => ($umbralKb + 100) * 1024,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertArrayHasKey('kb', $h->datos);
    }

    public function test_html_pesado_null_cuando_ligero(): void
    {
        $this->assertNull(
            (new HtmlPesado)->evaluar($this->lead(), collect([$this->home(['bytes' => 40_000])]), null)
        );
    }

    public function test_respuesta_lenta_detecta_cuando_excede(): void
    {
        $umbral = (int) config('outreach.auditoria.umbral_respuesta_ms');
        $h = (new RespuestaLenta)->evaluar($this->lead(), collect([$this->home([
            'respuesta_ms' => $umbral + 500,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame($umbral + 500, $h->datos['ms']);
        $this->assertSame(Certeza::HECHO, $h->certeza);
    }

    public function test_respuesta_lenta_null_cuando_rapida(): void
    {
        $this->assertNull(
            (new RespuestaLenta)->evaluar($this->lead(), collect([$this->home(['respuesta_ms' => 500])]), null)
        );
    }

    public function test_psi_rendimiento_malo_detecta(): void
    {
        $auditoria = Auditoria::factory()->make(['lead_id' => 1, 'psi_rendimiento' => 20]);
        $this->assertNotNull(
            (new PsiRendimientoMalo)->evaluar($this->lead(), collect([$this->home()]), $auditoria)
        );
    }

    public function test_psi_peso_excesivo_detecta(): void
    {
        $auditoria = Auditoria::factory()->make(['lead_id' => 1, 'psi_peso_kb' => 4096]);
        $h = (new PsiPesoExcesivo)->evaluar($this->lead(), collect([$this->home()]), $auditoria);
        $this->assertNotNull($h);
        $this->assertSame(4.0, $h->datos['mb']);
        $this->assertSame(Certeza::HECHO, $h->certeza);
    }

    public function test_psi_seo_bajo_detecta(): void
    {
        $auditoria = Auditoria::factory()->make(['lead_id' => 1, 'psi_seo' => 40]);
        $this->assertNotNull(
            (new PsiSeoBajo)->evaluar($this->lead(), collect([$this->home()]), $auditoria)
        );
    }

    public function test_psi_accesibilidad_baja_detecta(): void
    {
        $auditoria = Auditoria::factory()->make(['lead_id' => 1, 'psi_accesibilidad' => 50]);
        $this->assertNotNull(
            (new PsiAccesibilidadBaja)->evaluar($this->lead(), collect([$this->home()]), $auditoria)
        );
        $this->assertNull(
            (new PsiAccesibilidadBaja)->evaluar(
                $this->lead(),
                collect([$this->home()]),
                Auditoria::factory()->make(['lead_id' => 1, 'psi_accesibilidad' => 80])
            )
        );
    }
}
