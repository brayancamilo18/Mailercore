<?php

namespace Tests\Unit;

use App\Services\Web\DetectorEnlacesSenal;
use Tests\TestCase;

class DetectorEnlacesSenalTest extends TestCase
{
    private const BASE = 'https://restaurante-demo.es';

    public function test_reconoce_el_enlace_de_reservas_por_el_texto_ancla(): void
    {
        $html = '<nav><a href="/mesas">Reservar mesa</a><a href="/contacto">Contacto</a></nav>';

        $senales = (new DetectorEnlacesSenal)->detectar($html, self::BASE);

        $this->assertNotNull($senales);
        $this->assertTrue($senales['reservas']);
        $this->assertTrue($senales['formulario']);
        $this->assertFalse($senales['carrito']);
    }

    public function test_reconoce_el_enlace_de_reservas_por_la_ruta(): void
    {
        $html = '<a href="/es/reserva-online">Pide tu sitio</a>';

        $senales = (new DetectorEnlacesSenal)->detectar($html, self::BASE);

        $this->assertNotNull($senales);
        $this->assertTrue($senales['reservas']);
    }

    public function test_una_home_sin_enlaces_utiles_deja_todas_las_senales_en_false(): void
    {
        $html = '<nav><a href="/nosotros">Quiénes somos</a><a href="#top">Arriba</a></nav>';

        $senales = (new DetectorEnlacesSenal)->detectar($html, self::BASE);

        $this->assertSame(
            ['reservas' => false, 'carrito' => false, 'formulario' => false, 'whatsapp' => false],
            $senales
        );
    }

    public function test_los_enlaces_externos_no_cuentan(): void
    {
        $html = '<a href="https://www.thefork.es/restaurante/demo">Reserva en ElTenedor</a>';

        $senales = (new DetectorEnlacesSenal)->detectar($html, self::BASE);

        $this->assertNotNull($senales);
        $this->assertFalse($senales['reservas']);
    }

    public function test_reconoce_la_tienda_y_el_carrito(): void
    {
        $html = '<a href="/tienda">Tienda</a><a href="/carrito">Mi cesta</a>';

        $senales = (new DetectorEnlacesSenal)->detectar($html, self::BASE);

        $this->assertNotNull($senales);
        $this->assertTrue($senales['carrito']);
    }

    public function test_sin_palabras_clave_configuradas_no_hay_foto(): void
    {
        config()->set('outreach.scraper.palabras_clave_senal', []);

        $this->assertNull((new DetectorEnlacesSenal)->detectar('<a href="/reservas">x</a>', self::BASE));
    }
}
