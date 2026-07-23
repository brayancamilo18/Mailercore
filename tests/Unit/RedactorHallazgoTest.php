<?php

namespace Tests\Unit;

use App\Models\Auditoria;
use App\Models\Lead;
use App\Services\Auditoria\RedactorHallazgo;
use Tests\TestCase;

class RedactorHallazgoTest extends TestCase
{
    /** @var list<string> */
    private const CODIGOS = [
        'sin_viewport', 'title_malo', 'sin_meta_description', 'h1_incorrecto',
        'imagenes_sin_alt', 'sin_jsonld', 'sin_https', 'cert_caduca', 'web_abandonada',
        'generador_obsoleto', 'sin_aviso_legal', 'sin_cookies', 'sin_redes', 'sin_formulario',
        'contacto_roto', 'sin_reservas', 'sin_carrito', 'sin_whatsapp', 'html_pesado',
        'respuesta_lenta', 'psi_rendimiento', 'psi_lcp', 'psi_peso', 'psi_seo',
        'psi_accesibilidad',
    ];

    /** @var array<string, mixed> */
    private const DATOS_EJEMPLO = [
        'nombre' => 'Casa Pérez',
        'dominio' => 'ejemplo.es',
        'segundos' => 5.2,
        'porcentaje' => 45,
        'anio' => 2019,
        'kb' => 1800,
        'mb' => 4.5,
        'ms' => 3200,
        'total' => 3,
        'longitud' => 80,
        'status' => 404,
        'dias' => 12,
        'generador' => 'Wix',
        'puntuacion' => 42,
    ];

    public function test_todos_los_codigos_tienen_variante_generica(): void
    {
        $frases = $this->frases();

        foreach (self::CODIGOS as $codigo) {
            $this->assertArrayHasKey($codigo, $frases, "Falta el código {$codigo}");
            $this->assertArrayHasKey('generico', $frases[$codigo], "Falta generico en {$codigo}");
            $this->assertArrayHasKey('asunto', $frases[$codigo]['generico']);
            $this->assertArrayHasKey('apertura', $frases[$codigo]['generico']);
            $this->assertNotSame('', $frases[$codigo]['generico']['asunto']);
            $this->assertNotSame('', $frases[$codigo]['generico']['apertura']);
        }
    }

    public function test_ningun_asunto_supera_45_caracteres(): void
    {
        $frases = $this->frases();
        $redactor = new RedactorHallazgo;

        foreach ($frases as $codigo => $variantes) {
            foreach ($variantes as $sector => $bloque) {
                $asunto = $this->sustituir($bloque['asunto'], self::DATOS_EJEMPLO, $redactor);
                $this->assertLessThanOrEqual(
                    45,
                    mb_strlen($asunto),
                    "Asunto de {$codigo}/{$sector} supera 45: [{$asunto}] (".mb_strlen($asunto).')'
                );
            }
        }
    }

    public function test_ninguna_frase_contiene_palabras_prohibidas(): void
    {
        $prohibidas = [
            'increíble', 'potente', 'profesional', 'revolucionario', 'líder', 'innovador',
            'gratis', 'oferta', 'promoción',
        ];

        $frases = $this->frases();

        foreach ($frases as $codigo => $variantes) {
            foreach ($variantes as $sector => $bloque) {
                $texto = mb_strtolower($bloque['asunto'].' '.$bloque['apertura']);
                foreach ($prohibidas as $palabra) {
                    $this->assertStringNotContainsString(
                        mb_strtolower($palabra),
                        $texto,
                        "Palabra prohibida «{$palabra}» en {$codigo}/{$sector}"
                    );
                }
            }
        }
    }

