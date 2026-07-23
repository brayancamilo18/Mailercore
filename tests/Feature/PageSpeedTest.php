<?php

namespace Tests\Feature;

use App\Excepciones\CuotaPageSpeedExcedida;
use App\Jobs\AnalizarPageSpeedJob;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\ClientePageSpeed;
use App\Services\Auditoria\MotorAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PageSpeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapea_puntuaciones_y_metricas(): void
    {
        Http::fake([
            '*' => Http::response($this->fixture(), 200),
        ]);

        $resultado = app(ClientePageSpeed::class)->analizar('https://example.com');

        $this->assertNotNull($resultado);
        $this->assertSame(42, $resultado->rendimiento);
        $this->assertSame(85, $resultado->seo);
        $this->assertSame(5200, $resultado->lcpMs);
        $this->assertSame(0.123, $resultado->cls);
        $this->assertSame(2000, $resultado->pesoKb);
    }

    public function test_lanza_cuota_excedida_con_429(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'quota'], 429),
        ]);

        $this->expectException(CuotaPageSpeedExcedida::class);

        app(ClientePageSpeed::class)->analizar('https://example.com');
    }

    public function test_devuelve_null_si_la_web_no_es_analizable(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'bad request'], 400),
        ]);

        $this->assertNull(
            app(ClientePageSpeed::class)->analizar('https://example.com')
        );
    }

    public function test_job_guarda_las_metricas_en_la_auditoria(): void
    {
        Http::fake([
            '*' => Http::response($this->fixture(), 200),
        ]);

        $lead = $this->leadConAuditoriaYPagina();

        (new AnalizarPageSpeedJob($lead->id))->handle(
            app(ClientePageSpeed::class),
            app(MotorAuditoria::class),
        );

        $auditoria = $lead->fresh()->auditoria;
        $this->assertNotNull($auditoria);
        $this->assertSame(42, $auditoria->psi_rendimiento);
        $this->assertSame(85, $auditoria->psi_seo);
        $this->assertSame(65, $auditoria->psi_accesibilidad);
        $this->assertSame(77, $auditoria->psi_buenas_practicas);
        $this->assertSame(5200, $auditoria->psi_lcp_ms);
        $this->assertEquals(0.123, (float) $auditoria->psi_cls);
        $this->assertSame(451, $auditoria->psi_tbt_ms);
        $this->assertSame(2000, $auditoria->psi_peso_kb);
        $this->assertNotNull($auditoria->psi_solicitado_at);
        $this->assertNull($auditoria->psi_error);
    }

    public function test_job_guarda_psi_error_si_devuelve_null(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'unreachable'], 400),
        ]);

        $lead = $this->leadConAuditoriaYPagina();

        (new AnalizarPageSpeedJob($lead->id))->handle(
            app(ClientePageSpeed::class),
            app(MotorAuditoria::class),
        );

        $auditoria = $lead->fresh()->auditoria;
        $this->assertNotNull($auditoria->psi_error);
        $this->assertNotNull($auditoria->psi_solicitado_at);
        $this->assertNull($auditoria->psi_rendimiento);
    }

    public function test_job_reejecuta_la_auditoria(): void
    {
        Http::fake([
            '*' => Http::response($this->fixture(), 200),
        ]);

        $lead = $this->leadConAuditoriaYPagina([
            'tiene_viewport' => true,
            'es_https' => true,
            'cert_valido' => true,
            'title' => 'Título correcto de longitud media',
            'title_longitud' => 34,
            'meta_description' => str_repeat('a', 120),
            'meta_desc_longitud' => 120,
            'h1_total' => 1,
            'imagenes_total' => 2,
            'imagenes_sin_alt' => 0,
            'tiene_jsonld' => true,
            'tiene_aviso_legal' => true,
            'tiene_privacidad' => true,
            'tiene_cookies' => true,
            'tiene_formulario' => true,
            'redes_sociales' => ['instagram' => 'https://instagram.com/x'],
            'bytes' => 40_000,
            'respuesta_ms' => 500,
            'anio_copyright' => (int) now()->year,
            'generador' => null,
        ]);

        $motor = app(MotorAuditoria::class);
        $antes = $motor->auditar($lead->fresh(['paginas', 'auditoria']));
        $this->assertNotNull($antes);
        $puntuacionAntes = $antes->puntuacion;

        (new AnalizarPageSpeedJob($lead->id))->handle(
            app(ClientePageSpeed::class),
            $motor,
        );

        $despues = $lead->fresh()->auditoria;
        $this->assertNotNull($despues);
        $this->assertNotSame($puntuacionAntes, $despues->puntuacion);
        $this->assertContains(
            'psi_rendimiento',
            collect($despues->hallazgos ?? [])->pluck('codigo')->all()
        );
    }

    public function test_comando_solo_toma_psi_caducado(): void
    {
        Queue::fake();

        $caducado = Lead::factory()->create(['website' => 'https://caducado.example']);
        Auditoria::factory()->create([
            'lead_id' => $caducado->id,
            'puntuacion' => 80,
            'psi_solicitado_at' => null,
        ]);

        $fresco = Lead::factory()->create(['website' => 'https://fresco.example']);
        Auditoria::factory()->create([
            'lead_id' => $fresco->id,
            'puntuacion' => 90,
            'psi_solicitado_at' => now(),
        ]);

        $this->artisan('auditar:pagespeed', ['--limite' => 50])
            ->assertSuccessful();

        Queue::assertPushed(AnalizarPageSpeedJob::class, 1);
        Queue::assertPushed(
            AnalizarPageSpeedJob::class,
            fn (AnalizarPageSpeedJob $job): bool => $job->leadId === $caducado->id
        );
    }

    /**
     * @param  array<string, mixed>  $paginaAttrs
     */
    private function leadConAuditoriaYPagina(array $paginaAttrs = []): Lead
    {
        $lead = Lead::factory()->create([
            'website' => 'https://example.com',
            'estado' => 'auditado',
            'sector' => 'retail',
        ]);

        Pagina::factory()->create(array_merge([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'url' => 'https://example.com/',
            'capturada_at' => now(),
        ], $paginaAttrs));

        Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'puntuacion' => 10,
            'auditada_at' => now(),
        ]);

        return $lead->fresh(['auditoria', 'paginas']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        /** @var array<string, mixed> $datos */
        $datos = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/json/pagespeed.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return $datos;
    }
}
