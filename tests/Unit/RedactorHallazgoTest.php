<?php

namespace Tests\Unit;

use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\RedactorHallazgo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedactorHallazgoTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const CODIGOS = [
        'sin_viewport', 'title_malo', 'sin_meta_description', 'h1_incorrecto',
        'imagenes_sin_alt', 'sin_jsonld', 'sin_https', 'cert_caduca', 'web_abandonada',
        'generador_obsoleto', 'sin_aviso_legal', 'sin_cookies', 'sin_redes', 'sin_formulario',
        'contacto_roto', 'sin_reservas', 'sin_carrito', 'sin_whatsapp', 'html_pesado',
        'respuesta_lenta', 'psi_rendimiento', 'psi_lcp', 'psi_peso', 'psi_seo',
        'psi_accesibilidad',
    ];

    public function test_todos_los_codigos_tienen_variante_generica(): void
    {
        $frases = $this->frasesHallazgo();

        foreach (self::CODIGOS as $codigo) {
            $this->assertArrayHasKey($codigo, $frases, "Falta el código {$codigo}");
            $this->assertArrayHasKey('generico', $frases[$codigo], "Falta generico en {$codigo}");
            $this->assertArrayHasKey('asunto', $frases[$codigo]['generico']);
            $this->assertArrayHasKey('apertura', $frases[$codigo]['generico']);
            $this->assertNotSame('', $frases[$codigo]['generico']['asunto']);
            $this->assertNotSame('', $frases[$codigo]['generico']['apertura']);
        }
    }

    public function test_usa_frase_del_hallazgo_principal(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'oficios',
            'nombre' => 'Fontanería López',
            'website_dominio' => 'ejemplo.es',
        ]);

        $auditoria = Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'web_abandonada',
            'hallazgos' => [[
                'codigo' => 'web_abandonada',
                'peso' => 40,
                'titulo' => 'Web abandonada',
                'detalle' => 'Copyright del año 2014',
                'datos' => ['anio' => 2014],
            ]],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria);

        $this->assertStringContainsString('2014', $resultado['apertura']);
        $this->assertStringContainsString('ejemplo.es', $resultado['apertura']);
    }

    public function test_seguimiento_usa_hallazgo_secundario(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'website_dominio' => 'bar.es',
        ]);

        $auditoria = Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'sin_viewport',
            'hallazgo_secundario_codigo' => 'web_abandonada',
            'hallazgos' => [
                [
                    'codigo' => 'sin_viewport',
                    'peso' => 40,
                    'titulo' => 'Sin viewport',
                    'detalle' => 'Sin viewport',
                    'datos' => [],
                ],
                [
                    'codigo' => 'web_abandonada',
                    'peso' => 30,
                    'titulo' => 'Web abandonada',
                    'detalle' => 'Copyright 2019',
                    'datos' => ['anio' => 2019],
                ],
            ],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria, secundario: true);

        $this->assertStringContainsString('2019', $resultado['apertura']);
        $this->assertDoesNotMatchRegularExpression('/adapta|trompicones|viewport/i', $resultado['apertura']);
    }

    public function test_sin_auditoria_usa_apertura_generica_del_sector(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'nombre' => 'Bar Paco',
            'website_dominio' => 'ejemplo.es',
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead);

        $hosteleria = require resource_path('data/aperturas_sector.php');
        $esperada = $hosteleria['hosteleria'][$lead->id % 2];

        $this->assertSame($esperada['asunto'], $resultado['asunto']);
    }

    public function test_usa_apertura_https_si_falta_candado_y_no_hay_hallazgo(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'website_dominio' => 'ejemplo.es',
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'es_https' => false,
        ]);

        $lead->refresh()->load('paginas');

        $resultado = (new RedactorHallazgo)->redactar($lead);

        $this->assertMatchesRegularExpression('/HTTPS|candado/i', $resultado['apertura']);
    }

    /**
     * @return array<string, array<string, array{asunto: string, apertura: string}>>
     */
    private function frasesHallazgo(): array
    {
        return require resource_path('data/frases_hallazgo.php');
    }
}
