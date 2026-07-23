<?php

namespace Tests\Unit;

use App\Services\Web\ExtractorMetadatos;
use Tests\TestCase;

class ExtractorMetadatosSeoTest extends TestCase
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

    public function test_extrae_title_y_longitud(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertSame('Restaurante Ejemplo Madrid - Carta del dia!!!', $meta->title);
        $this->assertSame(45, $meta->titleLongitud);
    }

    public function test_title_null_en_pagina_minima(): void
    {
        $meta = $this->extractor->extraer($this->fixture('minima.html'), 'https://ejemplo.es/');

        $this->assertNull($meta->title);
        $this->assertNull($meta->titleLongitud);
    }

    public function test_extrae_meta_description(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertNotNull($meta->metaDescription);
        $this->assertSame(140, $meta->metaDescLongitud);
    }

    public function test_cuenta_h1_y_h2(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertSame(1, $meta->h1Total);
        $this->assertSame('Bienvenidos a Restaurante Ejemplo', $meta->h1Texto);
        $this->assertSame(3, $meta->h2Total);
    }

    public function test_detecta_idioma(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertSame('es', $meta->idioma);
    }

    public function test_detecta_viewport_presente(): void
    {
        $meta = $this->extractor->extraer($this->fixture('completa.html'), 'https://ejemplo.es/');

        $this->assertTrue($meta->tieneViewport);
    }

    public function test_detecta_viewport_ausente(): void
    {
        $meta = $this->extractor->extraer($this->fixture('wix.html'), 'https://ejemplo.wixsite.com/');

        $this->assertFalse($meta->tieneViewport);
    }

    public function test_detecta_generador_wix(): void
    {
        $meta = $this->extractor->extraer($this->fixture('wix.html'), 'https://ejemplo.wixsite.com/');

        $this->assertSame('wix', $meta->generador);
    }

    public function test_detecta_wordpress_por_wp_content(): void
    {
        $html = '<html><body><link rel="stylesheet" href="/wp-content/themes/tema/style.css"></body></html>';
        $meta = $this->extractor->extraer($html, 'https://blog.ejemplo.es/');

        $this->assertSame('wordpress', $meta->generador);
    }

    public function test_pagina_minima_no_lanza_excepcion(): void
    {
        $meta = $this->extractor->extraer($this->fixture('minima.html'), 'https://ejemplo.es/');

        $this->assertSame('https://ejemplo.es/', $meta->url);
        $this->assertNull($meta->error);
    }
}
