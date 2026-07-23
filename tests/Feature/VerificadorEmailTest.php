<?php

namespace Tests\Feature;

use App\Models\EventoInbox;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Suppression;
use App\Services\Verificacion\VerificadorEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VerificadorEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outreach.verificador.sonda_smtp' => false,
            'outreach.verificador.dominios_desechables' => [
                'mailinator.com', 'guerrillamail.com', 'yopmail.com',
            ],
        ]);

        Cache::flush();
    }

    public function test_sintaxis_invalida(): void
    {
        $email = $this->leadEmail('no-es-un-email');

        $resultado = app(VerificadorEmail::class)->verificar($email);

        $this->assertSame('invalido', $resultado);
        $this->assertSame('invalido', $email->fresh()->estado_verificacion);
        $this->assertFalse($email->fresh()->mx_ok);
    }

    public function test_dominio_sin_mx_es_invalido(): void
    {
        $dominio = 'sin-mx-test.invalid';
        Cache::put('mx:'.$dominio, false, 3600);

        $email = $this->leadEmail('info@'.$dominio);

        $resultado = app(VerificadorEmail::class)->verificar($email);

        $this->assertSame('invalido', $resultado);
        $this->assertSame('invalido', $email->fresh()->estado_verificacion);
        $this->assertFalse($email->fresh()->mx_ok);
    }

    public function test_dominio_con_dos_rebotes_duros_se_suprime_entero(): void
    {
        $dominio = 'quemado.es';
        Cache::put('mx:'.$dominio, true, 3600);

        EventoInbox::factory()->create([
            'email' => 'a@'.$dominio,
            'tipo' => 'rebote_duro',
            'mensaje_id' => null,
        ]);
        EventoInbox::factory()->create([
            'email' => 'b@'.$dominio,
            'tipo' => 'rebote_duro',
            'mensaje_id' => null,
        ]);

        $email = $this->leadEmail('nuevo@'.$dominio);

        $resultado = app(VerificadorEmail::class)->verificar($email);

        $this->assertSame('invalido', $resultado);
        $this->assertTrue(Suppression::dominioExcluido($dominio));
        $this->assertSame('invalido', $email->fresh()->estado_verificacion);
    }

    public function test_dominio_desechable_es_riesgo(): void
    {
        Cache::put('mx:mailinator.com', true, 3600);

        $email = $this->leadEmail('prueba@mailinator.com');

        $resultado = app(VerificadorEmail::class)->verificar($email);

        $this->assertSame('riesgo', $resultado);
        $this->assertSame('riesgo', $email->fresh()->estado_verificacion);
        $this->assertTrue($email->fresh()->mx_ok);
        $this->assertNull($email->fresh()->es_catch_all);
    }

    public function test_sonda_desactivada_no_marca_catch_all(): void
    {
        config(['outreach.verificador.sonda_smtp' => false]);
        Cache::put('mx:empresa.es', true, 3600);

        $this->assertNull(app(VerificadorEmail::class)->esCatchAll('empresa.es'));

        $email = $this->leadEmail('hola@empresa.es');
        $resultado = app(VerificadorEmail::class)->verificar($email);

        $this->assertSame('valido', $resultado);
        $this->assertNull($email->fresh()->es_catch_all);
    }

    public function test_guarda_verificado_at(): void
    {
        Cache::put('mx:negocio.es', true, 3600);

        $email = $this->leadEmail('info@negocio.es');
        $this->assertNull($email->verificado_at);

        app(VerificadorEmail::class)->verificar($email);

        $this->assertNotNull($email->fresh()->verificado_at);
    }

    public function test_email_suprimido_es_invalido(): void
    {
        Cache::put('mx:excluido.es', true, 3600);
        Suppression::registrar('fuera@excluido.es', 'baja');

        $email = $this->leadEmail('fuera@excluido.es');

        $resultado = app(VerificadorEmail::class)->verificar($email);

        $this->assertSame('invalido', $resultado);
        $this->assertSame('invalido', $email->fresh()->estado_verificacion);
    }

    private function leadEmail(string $direccion): LeadEmail
    {
        $lead = Lead::factory()->create();

        return LeadEmail::factory()->create([
            'lead_id' => $lead->id,
            'email' => $direccion,
            'es_principal' => true,
            'estado_verificacion' => null,
            'verificado_at' => null,
            'mx_ok' => null,
            'es_catch_all' => null,
        ]);
    }
}
