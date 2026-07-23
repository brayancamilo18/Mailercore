<?php

namespace Tests\Unit;

use App\Models\Suppression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuppressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_normaliza_email_con_mayusculas_y_espacios(): void
    {
        $this->assertSame(
            'info@ejemplo.es',
            Suppression::normalizarEmail('  INFO@Ejemplo.ES  ')
        );
    }

    public function test_dominio_de_email(): void
    {
        $this->assertSame('ejemplo.es', Suppression::dominioDeEmail('info@ejemplo.es'));
        $this->assertNull(Suppression::dominioDeEmail('sinarroba'));
        $this->assertNull(Suppression::dominioDeEmail('a@'));
    }

    public function test_dominio_de_web_quita_www_y_anade_esquema(): void
    {
        $this->assertSame('ejemplo.es', Suppression::dominioDeWeb('www.Ejemplo.ES/contacto'));
        $this->assertSame('sub.ejemplo.es', Suppression::dominioDeWeb('https://sub.ejemplo.es:8080/x?y=1'));
        $this->assertNull(Suppression::dominioDeWeb(null));
        $this->assertNull(Suppression::dominioDeWeb(''));
    }

    public function test_existe_detecta_email_exacto(): void
    {
        Suppression::registrar('info@ejemplo.es', 'manual');

        $this->assertTrue(Suppression::existe('info@ejemplo.es'));
        $this->assertTrue(Suppression::existe('  INFO@Ejemplo.ES  '));
        $this->assertFalse(Suppression::existe('otro@otrodominio.es'));
    }

    public function test_existe_detecta_por_dominio(): void
    {
        Suppression::registrarDominio('malo.es', 'rebote_duro');

        $this->assertTrue(Suppression::existe('cualquiera@malo.es'));
    }

    public function test_registrar_es_idempotente(): void
    {
        Suppression::registrar('info@ejemplo.es', 'manual');
        Suppression::registrar('info@ejemplo.es', 'baja', 'segunda vez');

        $this->assertSame(1, Suppression::query()->where('email', 'info@ejemplo.es')->count());
    }
}
