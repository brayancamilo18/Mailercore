<?php

namespace Tests\Unit;

use App\Services\Web\ExtractorMetadatos;
use Tests\TestCase;

class ExtractorMetadatosContenidoTest extends TestCase
{
    private ExtractorMetadatos $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new ExtractorMetadatos;
    }

    private function fixture(string $nombre): string
    {
        return file_get_contents(base_path('tests/Fixtures/html/'.$nombre));
    }

    public function test_extrae_tipos_jsonld_anidados(): void
    {
        $meta = $this->extractor->extraer(
            $this->fixture('jsonld-restaurante.html'),
            'https://ejemplo.es/'
        );

        $this->assertTrue($meta->tieneJsonld);
        $this->assertContains('Restaurant', $meta->jsonldTipos);
    }

    public function test_cuenta_imagenes_sin_alt(): void
    {
        $meta = $this->extractor->extraer($this->fixture('sin-alt.html'), 'https://ejemplo.es/');

        $this->assertSame(10, $meta->imagenesTotal);
        $this->assertSame(6, $meta->imagenesSinAlt);
    }

    public function test_separa_enlaces_internos_y_externos(): void
    {
        $html = <<<'HTML'
        <html><body>
          <a href="/contacto">Contacto</a>
          <a href="https://ejemplo.es/sobre">Sobre</a>
          <a href="https://otro.es/x">Externo</a>
          <a href="mailto:a@b.es">Mail</a>
          <a href="#ancla">Ancla</a>
        </body></html>
        HTML;

        $meta = $this->extractor->extraer($html, 'https://ejemplo.es/');

        $this->assertSame(2, $meta->enlacesInternos);
        $this->assertSame(1, $meta->enlacesExternos);
    }

    public function test_extrae_instagram(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertArrayHasKey('instagram', $meta->redesSociales);
        $this->assertStringContainsString('instagram.com/ejemplo', $meta->redesSociales['instagram']);
        $this->assertArrayNotHasKey('facebook', $meta->redesSociales);
    }

    public function test_extrae_telefono_espanol(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertNotEmpty($meta->telefonos);
        $this->assertTrue(
            collect($meta->telefonos)->contains(fn (string $t): bool => str_contains($t, '612345678'))
        );
    }

    public function test_extrae_emails_de_mailto_y_texto(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertContains('info@ejemplo.es', $meta->emailsEncontrados);
        $this->assertContains('comercial@ejemplo.es', $meta->emailsEncontrados);

        $ofuscado = $this->extractor->extraer($this->fixture('ofuscado.html'), 'https://empresa.es/');
        $this->assertContains('contacto@empresa.es', $ofuscado->emailsEncontrados);
        $this->assertNotContains('info@empresa.es', $ofuscado->emailsEncontrados);
    }

    public function test_detecta_formulario(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertTrue($meta->tieneFormulario);
    }

    public function test_detecta_carrito_en_tienda(): void
    {
        $meta = $this->extractor->extraer($this->fixture('tienda.html'), 'https://tienda.es/');

        $this->assertTrue($meta->tieneCarrito);
    }

    public function test_detecta_enlaces_legales(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertTrue($meta->tieneAvisoLegal);
        $this->assertTrue($meta->tienePrivacidad);
        $this->assertTrue($meta->tieneCookies);
    }

    public function test_anio_copyright_2019(): void
    {
        $meta = $this->extractor->extraer($this->fixture('abandonada.html'), 'https://empresa.es/');

        $this->assertSame(2019, $meta->anioCopyright);
    }

    public function test_anio_copyright_null_si_no_hay(): void
    {
        $meta = $this->extractor->extraer($this->fixture('minima.html'), 'https://ejemplo.es/');

        $this->assertNull($meta->anioCopyright);
    }

    public function test_hash_estable(): void
    {
        $html = $this->fixture('completa.html');
        $a = $this->extractor->extraer($html, 'https://ejemplo.es/');
        $b = $this->extractor->extraer($html, 'https://ejemplo.es/');

        $this->assertSame($a->htmlHash, $b->htmlHash);
        $this->assertNotEmpty($a->htmlHash);
    }
}
