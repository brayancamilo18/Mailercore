<?php

namespace Tests\Feature;

use App\Excepciones\PlantillaInvalida;
use App\Mail\CorreoOutreach;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Mensaje;
use App\Models\Pagina;
use App\Services\Auditoria\RedactorHallazgo;
use App\Services\Envio\Renderizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RenderizadorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pie legal de pruebas sin marca ni http, para no falsear las validaciones nuevas.
        config([
            'outreach.envio.remitente.nombre_legal' => 'Camilo',
            'outreach.envio.remitente.direccion' => 'Madrid',
            'outreach.envio.remitente.email_baja' => 'baja@example.com',
            'outreach.envio.remitente.url_baja' => '',
            'outreach.envio.remitente.responder_a' => 'hola@example.com',
        ]);
    }

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

                $cuerpo = explode("\n--\n", explode("\n---", $resultado['texto'])[0])[0];
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

    public function test_cuerpo_no_supera_110_palabras(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);

                $cuerpo = explode("\n--\n", explode("\n---", $resultado['texto'])[0])[0];
                $palabras = str_word_count(strip_tags($cuerpo), 0, 'áéíóúñüÁÉÍÓÚÑÜ');
                $maximo = $paso === 1 ? 110 : 60;

                $this->assertLessThanOrEqual(
                    $maximo,
                    $palabras,
                    "{$sector}-{$paso}: {$palabras} palabras (máx {$maximo})"
                );
            }
        }
    }

    public function test_ninguna_plantilla_tiene_enlaces(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);
                $this->assertSame(
                    0,
                    substr_count($resultado['html'], '<a '),
                    "{$sector}-{$paso} tiene enlaces <a>"
                );
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

    public function test_ninguna_plantilla_menciona_la_marca(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);

                $conjunto = mb_strtolower($resultado['texto'].' '.$resultado['html']);
                $this->assertStringNotContainsString(
                    'silgodev',
                    $conjunto,
                    "{$sector}-{$paso} menciona la marca"
                );
            }
        }
    }

    public function test_ninguna_plantilla_lleva_enlaces(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);

                $this->assertStringNotContainsString(
                    '<a ',
                    $resultado['html'],
                    "{$sector}-{$paso} HTML tiene <a>"
                );
                $this->assertStringNotContainsString(
                    'http',
                    $resultado['texto'],
                    "{$sector}-{$paso} texto tiene http"
                );
            }
        }
    }

    public function test_todas_firman_camilo_silva(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);

                $this->assertStringContainsString(
                    "Camilo Silva\n625 01 50 90",
                    $resultado['texto'],
                    "{$sector}-{$paso} no tiene la firma completa"
                );
                $this->assertStringContainsString(
                    'Camilo Silva',
                    $resultado['html'],
                    "{$sector}-{$paso} HTML sin Camilo Silva"
                );
                $this->assertStringContainsString(
                    '625 01 50 90',
                    $resultado['html'],
                    "{$sector}-{$paso} HTML sin teléfono"
                );
                $this->assertStringNotContainsString(
                    'Desarrollador Web | Soluciones Tecnológicas',
                    $resultado['texto'],
                    "{$sector}-{$paso}: el cargo no debe repetirse en la firma"
                );
            }
        }
    }

    public function test_primer_contacto_incluye_presentacion(): void
    {
        foreach (array_keys(config('sectores')) as $sector) {
            $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), 1);
            $this->assertNotNull($resultado);
            $this->assertStringContainsString(
                'Mi nombre es Camilo Silva, soy desarrollador web con más de 6 años de experiencia y me dedico a mejorar la presencia digital de las empresas en internet.',
                $resultado['texto'],
                "{$sector}-1 sin presentación"
            );
            // La presentación va al inicio (tras el saludo), no oculta al final.
            $posPresentacion = strpos($resultado['texto'], 'Mi nombre es Camilo Silva');
            $posAperturaHint = strpos($resultado['texto'], 'Un saludo');
            $this->assertNotFalse($posPresentacion);
            $this->assertNotFalse($posAperturaHint);
            $this->assertLessThan(
                $posAperturaHint,
                $posPresentacion,
                "{$sector}-1: la presentación debe ir antes del cierre"
            );
        }
    }

    public function test_firma_y_pie_van_unificados(): void
    {
        $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria('hosteleria'), 1);
        $this->assertNotNull($resultado);

        $this->assertStringNotContainsString(
            "\n---\n",
            $resultado['texto'],
            'El pie legal no debe ir en un bloque --- separado'
        );
        $this->assertMatchesRegularExpression(
            "/--\nCamilo Silva\n625 01 50 90\n.+\nSi no quieres recibir más correos míos, responde BAJA/s",
            $resultado['texto']
        );
        $this->assertStringContainsString('responde BAJA', $resultado['html']);
        $this->assertStringNotContainsString('<hr>', $resultado['html']);
    }

    public function test_ninguna_plantilla_suena_comercial(): void
    {
        $prohibidas = [
            'presupuesto', 'reunión', 'servicios', 'woocommerce',
            'brazo técnico', 'sin compromiso', 'atentamente', 'estimado',
        ];

        foreach (array_keys(config('sectores')) as $sector) {
            foreach ([1, 2] as $paso) {
                $resultado = app(Renderizador::class)->renderizar($this->leadConAuditoria($sector), $paso);
                $this->assertNotNull($resultado);

                $conjunto = mb_strtolower($resultado['texto'].' '.$resultado['html']);
                foreach ($prohibidas as $palabra) {
                    $this->assertStringNotContainsString(
                        mb_strtolower($palabra),
                        $conjunto,
                        "{$sector}-{$paso} contiene «{$palabra}»"
                    );
                }
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

        // Inserta una de las palabras comerciales nuevas en el cuerpo.
        file_put_contents($ruta, str_replace('más cómoda', 'presupuesto', $original));

        try {
            $this->expectException(PlantillaInvalida::class);
            app(Renderizador::class)->renderizar($this->leadConAuditoria('hosteleria'), 1);
        } finally {
            file_put_contents($ruta, $original);
        }
    }

    public function test_lanza_si_menciona_la_marca(): void
    {
        $ruta = resource_path('views/emails/texto/hosteleria-1.blade.php');
        $original = file_get_contents($ruta);
        $this->assertNotFalse($original);

        file_put_contents($ruta, str_replace('más cómoda', "más cómoda\nsilgodev", $original));

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
            'outreach.envio.remitente.email_baja' => 'baja@example.com',
            'outreach.envio.remitente.url_baja' => 'https://example.com/baja',
            'outreach.envio.remitente.responder_a' => 'hola@example.com',
        ]);

        Mail::fake();

        $mensaje = Mensaje::factory()->create([
            'asunto' => 'tu web en el móvil',
            'cuerpo_texto' => "Hola,\n\nPrueba.\n\n---\nPie",
            'cuerpo_html' => '<p>Hola,</p><p>Prueba.</p>',
            'message_id' => '<test-123@example.com>',
        ]);

        Mail::to('destino@example.com')->send(new CorreoOutreach($mensaje));

        Mail::assertSent(CorreoOutreach::class, function (CorreoOutreach $mail): bool {
            $texto = $mail->headers()->text;

            return isset($texto['List-Unsubscribe'])
                && str_contains($texto['List-Unsubscribe'], 'mailto:baja@example.com')
                && str_contains($texto['List-Unsubscribe'], 'https://example.com/baja')
                && ($texto['List-Unsubscribe-Post'] ?? null) === 'List-Unsubscribe=One-Click';
        });
    }

    public function test_ninguna_apertura_afirma_carencias(): void
    {
        $prohibidas = [
            'no tienes', 'te falta', 'no se ve', 'está abandonada', 'esta abandonada',
            'tardas', 'problema', 'error', 'fallo', 'mal hecho',
        ];

        $textos = [];

        foreach (require resource_path('data/aperturas_sector.php') as $variantes) {
            foreach ($variantes as $bloque) {
                $textos[] = $bloque['asunto'].' '.$bloque['apertura'];
            }
        }

        foreach (require resource_path('data/aperturas_https.php') as $bloque) {
            $textos[] = $bloque['asunto'].' '.$bloque['apertura'];
        }

        foreach ($textos as $texto) {
            $minusculas = mb_strtolower($texto);
            foreach ($prohibidas as $palabra) {
                $this->assertStringNotContainsString(
                    mb_strtolower($palabra),
                    $minusculas,
                    "Apertura contiene «{$palabra}»: {$texto}"
                );
            }
        }
    }

    public function test_solo_https_puede_mencionar_algo_concreto(): void
    {
        $lead = $this->leadConAuditoria('hosteleria', esHttps: true);
        $resultado = app(Renderizador::class)->renderizar($lead, 1);
        $this->assertNotNull($resultado);

        $apertura = app(RedactorHallazgo::class)->redactar($lead)['apertura'];
        $minusculas = mb_strtolower($apertura.' '.$resultado['texto']);

        $this->assertStringNotContainsString('https', $minusculas);
        $this->assertStringNotContainsString('candado', $minusculas);
        $this->assertStringNotContainsString('no segura', $minusculas);
    }

    public function test_lead_con_https_correcto_recibe_apertura_generica(): void
    {
        $lead = $this->leadConAuditoria('hosteleria', esHttps: true);
        $resultado = app(Renderizador::class)->renderizar($lead, 1);
        $this->assertNotNull($resultado);

        $apertura = app(RedactorHallazgo::class)->redactar($lead)['apertura'];
        $esperadas = $this->aperturasRellenas('hosteleria', $lead);

        $this->assertContains($apertura, $esperadas);
        $this->assertStringContainsString($apertura, $resultado['texto']);
    }

    public function test_lead_sin_https_recibe_apertura_de_https(): void
    {
        $lead = $this->leadConAuditoria('hosteleria', esHttps: false);
        $resultado = app(Renderizador::class)->renderizar($lead, 1);
        $this->assertNotNull($resultado);

        $apertura = app(RedactorHallazgo::class)->redactar($lead)['apertura'];
        $minusculas = mb_strtolower($apertura);

        $this->assertTrue(
            str_contains($minusculas, 'https') || str_contains($minusculas, 'candado'),
            "Se esperaba apertura HTTPS, llegó: {$apertura}"
        );
        $this->assertStringContainsString($apertura, $resultado['texto']);
    }

    public function test_dos_leads_del_mismo_sector_pueden_recibir_variantes_distintas(): void
    {
        $par = $this->leadConAuditoria('retail', esHttps: true);
        $impar = $this->leadConAuditoria('retail', esHttps: true);

        // Garantiza distinta paridad de id (variante = id % 2).
        if ($par->id % 2 !== 0) {
            [$par, $impar] = [$impar, $par];
        }
        if ($impar->id % 2 === 0) {
            $impar = $this->leadConAuditoria('retail', esHttps: true);
            while ($impar->id % 2 === 0) {
                $impar = $this->leadConAuditoria('retail', esHttps: true);
            }
        }

        $this->assertSame(0, $par->id % 2);
        $this->assertSame(1, $impar->id % 2);

        $aperturaPar = app(RedactorHallazgo::class)->redactar($par)['apertura'];
        $aperturaImpar = app(RedactorHallazgo::class)->redactar($impar)['apertura'];

        $this->assertNotSame($aperturaPar, $aperturaImpar);
    }

    public function test_seguimiento_no_menciona_https_ni_hallazgos(): void
    {
        $lead = $this->leadConAuditoria('hosteleria', esHttps: false);
        $resultado = app(Renderizador::class)->renderizar($lead, 2);
        $this->assertNotNull($resultado);

        $apertura = app(RedactorHallazgo::class)->redactar($lead, secundario: true)['apertura'];
        $minusculas = mb_strtolower($apertura.' '.$resultado['texto']);

        $this->assertStringNotContainsString('https', $minusculas);
        $this->assertStringNotContainsString('candado', $minusculas);
        $this->assertStringNotContainsString('no segura', $minusculas);
        $this->assertStringNotContainsString('viewport', $minusculas);
        $this->assertStringNotContainsString('trompicones', $minusculas);
    }

    public function test_correo_no_afirma_datos_de_auditoria_interna(): void
    {
        $anioCopyright = 1998;

        $lead = Lead::factory()->create([
            'sector' => 'hosteleria',
            'nombre' => 'Bar Prueba',
            'website' => 'https://barprueba.es',
            'website_dominio' => 'barprueba.es',
            'estado' => 'auditado',
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'es_https' => true,
            'anio_copyright' => $anioCopyright,
        ]);

        Auditoria::factory()->create([
            'lead_id' => $lead->id,
            'hallazgo_codigo' => 'sin_reservas',
            'hallazgo_secundario_codigo' => 'web_abandonada',
            'hallazgo_principal' => 'No tiene reservas online',
            'hallazgos' => [
                [
                    'codigo' => 'sin_reservas',
                    'peso' => 30,
                    'titulo' => 'Sin reservas',
                    'detalle' => 'No se detectó sistema de reservas',
                    'datos' => [],
                ],
                [
                    'codigo' => 'web_abandonada',
                    'peso' => 25,
                    'titulo' => 'Web abandonada',
                    'detalle' => "Copyright de {$anioCopyright}",
                    'datos' => ['anio' => $anioCopyright],
                ],
            ],
        ]);

        $resultado = app(Renderizador::class)->renderizar(
            $lead->fresh(['auditoria', 'paginas']),
            1
        );
        $this->assertNotNull($resultado);

        $conjunto = mb_strtolower($resultado['texto'].' '.$resultado['html']);
        $this->assertStringNotContainsString('reservas', $conjunto);
        $this->assertStringNotContainsString('abandonada', $conjunto);
        $this->assertStringNotContainsString((string) $anioCopyright, $conjunto);
    }

    /**
     * @return list<string>
     */
    private function aperturasRellenas(string $sector, Lead $lead): array
    {
        $todas = require resource_path('data/aperturas_sector.php');
        $variantes = $todas[$sector] ?? $todas['generico'];
        $sustituciones = [
            '{dominio}' => $lead->website_dominio ?? 'tu web',
            '{nombre}' => $lead->nombre ?? 'tu negocio',
        ];

        return array_map(
            fn (array $bloque): string => strtr($bloque['apertura'], $sustituciones),
            $variantes
        );
    }

    private function leadConAuditoria(string $sector, ?bool $esHttps = null): Lead
    {
        $lead = Lead::factory()->create([
            'sector' => $sector,
            'nombre' => 'Negocio Prueba',
            'website' => 'https://ejemplo.es',
            'website_dominio' => 'ejemplo.es',
            'estado' => 'auditado',
        ]);

        if ($esHttps !== null) {
            Pagina::factory()->create([
                'lead_id' => $lead->id,
                'ruta' => '/',
                'es_https' => $esHttps,
            ]);
        }

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

        return $lead->fresh(['auditoria', 'paginas']);
    }
}
