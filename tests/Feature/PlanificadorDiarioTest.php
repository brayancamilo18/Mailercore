<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Envio\PlanificadorDiario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlanificadorDiarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outreach.envio.activo' => true,
            'outreach.envio.dias' => [1, 2, 3, 4],
            'outreach.envio.max_diario' => 40,
            'outreach.envio.max_por_dominio_destino' => 3,
            'outreach.envio.minutos_min_entre_correos' => 4,
            'outreach.envio.porcentaje_seguimientos' => 25,
            'outreach.envio.ventana_seguimiento_dias' => [5, 9],
            'outreach.envio.remitente.nombre_legal' => 'Camilo Silva',
            'outreach.envio.remitente.direccion' => 'Calle 1',
            'outreach.envio.remitente.email_baja' => 'baja@silgodev.es',
            'outreach.envio.remitente.url_baja' => 'https://silgodev.es/baja',
            'app.url' => 'https://silgodev.es',
        ]);
    }

    public function test_no_planifica_si_el_envio_esta_desactivado(): void
    {
        config(['outreach.envio.activo' => false]);

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertSame(0, $resultado['cuota']);
        $this->assertStringContainsString('desactivado', $resultado['motivo']);
        $this->assertSame(0, Mensaje::query()->count());
    }

    public function test_no_planifica_en_viernes(): void
    {
        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-13')); // viernes

        $this->assertSame(0, $resultado['cuota']);
        $this->assertSame('No es día de envío', $resultado['motivo']);
        $this->assertSame(0, Mensaje::query()->count());
    }

    public function test_crea_tantos_mensajes_como_cuota(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->candidato(['puntuacion' => 50 + $i]);
        }

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertSame(10, $resultado['cuota']);
        $this->assertSame(10, $resultado['primer_contacto']);
        $this->assertSame(10, Mensaje::query()->where('paso', 1)->count());
    }

    public function test_ordena_por_puntuacion_de_auditoria_descendente(): void
    {
        $bajo = $this->candidato(['puntuacion' => 20, 'dominio' => 'bajo.es']);
        $alto = $this->candidato(['puntuacion' => 80, 'dominio' => 'alto.es']);
        $medio = $this->candidato(['puntuacion' => 50, 'dominio' => 'medio.es']);

        app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $primero = Mensaje::query()->orderBy('programado_para')->first();

        $this->assertNotNull($primero);
        $this->assertSame($alto->id, $primero->lead_id);
        $this->assertNotSame($bajo->id, $primero->lead_id);
        $this->assertNotSame($medio->id, $primero->lead_id);
    }

    public function test_no_selecciona_email_sin_verificar(): void
    {
        $this->candidato([
            'estado_verificacion' => null,
            'verificado_at' => null,
        ]);

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertSame(0, $resultado['primer_contacto']);
        $this->assertSame(0, Mensaje::query()->count());
    }

    public function test_no_selecciona_email_suprimido(): void
    {
        $lead = $this->candidato(['email' => 'fuera@ejemplo.es']);
        Suppression::registrar('fuera@ejemplo.es', 'baja');

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertSame(0, $resultado['primer_contacto']);
        $this->assertSame(0, Mensaje::query()->where('lead_id', $lead->id)->count());
    }

    public function test_no_repite_lead_ya_contactado(): void
    {
        $lead = $this->candidato(['dominio' => 'ya.es']);

        Mensaje::factory()->create([
            'lead_id' => $lead->id,
            'plantilla' => $lead->plantilla(),
            'paso' => 1,
            'estado' => 'enviado',
            'enviado_at' => now()->subDays(2),
            'destinatario' => 'info@ya.es',
        ]);

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertSame(0, $resultado['primer_contacto']);
        $this->assertSame(1, Mensaje::query()->where('lead_id', $lead->id)->count());
    }

    public function test_limita_tres_por_dominio_destino(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->candidato([
                'email' => "contacto{$i}@mismodominio.com",
                'dominio' => "negocio{$i}.es",
                'puntuacion' => 90 - $i,
            ]);
        }

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertSame(3, $resultado['primer_contacto']);
        $this->assertSame(3, Mensaje::query()->count());
    }

    public function test_respeta_minutos_minimos_entre_correos(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->candidato(['puntuacion' => 40 + $i, 'dominio' => "d{$i}.es"]);
        }

        app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $horas = Mensaje::query()->orderBy('programado_para')->pluck('programado_para');
        $this->assertGreaterThan(1, $horas->count());

        for ($i = 1; $i < $horas->count(); $i++) {
            $diff = $horas[$i - 1]->diffInMinutes($horas[$i]);
            $this->assertGreaterThanOrEqual(4, $diff);
        }
    }

    public function test_reserva_porcentaje_para_seguimientos(): void
    {
        // Cuota 10 → 25% = 2 seguimientos.
        for ($i = 0; $i < 10; $i++) {
            $this->candidato(['puntuacion' => 60 + $i, 'dominio' => "nuevo{$i}.es"]);
        }

        for ($i = 0; $i < 3; $i++) {
            $this->candidatoSeguimiento([
                'dominio' => "viejo{$i}.es",
                'email' => "hola{$i}@viejo{$i}.es",
            ]);
        }

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertSame(2, $resultado['seguimientos']);
        $this->assertSame(8, $resultado['primer_contacto']);
        $this->assertSame(2, Mensaje::query()->where('paso', 2)->count());
    }

    public function test_omite_lead_sin_hallazgo_y_continua(): void
    {
        $sinHallazgo = $this->candidato([
            'puntuacion' => 99,
            'dominio' => 'sin.es',
            'hallazgo_codigo' => null,
            'hallazgo_principal' => 'detalle genérico',
            'hallazgos' => [],
        ]);
        $conHallazgo = $this->candidato([
            'puntuacion' => 50,
            'dominio' => 'con.es',
        ]);

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'));

        $this->assertGreaterThanOrEqual(1, $resultado['omitidos']);
        $this->assertSame(0, Mensaje::query()->where('lead_id', $sinHallazgo->id)->count());
        $this->assertSame(1, Mensaje::query()->where('lead_id', $conHallazgo->id)->count());
    }

    public function test_dry_run_no_crea_mensajes(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->candidato(['dominio' => "dry{$i}.es", 'puntuacion' => 40 + $i]);
        }

        $resultado = app(PlanificadorDiario::class)->planificar(Carbon::parse('2026-03-10'), true);

        $this->assertGreaterThan(0, $resultado['primer_contacto']);
        $this->assertSame(0, Mensaje::query()->count());
        $this->assertSame('auditado', Lead::query()->first()->estado);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function candidato(array $attrs = []): Lead
    {
        $dominio = $attrs['dominio'] ?? fake()->unique()->domainName();
        $email = $attrs['email'] ?? 'info@'.$dominio;
        $sector = $attrs['sector'] ?? 'hosteleria';

        $lead = Lead::factory()->create([
            'sector' => $sector,
            'estado' => 'auditado',
            'website' => 'https://'.$dominio,
            'website_dominio' => $dominio,
            'clasificacion_confianza' => $attrs['confianza'] ?? 80,
            'rastreado_at' => now(),
        ]);

        LeadEmail::factory()->principal()->valido()->create([
            'lead_id' => $lead->id,
            'email' => $email,
            'estado_verificacion' => array_key_exists('estado_verificacion', $attrs)
                ? $attrs['estado_verificacion']
                : 'valido',
            'verificado_at' => array_key_exists('verificado_at', $attrs)
                ? $attrs['verificado_at']
                : now(),
            'es_catch_all' => $attrs['es_catch_all'] ?? false,
        ]);

        Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'puntuacion' => $attrs['puntuacion'] ?? 50,
            'hallazgo_codigo' => array_key_exists('hallazgo_codigo', $attrs)
                ? $attrs['hallazgo_codigo']
                : 'sin_viewport',
            'hallazgo_principal' => array_key_exists('hallazgo_principal', $attrs)
                ? $attrs['hallazgo_principal']
                : 'Sin viewport móvil',
            'hallazgo_secundario_codigo' => $attrs['hallazgo_secundario_codigo'] ?? 'respuesta_lenta',
            'hallazgos' => $attrs['hallazgos'] ?? [
                [
                    'codigo' => 'sin_viewport',
                    'peso' => 25,
                    'titulo' => 'Sin viewport',
                    'detalle' => 'Sin viewport',
                    'datos' => [],
                ],
                [
                    'codigo' => 'respuesta_lenta',
                    'peso' => 15,
                    'titulo' => 'Lenta',
                    'detalle' => 'lenta',
                    'datos' => ['ms' => 3000, 'segundos' => 3.0],
                ],
            ],
            'auditada_at' => now(),
        ]);

        return $lead->fresh(['auditoria', 'emailPrincipal']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function candidatoSeguimiento(array $attrs = []): Lead
    {
        $lead = $this->candidato(array_merge($attrs, [
            'puntuacion' => $attrs['puntuacion'] ?? 40,
        ]));

        $lead->update(['estado' => 'contactado']);

        Mensaje::factory()->create([
            'lead_id' => $lead->id,
            'lead_email_id' => $lead->emailPrincipal?->id,
            'destinatario' => $lead->emailPrincipal?->email,
            'plantilla' => $lead->plantilla(),
            'paso' => 1,
            'estado' => 'enviado',
            'enviado_at' => now()->subDays(7),
            'asunto' => 'asunto previo',
            'cuerpo_texto' => 'texto',
        ]);

        return $lead->fresh(['auditoria', 'emailPrincipal', 'mensajes']);
    }
}
