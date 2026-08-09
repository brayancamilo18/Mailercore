<?php

namespace Tests\Unit;

use App\Enums\Certeza;
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
            'hallazgo_codigo' => 'cert_caduca',
            'hallazgo_certeza' => Certeza::HECHO->value,
            'hallazgos' => [[
                'codigo' => 'cert_caduca',
                'peso' => 20,
                'titulo' => 'Certificado a punto de caducar',
                'detalle' => 'El certificado caduca en 12 días',
                'certeza' => Certeza::HECHO->value,
                'datos' => ['dias' => 12],
            ]],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria);

        $this->assertStringContainsString('12', $resultado['apertura']);
        $this->assertStringContainsString('ejemplo.es', $resultado['apertura']);
    }

    /**
     * Un indicio ordena la lista, pero no se le dice al dueño de la web.
     */
    public function test_un_indicio_cae_a_la_apertura_generica_del_sector(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'nombre' => 'Bar Paco',
            'website_dominio' => 'ejemplo.es',
        ]);

        $auditoria = Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'web_abandonada',
            'hallazgo_certeza' => Certeza::INDICIO->value,
            'hallazgos' => [[
                'codigo' => 'web_abandonada',
                'peso' => 18,
                'titulo' => 'Web abandonada',
                'detalle' => 'Copyright del año 2014',
                'certeza' => Certeza::INDICIO->value,
                'datos' => ['anio' => 2014],
            ]],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria);

        $genericas = require resource_path('data/aperturas_sector.php');
        $esperada = $genericas['hosteleria'][$lead->id % 2];

        $this->assertSame($esperada['asunto'], $resultado['asunto']);
        $this->assertStringNotContainsString('2014', $resultado['apertura']);
    }

    public function test_un_secundario_indicio_tambien_se_ignora(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'website_dominio' => 'bar.es',
        ]);

        $auditoria = Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'sin_https',
            'hallazgo_certeza' => Certeza::HECHO->value,
            'hallazgo_secundario_codigo' => 'web_abandonada',
            'hallazgos' => [
                [
                    'codigo' => 'sin_https',
                    'peso' => 30,
                    'titulo' => 'Sin HTTPS seguro',
                    'detalle' => 'La home no usa HTTPS',
                    'certeza' => Certeza::HECHO->value,
                    'datos' => [],
                ],
                [
                    'codigo' => 'web_abandonada',
                    'peso' => 18,
                    'titulo' => 'Web abandonada',
                    'detalle' => 'Copyright 2019',
                    'certeza' => Certeza::INDICIO->value,
                    'datos' => ['anio' => 2019],
                ],
            ],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria, secundario: true);

        $this->assertStringNotContainsString('2019', $resultado['apertura']);
    }

    public function test_una_ausencia_observada_si_se_afirma(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'website_dominio' => 'bar.es',
        ]);

        $auditoria = Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'sin_reservas',
            'hallazgo_certeza' => Certeza::OBSERVADO->value,
            'hallazgos' => [[
                'codigo' => 'sin_reservas',
                'peso' => 22,
                'titulo' => 'Sin sistema de reservas',
                'detalle' => 'No se detectó sistema de reservas online',
                'certeza' => Certeza::OBSERVADO->value,
                'datos' => [],
            ]],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria);

        $this->assertSame('reservar desde la web', $resultado['asunto']);
    }

    /**
     * Auditoría anterior a la columna de certeza: no consta con qué solidez se
     * detectó, así que no se afirma nada y se usa la apertura del sector.
     */
    public function test_sin_certeza_registrada_no_se_genera_apertura_del_hallazgo(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'oficios',
            'website_dominio' => 'ejemplo.es',
        ]);

        $auditoria = Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'web_abandonada',
            'hallazgo_certeza' => null,
            'hallazgos' => [[
                'codigo' => 'web_abandonada',
                'peso' => 18,
                'titulo' => 'Web abandonada',
                'detalle' => 'Copyright del año 2014',
                'datos' => ['anio' => 2014],
            ]],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria);

        $genericas = require resource_path('data/aperturas_sector.php');
        $esperada = $genericas['oficios'][$lead->id % 2];

        $this->assertSame($esperada['asunto'], $resultado['asunto']);
        $this->assertStringNotContainsString('2014', $resultado['apertura']);
    }

    public function test_seguimiento_usa_hallazgo_secundario(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'website_dominio' => 'bar.es',
        ]);

        $auditoria = Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'sin_https',
            'hallazgo_certeza' => Certeza::HECHO->value,
            'hallazgo_secundario_codigo' => 'contacto_roto',
            'hallazgos' => [
                [
                    'codigo' => 'sin_https',
                    'peso' => 40,
                    'titulo' => 'Sin HTTPS seguro',
                    'detalle' => 'La home no usa HTTPS',
                    'certeza' => Certeza::HECHO->value,
                    'datos' => [],
                ],
                [
                    'codigo' => 'contacto_roto',
                    'peso' => 30,
                    'titulo' => 'Página de contacto rota',
                    'detalle' => '/contacto responde 404',
                    'certeza' => Certeza::HECHO->value,
                    'datos' => ['status' => 404],
                ],
            ],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead->fresh(), $auditoria, secundario: true);

        $this->assertStringContainsString('404', $resultado['apertura']);
        $this->assertDoesNotMatchRegularExpression('/candado|no segura/i', $resultado['apertura']);
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
