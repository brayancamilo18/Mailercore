<?php

namespace Tests\Unit;

use App\Services\Web\DescubridorRutas;
use Tests\TestCase;

class DescubridorRutasTest extends TestCase
{
    private DescubridorRutas $descubridor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->descubridor = new DescubridorRutas;
    }

    private function fixture(string $nombre): string
    {
        return file_get_contents(base_path('tests/Fixtures/html/'.$nombre));
    }

    public function test_restaurante_descubre_la_pagina_de_reservas(): void
    {
        $rutas = $this->descubridor->descubrir(
            $this->fixture('home-restaurante.html'),
            'https://casamanolo.es/',
            'hosteleria'
        );

        // «Reservar mesa» puntúa en href y en texto ancla, así que va primero.
        $this->assertSame('/reserva-online', $rutas[0]);
        $this->assertSame(['/reserva-online', '/nuestra-carta', '/menu-del-dia'], $rutas);
    }

    public function test_restaurante_descarta_ficheros_externos_y_rutas_fijas(): void
    {
        $rutas = $this->descubridor->descubrir(
            $this->fixture('home-restaurante.html'),
            'https://casamanolo.es/',
            'hosteleria'
        );

        $this->assertNotContains('/carta-de-vinos.pdf', $rutas);
        $this->assertNotContains('/contacto', $rutas);
        $this->assertNotContains('/aviso-legal', $rutas);
        $this->assertNotContains('', $rutas);

        foreach ($rutas as $ruta) {
            $this->assertStringStartsWith('/', $ruta);
            $this->assertStringNotContainsString('thefork', $ruta);
            $this->assertStringNotContainsString('instagram', $ruta);
        }
    }

    public function test_tienda_descubre_la_ruta_con_prefijo_de_idioma(): void
    {
        $rutas = $this->descubridor->descubrir(
            $this->fixture('home-tienda.html'),
            'https://bicicletasruiz.es/',
            'retail'
        );

        $this->assertContains('/es/tienda', $rutas);
        // «/es/tienda» y «/es/tienda/» son la misma ruta: aparece una sola vez.
        $this->assertSame(1, count(array_keys($rutas, '/es/tienda', true)));
        // «/ES/Novedades?orden=recientes» pierde mayúsculas y query.
        $this->assertContains('/es/novedades', $rutas);
        $this->assertNotContains('/catalogo-2026.pdf', $rutas);
    }

    public function test_web_sin_enlaces_utiles_no_devuelve_nada(): void
    {
        $rutas = $this->descubridor->descubrir(
            $this->fixture('home-sin-enlaces-utiles.html'),
            'https://gestorianieto.es/',
            'servicios_profesionales'
        );

        $this->assertSame([], $rutas);
    }

    public function test_devuelve_como_maximo_cuatro_rutas(): void
    {
        $html = <<<'HTML'
        <html><body>
          <a href="/reservar-mesa">Reservar mesa</a>
          <a href="/nuestra-carta">Nuestra carta</a>
          <a href="/menu-degustacion">Menú degustación</a>
          <a href="/carta-de-postres">Carta de postres</a>
          <a href="/menu-infantil">Menú infantil</a>
          <a href="/cocina-de-temporada">Cocina de temporada</a>
        </body></html>
        HTML;

        $rutas = $this->descubridor->descubrir($html, 'https://ejemplo.es/', 'hosteleria');

        $this->assertCount(4, $rutas);
    }

    public function test_si_dos_enlaces_van_a_la_misma_ruta_gana_la_mejor_puntuacion(): void
    {
        $html = <<<'HTML'
        <html><body>
          <a href="/pagina-a">Ver más</a>
          <a href="/pagina-a">Reservar mesa</a>
          <a href="/pagina-b?x=1">Reservar</a>
        </body></html>
        HTML;

        $rutas = $this->descubridor->descubrir($html, 'https://ejemplo.es/', 'hosteleria');

        // /pagina-a solo puntúa por el segundo enlace, y ese le da más que a /pagina-b.
        $this->assertSame(['/pagina-a', '/pagina-b'], $rutas);
    }

    public function test_normaliza_barras_fragmentos_y_enlaces_relativos(): void
    {
        $html = <<<'HTML'
        <html><body>
          <a href="//ejemplo.es//es///Carta/#platos">La carta</a>
          <a href="../reservas-grupos/">Reservas para grupos</a>
          <a href="https://ejemplo.es/menu-semanal/">Menú semanal</a>
        </body></html>
        HTML;

        $rutas = $this->descubridor->descubrir($html, 'https://ejemplo.es/es/inicio', 'hosteleria');

        $this->assertContains('/es/carta', $rutas);
        $this->assertContains('/reservas-grupos', $rutas);
        $this->assertContains('/menu-semanal', $rutas);
    }

    public function test_sin_sector_puntua_con_las_palabras_de_todas_las_familias(): void
    {
        $rutas = $this->descubridor->descubrir(
            $this->fixture('home-restaurante.html'),
            'https://casamanolo.es/',
            null
        );

        $this->assertContains('/reserva-online', $rutas);
    }
}
