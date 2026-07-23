<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Clasificacion\ClasificadorSector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClasificadorSectorTest extends TestCase
{
    use RefreshDatabase;

    private ClasificadorSector $clasificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clasificador = $this->app->make(ClasificadorSector::class);
    }

    public function test_clasifica_por_filtro_osm(): void
    {
        $lead = Lead::factory()->create([
            'osm_tag' => 'amenity',
            'osm_valor' => 'restaurant',
            'sector' => null,
        ]);

        $resultado = $this->clasificador->clasificar($lead);

        $this->assertSame('hosteleria', $resultado->sector);
        $this->assertSame('osm_filtro', $resultado->metodo);
        $this->assertSame(100, $resultado->confianza);
        $this->assertSame('Restaurante', $resultado->subsector);
    }

    public function test_clasifica_por_tags_raw(): void
    {
        $lead = Lead::factory()->create([
            'osm_tag' => null,
            'osm_valor' => null,
            'osm_tags_raw' => ['amenity' => 'dentist', 'name' => 'Clinica'],
            'sector' => null,
        ]);

        $resultado = $this->clasificador->clasificar($lead);

        $this->assertSame('salud', $resultado->sector);
        $this->assertSame('osm_tags', $resultado->metodo);
        $this->assertSame(95, $resultado->confianza);
    }

    public function test_clasifica_por_jsonld(): void
    {
        $lead = Lead::factory()->create([
            'osm_tag' => null,
            'osm_valor' => null,
            'osm_tags_raw' => null,
            'sector' => null,
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'jsonld_tipos' => ['Dentist'],
        ]);

        $lead->refresh();
        $resultado = $this->clasificador->clasificar($lead);

        $this->assertSame('salud', $resultado->sector);
        $this->assertSame('schema', $resultado->metodo);
        $this->assertSame(80, $resultado->confianza);
    }

    public function test_clasifica_por_heuristica(): void
    {
        $lead = Lead::factory()->create([
            'osm_tag' => null,
            'osm_valor' => null,
            'osm_tags_raw' => null,
            'website_dominio' => 'ejemplo.es',
            'sector' => null,
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'title' => 'Restaurante El Puerto - carta, cocina y reservas',
            'meta_description' => 'Tapas y raciones en terraza',
            'h1_texto' => 'Bienvenidos',
            'jsonld_tipos' => null,
        ]);

        $lead->refresh();
        $resultado = $this->clasificador->clasificar($lead);

        $this->assertSame('hosteleria', $resultado->sector);
        $this->assertSame('heuristica_web', $resultado->metodo);
        $this->assertSame(55, $resultado->confianza);
    }

    public function test_heuristica_no_resuelve_con_pocas_coincidencias(): void
    {
        $lead = Lead::factory()->create([
            'osm_tag' => null,
            'osm_valor' => null,
            'osm_tags_raw' => null,
            'website_dominio' => 'acme-xyz.es',
            'sector' => null,
        ]);

        Pagina::factory()->create([
            'lead_id' => $lead->id,
            'ruta' => '/',
            'title' => 'Empresa Acme',
            'meta_description' => 'Servicios varios',
            'h1_texto' => 'Inicio',
            'jsonld_tipos' => null,
        ]);

        $lead->refresh();
        $resultado = $this->clasificador->clasificar($lead);

        $this->assertSame('sin_clasificar', $resultado->metodo);
        $this->assertNull($resultado->sector);
    }

    public function test_sin_datos_devuelve_sin_clasificar(): void
    {
        $lead = Lead::factory()->sinWeb()->create([
            'osm_tag' => null,
            'osm_valor' => null,
            'osm_tags_raw' => null,
            'sector' => null,
            'website_dominio' => null,
        ]);

        $resultado = $this->clasificador->clasificar($lead);

        $this->assertSame('sin_clasificar', $resultado->metodo);
        $this->assertSame(0, $resultado->confianza);
        $this->assertNull($resultado->sector);
    }

    public function test_aplicar_guarda_metodo_y_confianza(): void
    {
        $lead = Lead::factory()->create([
            'osm_tag' => 'amenity',
            'osm_valor' => 'cafe',
            'sector' => null,
            'clasificacion_metodo' => null,
            'clasificacion_confianza' => null,
        ]);

        $resultado = $this->clasificador->aplicar($lead);
        $lead->refresh();

        $this->assertSame('hosteleria', $lead->sector);
        $this->assertSame('osm_filtro', $lead->clasificacion_metodo);
        $this->assertSame(100, $lead->clasificacion_confianza);
        $this->assertSame($resultado->metodo, $lead->clasificacion_metodo);
    }
}
