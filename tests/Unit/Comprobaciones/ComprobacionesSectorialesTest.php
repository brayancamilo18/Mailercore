<?php

namespace Tests\Unit\Comprobaciones;

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
    private function lead(): Lead
    {
        return Lead::factory()->make();
    }

    private function home(array $attrs = []): Pagina
    {
        return Pagina::factory()->make(array_merge([
            'lead_id' => 1,
            'ruta' => '/',
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

    public function test_sin_reservas_aplica_a_hosteleria(): void
    {
        $this->assertContains('hosteleria', (new SinReservas)->sectores());
        $h = (new SinReservas)->evaluar($this->lead(), collect([$this->home(['tiene_reservas' => false])]), null);
        $this->assertNotNull($h);
    }

    public function test_sin_reservas_no_aplica_a_retail(): void
    {
        $this->assertNotContains('retail', (new SinReservas)->sectores() ?? []);
    }

    public function test_sin_carrito_solo_retail(): void
    {
        $this->assertSame(['retail'], (new SinCarrito)->sectores());
        $h = (new SinCarrito)->evaluar($this->lead(), collect([$this->home(['tiene_carrito' => false])]), null);
        $this->assertNotNull($h);
        $this->assertNull(
            (new SinCarrito)->evaluar($this->lead(), collect([$this->home(['tiene_carrito' => true])]), null)
        );
    }

    public function test_psi_lcp_calcula_segundos(): void
    {
        $auditoria = Auditoria::factory()->make(['lead_id' => 1, 'psi_lcp_ms' => 5200]);
        $h = (new PsiLcpLento)->evaluar($this->lead(), collect([$this->home()]), $auditoria);
        $this->assertNotNull($h);
        $this->assertSame(5.2, $h->datos['segundos']);
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
            'tiene_aviso_legal' => false,
            'tiene_privacidad' => false,
        ])]), null);
        $this->assertNotNull($h);
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
            (new SinFormularioContacto)->evaluar($this->lead(), collect([$this->home(['tiene_formulario' => false])]), null)
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
            (new SinWhatsapp)->evaluar($this->lead(), collect([$this->home(['tiene_whatsapp' => false])]), null)
        );
        $this->assertNull(
            (new SinWhatsapp)->evaluar($this->lead(), collect([$this->home(['tiene_whatsapp' => true])]), null)
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
