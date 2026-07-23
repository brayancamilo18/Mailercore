<?php

namespace Tests\Feature;

use App\Excepciones\PlantillaInvalida;
use App\Mail\CorreoOutreach;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Mensaje;
use App\Services\Envio\Renderizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RenderizadorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function plantillasProvider(): array
    {
        $sectores = [
            'hosteleria', 'salud', 'retail', 'servicios_profesionales',
            'oficios', 'belleza', 'agencias',
        ];

        $casos = [];
        foreach ($sectores as $sector) {
            $casos["{$sector}-paso-1"] = [$sector, 1];
            $casos["{$sector}-paso-2"] = [$sector, 2];
        }

        return $casos;
    }

    #[DataProvider('plantillasProvider')]
    public function test_renderiza_las_catorce_plantillas(string $sector, int $paso): void
    {
        $lead = $this->leadConAuditoria($sector);
        $resultado = app(Renderizador::class)->renderizar($lead, $paso);

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('asunto', $resultado);
        $this->assertArrayHasKey('texto', $resultado);
        $this->assertArrayHasKey('html', $resultado);
        $this->assertNotSame('', $resultado['asunto']);
        $this->assertNotSame('', $resultado['texto']);
        $this->assertNotSame('', $resultado['html']);
    }

    public function test_ninguna_plantilla_supera_el_limite_de_palabras(): void
    {
        $cfg = config('outreach.envio');

        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);

                $cuerpo = explode("\n---", $resultado['texto'])[0];
                $palabras = str_word_count(strip_tags($cuerpo), 0, 'áéíóúñüÁÉÍÓÚÑÜ');
                $maximo = $paso === 1 ? $cfg['max_palabras_cuerpo'] : $cfg['max_palabras_seguimiento'];

                $this->assertLessThanOrEqual(
                    $maximo,
                    $palabras,
                    "{$sector}-{$paso}: {$palabras} palabras (máx {$maximo})"
                );
            }
        }
    }

    public function test_ninguna_plantilla_tiene_mas_de_un_enlace(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);
                $this->assertLessThanOrEqual(1, substr_count($resultado['html'], '<a '));
            }
        }
    }

    public function test_ninguna_plantilla_lleva_imagenes(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);
                $this->assertStringNotContainsString('<img', $resultado['html']);
            }
        }
    }

    public function test_asunto_de_seguimiento_empieza_por_re(): void
    {
        $resultado = app(Renderizador::class)->renderizar(
            $this->leadConAuditoria('hosteleria'),
            2
        );

        $this->assertNotNull($resultado);
        $this->assertStringStartsWith('Re: ', $resultado['asunto']);
    }

    public function test_lanza_si_hay_palabra_prohibida(): void
    {
        $ruta = resource_path('views/emails/texto/hosteleria-1.blade.php');
        $original = file_get_contents($ruta);
        $this->assertNotFalse($original);

        file_put_contents($ruta, str_replace('móvil', 'gratis', $original));

        try {
            $this->expectException(PlantillaInvalida::class);
            app(Renderizador::class)->renderizar($this->leadConAuditoria('hosteleria'), 1);
        } finally {
            file_put_contents($ruta, $original);
        }
    }

    public function test_devuelve_null_sin_auditoria(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'website_dominio' => 'ejemplo.es',
        ]);

        $this->assertNull(app(Renderizador::class)->renderizar($lead, 1));
    }

    public function test_devuelve_null_sin_sector(): void
    {
        $lead = Lead::factory()->create([
            'sector' => null,
            'website_dominio' => 'ejemplo.es',
        ]);

        Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'sin_viewport',
            'hallazgos' => [
                ['codigo' => 'sin_viewport', 'peso' => 25, 'titulo' => 't', 'detalle' => 'd', 'datos' => []],
            ],
        ]);

        $this->assertNull(app(Renderizador::class)->renderizar($lead->fresh(['auditoria']), 1));
    }

    public function test_mailable_incluye_list_unsubscribe(): void
    {
        config([
            'outreach.envio.remitente.email_baja' => 'baja@silgodev.es',
            'outreach.envio.remitente.url_baja' => 'https://silgodev.es/baja',
            'outreach.envio.remitente.responder_a' => 'hola@silgodev.es',
        ]);

        Mail::fake();

        $mensaje = Mensaje::factory()->create([
            'asunto' => 'vuestra web en el móvil',
            'cuerpo_texto' => "Hola,\n\nPrueba.\n\n---\nPie",
            'cuerpo_html' => '<p>Hola,</p><p>Prueba.</p>',
            'message_id' => '<test-123@silgodev.es>',
        ]);

        Mail::to('destino@example.com')->send(new CorreoOutreach($mensaje));

        Mail::assertSent(CorreoOutreach::class, function (CorreoOutreach $mail): bool {
            $texto = $mail->headers()->text;

            return isset($texto['List-Unsubscribe'])
                && str_contains($texto['List-Unsubscribe'], 'mailto:baja@silgodev.es')
                && str_contains($texto['List-Unsubscribe'], 'https://silgodev.es/baja')
                && ($texto['List-Unsubscribe-Post'] ?? null) === 'List-Unsubscribe=One-Click';
        });
    }

    private function leadConAuditoria(string $sector): Lead
    {
        $lead = Lead::factory()->create([
            'sector' => $sector,
            'nombre' => 'Negocio Prueba',
            'website' => 'https://ejemplo.es',
            'website_dominio' => 'ejemplo.es',
        ]);

        Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'sin_viewport',
            'hallazgo_secundario_codigo' => 'respuesta_lenta',
            'hallazgos' => [
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
                    'titulo' => 'Respuesta lenta',
                    'detalle' => '3200 ms',
                    'datos' => ['ms' => 3200, 'segundos' => 3.2],
                ],
            ],
        ]);

        return $lead->fresh(['auditoria']);
    }
}
