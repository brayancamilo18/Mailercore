<?php

namespace Tests\Unit;

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

    public function test_aperturas_sector_no_superan_40_caracteres_en_asunto(): void
    {
        $aperturas = require resource_path('data/aperturas_sector.php');

        foreach ($aperturas as $sector => $variantes) {
            foreach ($variantes as $i => $bloque) {
                $this->assertLessThanOrEqual(
                    40,
                    mb_strlen($bloque['asunto']),
                    "Asunto de {$sector}/{$i} supera 40: [{$bloque['asunto']}]"
                );
            }
        }
    }

    public function test_aperturas_sector_sin_afirmaciones_peligrosas(): void
    {
        $aperturas = require resource_path('data/aperturas_sector.php');
        $patron = '/no tienes|te falta|tardas|no se ve|abandonada|problema|error/i';

        foreach ($aperturas as $sector => $variantes) {
            foreach ($variantes as $i => $bloque) {
                $texto = $bloque['asunto'].' '.$bloque['apertura'];
                $this->assertDoesNotMatchRegularExpression(
                    $patron,
                    $texto,
                    "Afirmación peligrosa en {$sector}/{$i}"
                );
            }
        }
    }

    public function test_usa_apertura_generica_del_sector(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'nombre' => 'Bar Paco',
            'website_dominio' => 'ejemplo.es',
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead);

        $this->assertNotNull($resultado);
        $hosteleria = require resource_path('data/aperturas_sector.php');
        $esperada = $hosteleria['hosteleria'][$lead->id % 2];

        $this->assertSame($esperada['asunto'], $resultado['asunto']);
        $this->assertStringContainsString(
            str_contains($esperada['apertura'], '{dominio}') ? 'ejemplo.es' : 'Bar Paco',
            $resultado['apertura']
        );
        $this->assertDoesNotMatchRegularExpression(
            '/no tienes|te falta|tardas|no se ve|abandonada|problema|error/i',
            $resultado['apertura']
        );
    }

    public function test_cae_a_generico_si_sector_desconocido(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'sector_inventado',
            'nombre' => 'Negocio X',
            'website_dominio' => 'negociox.es',
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead);
        $generico = require resource_path('data/aperturas_sector.php');
        $esperada = $generico['generico'][$lead->id % 2];

        $this->assertNotNull($resultado);
        $this->assertSame($esperada['asunto'], $resultado['asunto']);
        $this->assertStringContainsString('negociox.es', $resultado['apertura']);
    }

    public function test_usa_apertura_https_si_falta_candado(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'nombre' => 'Bar Paco',
            'website_dominio' => 'ejemplo.es',
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'es_https' => false,
        ]);

        $lead->refresh()->load('paginas');

        $resultado = (new RedactorHallazgo)->redactar($lead);
        $https = require resource_path('data/aperturas_https.php');
        $esperada = $https[$lead->id % 2];

        $this->assertNotNull($resultado);
        $this->assertSame($esperada['asunto'], $resultado['asunto']);
        $this->assertStringContainsString('ejemplo.es', $resultado['apertura']);
        $this->assertMatchesRegularExpression('/HTTPS|candado/i', $resultado['apertura']);
    }

    public function test_seguimiento_nunca_usa_https_y_elige_otra_variante(): void
    {
        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'nombre' => 'Bar Paco',
            'website_dominio' => 'ejemplo.es',
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'es_https' => false,
        ]);

        $lead->refresh()->load('paginas');

        $primero = (new RedactorHallazgo)->redactar($lead, secundario: false);
        $segundo = (new RedactorHallazgo)->redactar($lead, secundario: true);

        $hosteleria = (require resource_path('data/aperturas_sector.php'))['hosteleria'];
        $esperadaSeguimiento = $hosteleria[($lead->id + 1) % 2];

        $this->assertNotNull($primero);
        $this->assertNotNull($segundo);
        $this->assertMatchesRegularExpression('/HTTPS|candado/i', $primero['apertura']);
        $this->assertDoesNotMatchRegularExpression('/HTTPS|candado/i', $segundo['apertura']);
        $this->assertSame($esperadaSeguimiento['asunto'], $segundo['asunto']);
        $this->assertNotSame($primero['asunto'], $segundo['asunto']);
    }

    /**
     * @return array<string, array<string, array{asunto: string, apertura: string}>>
     */
    private function frasesHallazgo(): array
    {
        return require resource_path('data/frases_hallazgo.php');
    }
}
