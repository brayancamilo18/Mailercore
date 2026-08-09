<?php

namespace Tests\Feature;

use App\DTO\Hallazgo;
use App\Enums\Certeza;
use App\Enums\PropositoRastreo;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\Comprobaciones\SinReservas;
use App\Services\Auditoria\Comprobaciones\SinViewport;
use App\Services\Auditoria\ContratoComprobacion;
use App\Services\Auditoria\MotorAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MotorAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_puntuacion_es_la_suma_de_pesos(): void
    {
        $lead = $this->leadConHome();
        $motor = new MotorAuditoria([
            $this->comprobacionFija('a', 40),
            $this->comprobacionFija('b', 25),
        ]);

        $auditoria = $motor->auditar($lead);

        $this->assertNotNull($auditoria);
        $this->assertSame(65, $auditoria->puntuacion);
    }

    public function test_puntuacion_tope_100(): void
    {
        $lead = $this->leadConHome();
        $motor = new MotorAuditoria([
            $this->comprobacionFija('a', 60),
            $this->comprobacionFija('b', 50),
        ]);

        $auditoria = $motor->auditar($lead);

        $this->assertNotNull($auditoria);
        $this->assertSame(100, $auditoria->puntuacion);
    }

    public function test_hallazgo_principal_es_el_de_mayor_peso(): void
    {
        $lead = $this->leadConHome();
        $motor = new MotorAuditoria([
            $this->comprobacionFija('leve', 10, 'detalle leve'),
            $this->comprobacionFija('grave', 40, 'detalle grave'),
        ]);

        $auditoria = $motor->auditar($lead);

        $this->assertNotNull($auditoria);
        $this->assertSame('grave', $auditoria->hallazgo_codigo);
        $this->assertSame('detalle grave', $auditoria->hallazgo_principal);
    }

    public function test_hallazgo_secundario_es_el_segundo(): void
    {
        $lead = $this->leadConHome();
        $motor = new MotorAuditoria([
            $this->comprobacionFija('a', 50, 'primero'),
            $this->comprobacionFija('b', 30, 'segundo'),
            $this->comprobacionFija('c', 10, 'tercero'),
        ]);

        $auditoria = $motor->auditar($lead);

        $this->assertNotNull($auditoria);
        $this->assertSame('b', $auditoria->hallazgo_secundario_codigo);
        $this->assertSame('segundo', $auditoria->hallazgo_secundario);
    }

    public function test_sin_paginas_no_crea_auditoria(): void
    {
        $lead = Lead::factory()->create();
        $motor = new MotorAuditoria([$this->comprobacionFija('a', 10)]);

        $this->assertNull($motor->auditar($lead));
        $this->assertSame(0, Auditoria::query()->count());
    }

    public function test_filtra_comprobaciones_por_sector(): void
    {
        $lead = Lead::factory()->create(['sector' => 'retail']);
        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'tiene_reservas' => false,
            'capturada_at' => now(),
        ]);

        $motor = new MotorAuditoria([new SinReservas]);
        $auditoria = $motor->auditar($lead->fresh());

        $this->assertNotNull($auditoria);
        $this->assertSame(0, $auditoria->puntuacion);
        $this->assertNull($auditoria->hallazgo_codigo);
        $this->assertSame([], $auditoria->hallazgos ?? []);
    }

    /**
     * El motivo de todo el tri-estado: si el rastreo solo buscaba correos,
     * nadie abrió la página de reservas y la auditoría no puede echarlas de menos.
     */
    public function test_lead_sin_pagina_de_reservas_visitada_no_genera_hallazgo(): void
    {
        $lead = Lead::factory()->create(['sector' => 'hosteleria']);
        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'proposito' => PropositoRastreo::EMAIL->value,
            'tiene_reservas' => null,
            'capturada_at' => now(),
        ]);

        $auditoria = (new MotorAuditoria([new SinReservas]))->auditar($lead->fresh());

        $this->assertNotNull($auditoria);
        $this->assertNull($auditoria->hallazgo_codigo);
        $this->assertSame(0, $auditoria->puntuacion);
        $this->assertSame([], $auditoria->hallazgos ?? []);
    }

    public function test_con_rastreo_de_auditoria_las_reservas_ausentes_son_observado(): void
    {
        $lead = Lead::factory()->create(['sector' => 'hosteleria']);

        foreach (['/', '/reservas'] as $ruta) {
            Pagina::factory()->create([
                'lead_id' => $lead->id,
                'ruta' => $ruta,
                'proposito' => PropositoRastreo::AUDITORIA->value,
                'tiene_reservas' => null,
                'capturada_at' => now(),
            ]);
        }

        $auditoria = (new MotorAuditoria([new SinReservas]))->auditar($lead->fresh());

        $this->assertNotNull($auditoria);
        $this->assertSame('sin_reservas', $auditoria->hallazgo_codigo);
        $this->assertSame(Certeza::OBSERVADO->value, $auditoria->hallazgo_certeza);
        $this->assertSame(Certeza::OBSERVADO->value, $auditoria->hallazgos[0]['certeza']);
        $this->assertStringContainsString(
            'buscado en /, /reservas',
            (string) $auditoria->hallazgos[0]['evidencia']
        );
    }

    public function test_guarda_la_certeza_del_principal_y_la_de_cada_hallazgo(): void
    {
        $lead = $this->leadConHome();
        $motor = new MotorAuditoria([
            $this->comprobacionFija('indicio', 40, 'uno', Certeza::INDICIO),
            $this->comprobacionFija('hecho', 20, 'dos', Certeza::HECHO),
        ]);

        $auditoria = $motor->auditar($lead);

        $this->assertNotNull($auditoria);
        $this->assertSame(Certeza::INDICIO->value, $auditoria->hallazgo_certeza);
        $this->assertSame(
            [Certeza::INDICIO->value, Certeza::HECHO->value],
            array_column($auditoria->hallazgos, 'certeza')
        );
        $this->assertSame('evidencia de indicio', $auditoria->hallazgos[0]['evidencia']);
    }

    public function test_usa_la_captura_mas_reciente_de_cada_ruta(): void
    {
        $lead = Lead::factory()->create(['sector' => 'retail']);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'tiene_viewport' => false,
            'capturada_at' => now()->subDay(),
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'tiene_viewport' => true,
            'capturada_at' => now(),
        ]);

        $motor = new MotorAuditoria([
            new SinViewport,
        ]);

        $auditoria = $motor->auditar($lead->fresh());

        $this->assertNotNull($auditoria);
        $this->assertNull($auditoria->hallazgo_codigo);
        $this->assertSame(0, $auditoria->puntuacion);
    }

    public function test_reauditar_actualiza_la_misma_fila(): void
    {
        $lead = $this->leadConHome();
        $motor = new MotorAuditoria([
            $this->comprobacionFija('a', 20, 'uno'),
        ]);

        $primera = $motor->auditar($lead);
        $this->assertNotNull($primera);

        $motor2 = new MotorAuditoria([
            $this->comprobacionFija('b', 35, 'dos'),
        ]);

        $segunda = $motor2->auditar($lead->fresh(['auditoria', 'paginas']));

        $this->assertNotNull($segunda);
        $this->assertSame(1, Auditoria::query()->count());
        $this->assertSame($primera->id, $segunda->id);
        $this->assertSame('b', $segunda->hallazgo_codigo);
        $this->assertSame(35, $segunda->puntuacion);
    }

    private function leadConHome(array $attrs = []): Lead
    {
        $lead = Lead::factory()->create($attrs);
        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'capturada_at' => now(),
        ]);

        return $lead->fresh();
    }

    private function comprobacionFija(
        string $codigo,
        int $peso,
        string $detalle = 'detalle',
        Certeza $certeza = Certeza::HECHO,
    ): ContratoComprobacion {
        return new class($codigo, $peso, $detalle, $certeza) implements ContratoComprobacion
        {
            public function __construct(
                private string $codigo,
                private int $peso,
                private string $detalle,
                private Certeza $certeza,
            ) {}

            public function codigo(): string
            {
                return $this->codigo;
            }

            public function peso(): int
            {
                return $this->peso;
            }

            public function sectores(): ?array
            {
                return null;
            }

            public function evaluar(Lead $lead, Collection $paginas, ?Auditoria $auditoria): ?Hallazgo
            {
                return new Hallazgo(
                    $this->codigo,
                    $this->peso,
                    'Título '.$this->codigo,
                    $this->detalle,
                    $this->certeza,
                    evidencia: 'evidencia de '.$this->codigo,
                );
            }
        };
    }
}