    public function test_usa_variante_de_sector_si_existe(): void
    {
        $lead = Lead::factory()->make([
            'sector' => 'hosteleria',
            'nombre' => 'Bar Sol',
            'website_dominio' => 'barsol.es',
        ]);

        $auditoria = Auditoria::factory()->make([
            'lead_id' => 1,
            'hallazgo_codigo' => 'sin_viewport',
            'hallazgos' => [
                ['codigo' => 'sin_viewport', 'peso' => 25, 'titulo' => 't', 'detalle' => 'd', 'datos' => []],
            ],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead, $auditoria);

        $this->assertNotNull($resultado);
        $this->assertSame('la carta en el móvil', $resultado['asunto']);
        $this->assertStringContainsString('dónde va a comer', $resultado['apertura']);
    }

    public function test_cae_a_generico_si_no_hay_variante_de_sector(): void
    {
        $lead = Lead::factory()->make([
            'sector' => 'agencias',
            'nombre' => 'Agencia X',
            'website_dominio' => 'agenciax.es',
        ]);

        $auditoria = Auditoria::factory()->make([
            'lead_id' => 1,
            'hallazgo_codigo' => 'sin_formulario',
            'hallazgos' => [
                ['codigo' => 'sin_formulario', 'peso' => 10, 'titulo' => 't', 'detalle' => 'd', 'datos' => []],
            ],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead, $auditoria);

        $this->assertNotNull($resultado);
        $this->assertSame('cómo contactaros', $resultado['asunto']);
        $this->assertStringContainsString('agenciax.es', $resultado['apertura']);
    }

    public function test_devuelve_null_si_falta_el_codigo(): void
    {
        $lead = Lead::factory()->make(['sector' => 'retail']);
        $auditoria = Auditoria::factory()->make([
            'lead_id' => 1,
            'hallazgo_codigo' => null,
            'hallazgos' => [],
        ]);

        $this->assertNull((new RedactorHallazgo)->redactar($lead, $auditoria));
    }

    public function test_devuelve_null_si_queda_un_marcador_sin_sustituir(): void
    {
        $lead = Lead::factory()->make([
            'sector' => 'retail',
            'nombre' => 'Tienda',
            'website_dominio' => 'tienda.es',
        ]);

        // psi_lcp necesita {segundos}; sin datos del hallazgo queda el marcador.
        $auditoria = Auditoria::factory()->make([
            'lead_id' => 1,
            'hallazgo_codigo' => 'psi_lcp',
            'hallazgos' => [
                ['codigo' => 'psi_lcp', 'peso' => 30, 'titulo' => 't', 'detalle' => 'd', 'datos' => []],
            ],
        ]);

        $this->assertNull((new RedactorHallazgo)->redactar($lead, $auditoria));
    }

    public function test_formatea_decimales_con_coma(): void
    {
        $lead = Lead::factory()->make([
            'sector' => 'retail',
            'nombre' => 'Tienda',
            'website_dominio' => 'tienda.es',
        ]);

        $auditoria = Auditoria::factory()->make([
            'lead_id' => 1,
            'hallazgo_codigo' => 'psi_lcp',
            'hallazgos' => [
                [
                    'codigo' => 'psi_lcp',
                    'peso' => 30,
                    'titulo' => 'LCP',
                    'detalle' => '5200 ms',
                    'datos' => ['ms' => 5200, 'segundos' => 5.2],
                ],
            ],
        ]);

        $resultado = (new RedactorHallazgo)->redactar($lead, $auditoria);

        $this->assertNotNull($resultado);
        $this->assertStringContainsString('5,2', $resultado['asunto']);
        $this->assertStringContainsString('5,2 segundos', $resultado['apertura']);
    }

    /**
     * @return array<string, array<string, array{asunto: string, apertura: string}>>
     */
    private function frases(): array
    {
        return require resource_path('data/frases_hallazgo.php');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function sustituir(string $texto, array $datos, RedactorHallazgo $redactor): string
    {
        $ref = new \ReflectionMethod($redactor, 'formatear');
        $ref->setAccessible(true);

        foreach ($datos as $clave => $valor) {
            $texto = str_replace('{'.$clave.'}', $ref->invoke($redactor, $valor), $texto);
        }

        return $texto;
    }
}
