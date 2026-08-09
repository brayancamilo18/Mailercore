<?php

namespace Tests\Unit;

use App\Enums\PropositoRastreo;
use App\Models\Pagina;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PaginaSenalTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attrs
     */
    private function pagina(array $attrs = []): Pagina
    {
        return new Pagina(array_merge([
            'ruta' => '/',
            'proposito' => PropositoRastreo::AUDITORIA->value,
            'origen_ruta' => 'fija',
            'http_status' => 200,
            'fallo_tipo' => null,
        ], $attrs));
    }

    /** @param  array<string, mixed>  $attrs */
    private function home(array $attrs = []): Pagina
    {
        return $this->pagina($attrs);
    }

    /** Ruta del sector hostelería: es donde vivirían las reservas. */
    private function carta(): Pagina
    {
        return $this->pagina(['ruta' => '/carta']);
    }

    public function test_true_si_alguna_pagina_lo_detecta(): void
    {
        $paginas = new Collection([
            $this->home(),
            $this->pagina(['ruta' => '/carta', 'tiene_reservas' => true]),
        ]);

        $this->assertTrue(Pagina::señal($paginas, 'tiene_reservas', 'hosteleria'));
        $this->assertNull(Pagina::motivoSenal($paginas, 'tiene_reservas', 'hosteleria'));
    }

    public function test_senal_de_sitio_es_false_con_cualquier_pagina_sana(): void
    {
        $paginas = new Collection([$this->home()]);

        $this->assertFalse(Pagina::señal($paginas, 'tiene_cookies'));
        $this->assertFalse(Pagina::señal($paginas, 'tiene_aviso_legal'));
        $this->assertFalse(Pagina::señal($paginas, 'tiene_privacidad'));
    }

    public function test_null_si_solo_se_rastreo_buscando_correos(): void
    {
        $paginas = new Collection([
            $this->home(['proposito' => PropositoRastreo::EMAIL->value]),
        ]);

        $this->assertNull(Pagina::señal($paginas, 'tiene_cookies'));
        $this->assertSame(
            Pagina::MOTIVO_SIN_COBERTURA,
            Pagina::motivoSenal($paginas, 'tiene_cookies')
        );
    }

    public function test_null_si_las_paginas_son_de_antes_de_registrar_el_proposito(): void
    {
        $paginas = new Collection([$this->home(['proposito' => null])]);

        $this->assertNull(Pagina::señal($paginas, 'tiene_cookies'));
    }

    public function test_null_sin_paginas(): void
    {
        $this->assertNull(Pagina::señal(new Collection, 'tiene_cookies'));
    }

    /** Test 4 del encargo: un 500 visita el sitio, pero no demuestra nada. */
    public function test_una_pagina_con_error_no_aporta_cobertura(): void
    {
        $caida = $this->home([
            'proposito' => PropositoRastreo::COMPLETO->value,
            'http_status' => 500,
            'fallo_tipo' => 'http',
        ]);
        $paginas = new Collection([$caida]);

        $this->assertFalse($caida->aportaCobertura());
        $this->assertNull(Pagina::señal($paginas, 'tiene_cookies'));
        $this->assertSame(
            Pagina::MOTIVO_SIN_COBERTURA,
            Pagina::motivoSenal($paginas, 'tiene_cookies')
        );
    }

    public function test_un_200_con_fallo_registrado_tampoco_aporta_cobertura(): void
    {
        $paginas = new Collection([
            $this->home(['http_status' => 200, 'fallo_tipo' => 'timeout']),
        ]);

        $this->assertNull(Pagina::señal($paginas, 'tiene_cookies'));
    }

    /**
     * Test 1 del encargo: home y /contacto sanas, /reservas caída. Nadie abrió
     * una página de reservas, así que no se puede negar que existan.
     */
    public function test_senal_sectorial_null_si_no_se_visito_ninguna_ruta_del_sector(): void
    {
        $paginas = new Collection([
            $this->home(['enlaces_senal' => ['reservas' => false, 'carrito' => false]]),
            $this->pagina(['ruta' => '/contacto']),
            $this->pagina(['ruta' => '/reservas', 'http_status' => 404, 'fallo_tipo' => 'http']),
        ]);

        $this->assertNull(Pagina::señal($paginas, 'tiene_reservas', 'hosteleria'));
        $this->assertSame(
            Pagina::MOTIVO_SIN_RUTA_SECTORIAL,
            Pagina::motivoSenal($paginas, 'tiene_reservas', 'hosteleria')
        );
    }

    /**
     * Test 2 del encargo: la home anuncia reservas en un enlace que nadie llegó
     * a abrir. Ahí callar no es prudencia, es lo único honesto.
     */
    public function test_senal_sectorial_null_si_la_home_enlaza_a_esa_senal(): void
    {
        $paginas = new Collection([
            $this->home(['enlaces_senal' => ['reservas' => true, 'carrito' => false]]),
            $this->pagina(['ruta' => '/contacto']),
        ]);

        $this->assertNull(Pagina::señal($paginas, 'tiene_reservas', 'hosteleria'));
        $this->assertSame(
            Pagina::MOTIVO_HOME_ENLAZA,
            Pagina::motivoSenal($paginas, 'tiene_reservas', 'hosteleria')
        );
    }

    public function test_la_home_que_enlaza_a_la_senal_calla_aunque_se_mirara_el_sector(): void
    {
        $paginas = new Collection([
            $this->home(['enlaces_senal' => ['reservas' => true]]),
            $this->carta(),
        ]);

        $this->assertNull(Pagina::señal($paginas, 'tiene_reservas', 'hosteleria'));
    }

    /** Test 3 del encargo: /carta es ruta del sector y se leyó entera. */
    public function test_senal_sectorial_false_si_se_visito_una_ruta_del_sector(): void
    {
        $paginas = new Collection([
            $this->home(['enlaces_senal' => ['reservas' => false]]),
            $this->carta(),
        ]);

        $this->assertFalse(Pagina::señal($paginas, 'tiene_reservas', 'hosteleria'));
        $this->assertNull(Pagina::motivoSenal($paginas, 'tiene_reservas', 'hosteleria'));
    }

    public function test_una_ruta_descubierta_tambien_da_cobertura_sectorial(): void
    {
        $paginas = new Collection([
            $this->home(),
            $this->pagina([
                'ruta' => '/reserva-online',
                'origen_ruta' => Pagina::ORIGEN_DESCUBIERTA,
            ]),
        ]);

        $this->assertFalse(Pagina::señal($paginas, 'tiene_reservas', 'hosteleria'));
    }

    public function test_la_ruta_del_sector_caida_no_da_cobertura(): void
    {
        $paginas = new Collection([
            $this->home(),
            $this->pagina(['ruta' => '/carta', 'http_status' => 404, 'fallo_tipo' => 'http']),
        ]);

        $this->assertNull(Pagina::señal($paginas, 'tiene_reservas', 'hosteleria'));
    }

    public function test_la_ruta_del_sector_de_otro_sector_no_cuenta(): void
    {
        $paginas = new Collection([
            $this->home(),
            $this->carta(),
        ]);

        // /carta es ruta de hostelería, no de retail.
        $this->assertNull(Pagina::señal($paginas, 'tiene_carrito', 'retail'));
        $this->assertFalse(Pagina::señal($paginas, 'tiene_carrito', 'hosteleria'));
    }

    public function test_sin_sector_solo_las_rutas_descubiertas_dan_cobertura(): void
    {
        $sinDescubrir = new Collection([$this->home(), $this->carta()]);
        $conDescubierta = new Collection([
            $this->home(),
            $this->pagina(['ruta' => '/pide-cita', 'origen_ruta' => Pagina::ORIGEN_DESCUBIERTA]),
        ]);

        $this->assertNull(Pagina::señal($sinDescubrir, 'tiene_reservas'));
        $this->assertFalse(Pagina::señal($conDescubierta, 'tiene_reservas'));
    }
}
