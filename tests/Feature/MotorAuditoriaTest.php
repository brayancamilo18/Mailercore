<?php

namespace Tests\Feature;

use App\DTO\Hallazgo;
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

    private function comprobacionFija(string $codigo, int $peso, string $detalle = 'detalle'): ContratoComprobacion
    {
        return new class($codigo, $peso, $detalle) implements ContratoComprobacion
        {
            public function __construct(
                private string $codigo,
                private int $peso,
                private string $detalle,
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
                return new Hallazgo($this->codigo, $this->peso, 'Título '.$this->codigo, $this->detalle);
            }
        };
    }
}
