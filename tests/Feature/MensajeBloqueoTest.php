<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Mensaje;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensajeBloqueoTest extends TestCase
{
    use RefreshDatabase;

    public function test_marcar_enviando_devuelve_true_la_primera_vez(): void
    {
        $mensaje = Mensaje::factory()->create(['estado' => 'pendiente']);

        $this->assertTrue($mensaje->marcarEnviando());
        $this->assertSame('enviando', $mensaje->fresh()->estado);
    }

    public function test_marcar_enviando_devuelve_false_la_segunda_vez(): void
    {
        $m = Mensaje::factory()->create(['estado' => 'pendiente']);
        $copia = Mensaje::find($m->id);

        $this->assertTrue($m->marcarEnviando());
        $this->assertFalse($copia->marcarEnviando());
    }

    public function test_marcar_enviando_incrementa_intentos(): void
    {
        $mensaje = Mensaje::factory()->create([
            'estado' => 'pendiente',
            'intentos' => 0,
        ]);

        $mensaje->marcarEnviando();

        $this->assertSame(1, $mensaje->fresh()->intentos);
    }

    public function test_no_se_pueden_crear_dos_mensajes_con_misma_clave(): void
    {
        $lead = Lead::factory()->create();

        Mensaje::factory()->create([
            'lead_id' => $lead->id,
            'plantilla' => 'retail',
            'paso' => 1,
        ]);

        $this->expectException(QueryException::class);

        Mensaje::factory()->create([
            'lead_id' => $lead->id,
            'plantilla' => 'retail',
            'paso' => 1,
        ]);
    }
}
