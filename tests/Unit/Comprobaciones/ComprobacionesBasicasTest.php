<?php

namespace Tests\Unit\Comprobaciones;

use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\Comprobaciones\CertificadoCaduca;
use App\Services\Auditoria\Comprobaciones\GeneradorObsoleto;
use App\Services\Auditoria\Comprobaciones\H1Incorrecto;
use App\Services\Auditoria\Comprobaciones\ImagenesSinAlt;
use App\Services\Auditoria\Comprobaciones\SinHttps;
use App\Services\Auditoria\Comprobaciones\SinJsonLd;
use App\Services\Auditoria\Comprobaciones\SinMetaDescription;
use App\Services\Auditoria\Comprobaciones\SinViewport;
use App\Services\Auditoria\Comprobaciones\TitleMalo;
use App\Services\Auditoria\Comprobaciones\WebAbandonada;
use Tests\TestCase;

class ComprobacionesBasicasTest extends TestCase
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
            'tiene_viewport' => true,
            'title' => 'Título correcto de longitud media',
            'title_longitud' => 34,
            'meta_description' => str_repeat('a', 120),
            'meta_desc_longitud' => 120,
            'h1_total' => 1,
            'imagenes_total' => 2,
            'imagenes_sin_alt' => 0,
            'tiene_jsonld' => true,
            'es_https' => true,
            'cert_valido' => true,
            'cert_expira_at' => now()->addYear(),
            'anio_copyright' => (int) now()->year,
            'generador' => null,
        ], $attrs));
    }

    public function test_sin_viewport_detecta_cuando_falta(): void
    {
        $h = (new SinViewport)->evaluar($this->lead(), collect([$this->home(['tiene_viewport' => false])]), null);
        $this->assertNotNull($h);
        $this->assertSame('sin_viewport', $h->codigo);
    }

    public function test_sin_viewport_null_cuando_existe(): void
    {
        $h = (new SinViewport)->evaluar($this->lead(), collect([$this->home(['tiene_viewport' => true])]), null);
        $this->assertNull($h);
    }

    public function test_title_malo_detecta_titulo_largo(): void
    {
        $title = str_repeat('x', 80);
        $h = (new TitleMalo)->evaluar($this->lead(), collect([$this->home([
            'title' => $title,
            'title_longitud' => 80,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame(80, $h->datos['longitud']);
    }

    public function test_title_malo_null_con_titulo_correcto(): void
    {
        $h = (new TitleMalo)->evaluar($this->lead(), collect([$this->home()]), null);
        $this->assertNull($h);
    }

    public function test_sin_meta_description_detecta_cuando_falta(): void
    {
        $h = (new SinMetaDescription)->evaluar($this->lead(), collect([$this->home([
            'meta_description' => null,
            'meta_desc_longitud' => null,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame('sin_meta_description', $h->codigo);
    }

    public function test_sin_meta_description_null_cuando_existe(): void
    {
        $h = (new SinMetaDescription)->evaluar($this->lead(), collect([$this->home()]), null);
        $this->assertNull($h);
    }

    public function test_h1_incorrecto_detecta_sin_h1(): void
    {
        $h = (new H1Incorrecto)->evaluar($this->lead(), collect([$this->home(['h1_total' => 0])]), null);
        $this->assertNotNull($h);
        $this->assertSame(0, $h->datos['total']);
    }

    public function test_h1_incorrecto_null_con_un_h1(): void
    {
        $h = (new H1Incorrecto)->evaluar($this->lead(), collect([$this->home(['h1_total' => 1])]), null);
        $this->assertNull($h);
    }

    public function test_imagenes_sin_alt_detecta_ratio_alto(): void
    {
        $h = (new ImagenesSinAlt)->evaluar($this->lead(), collect([$this->home([
            'imagenes_total' => 10,
            'imagenes_sin_alt' => 6,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame(60, $h->datos['porcentaje']);
    }

    public function test_imagenes_sin_alt_null_con_ratio_bajo(): void
    {
        $h = (new ImagenesSinAlt)->evaluar($this->lead(), collect([$this->home([
            'imagenes_total' => 10,
            'imagenes_sin_alt' => 2,
        ])]), null);
        $this->assertNull($h);
    }

    public function test_sin_jsonld_detecta_cuando_falta(): void
    {
        $h = (new SinJsonLd)->evaluar($this->lead(), collect([
            $this->home(['tiene_jsonld' => false]),
            Pagina::factory()->make(['lead_id' => 1, 'ruta' => '/contacto', 'tiene_jsonld' => false]),
        ]), null);
        $this->assertNotNull($h);
    }

    public function test_sin_jsonld_null_cuando_alguna_tiene(): void
    {
        $h = (new SinJsonLd)->evaluar($this->lead(), collect([
            $this->home(['tiene_jsonld' => false]),
            Pagina::factory()->make(['lead_id' => 1, 'ruta' => '/contacto', 'tiene_jsonld' => true]),
        ]), null);
        $this->assertNull($h);
    }

    public function test_sin_https_detecta_http(): void
    {
        $h = (new SinHttps)->evaluar($this->lead(), collect([$this->home([
            'es_https' => false,
            'cert_valido' => null,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame('sin_https', $h->codigo);
    }

    public function test_sin_https_null_con_https_valido(): void
    {
        $h = (new SinHttps)->evaluar($this->lead(), collect([$this->home([
            'es_https' => true,
            'cert_valido' => true,
        ])]), null);
        $this->assertNull($h);
    }

    public function test_certificado_caduca_detecta_proximo(): void
    {
        $h = (new CertificadoCaduca)->evaluar($this->lead(), collect([$this->home([
            'cert_expira_at' => now()->addDays(10),
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame('cert_caduca', $h->codigo);
    }

    public function test_certificado_caduca_null_si_lejos(): void
    {
        $h = (new CertificadoCaduca)->evaluar($this->lead(), collect([$this->home([
            'cert_expira_at' => now()->addYear(),
        ])]), null);
        $this->assertNull($h);
    }

    public function test_web_abandonada_detecta_copyright_viejo(): void
    {
        $anio = (int) now()->year - 3;
        $h = (new WebAbandonada)->evaluar($this->lead(), collect([$this->home([
            'anio_copyright' => $anio,
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame($anio, $h->datos['anio']);
    }

    public function test_web_abandonada_null_con_copyright_reciente(): void
    {
        $h = (new WebAbandonada)->evaluar($this->lead(), collect([$this->home([
            'anio_copyright' => (int) now()->year,
        ])]), null);
        $this->assertNull($h);
    }

    public function test_generador_obsoleto_detecta_wix(): void
    {
        $h = (new GeneradorObsoleto)->evaluar($this->lead(), collect([$this->home([
            'generador' => 'wix',
        ])]), null);
        $this->assertNotNull($h);
        $this->assertSame('wix', $h->datos['generador']);
    }

    public function test_generador_obsoleto_null_con_otro(): void
    {
        $h = (new GeneradorObsoleto)->evaluar($this->lead(), collect([$this->home([
            'generador' => 'wordpress',
            'tiene_viewport' => true,
        ])]), null);
        $this->assertNull($h);
    }
}
