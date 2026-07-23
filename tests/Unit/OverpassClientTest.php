<?php

namespace Tests\Unit;

use App\Excepciones\OverpassNoDisponible;
use App\Services\Overpass\OverpassClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OverpassClientTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function configBase(): array
    {
        return [
            'endpoints' => [
                'https://overpass-api.de/api/interpreter',
                'https://lz4.overpass-api.de/api/interpreter',
                'https://overpass.kumi.systems/api/interpreter',
            ],
            'timeout' => 90,
            'pausa_peticion_ms' => 0,
            'user_agent' => 'SilgoDevBot/2.0-test',
            'max_ids_por_lote' => 300,
        ];
    }

    private function cliente(): OverpassClient
    {
        return new OverpassClient($this->configBase());
    }

    private function fixtureElements(): array
    {
        $json = json_decode(
            file_get_contents(base_path('tests/Fixtures/json/overpass-restaurantes.json')),
            true
        );

        return $json['elements'];
    }

    public function test_propaga_osm_tag_y_valor_del_filtro(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => $this->fixtureElements()], 200),
        ]);

        $resultados = iterator_to_array($this->cliente()->buscarStream(
            [['nombre' => 'Madrid', 'admin_level' => 6]],
            [['amenity', 'restaurant']]
        ));

        $this->assertNotEmpty($resultados);
        foreach ($resultados as $r) {
            $this->assertSame('amenity', $r['osm_tag']);
            $this->assertSame('restaurant', $r['osm_valor']);
        }
    }

    public function test_propaga_todos_los_tags_en_osm_tags_raw(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => $this->fixtureElements()], 200),
        ]);

        $resultados = iterator_to_array($this->cliente()->buscarStream(
            [['nombre' => 'Madrid', 'admin_level' => 6]],
            [['amenity', 'restaurant']]
        ));

        $sol = collect($resultados)->firstWhere('place_id', 'node/1001');
        $this->assertIsArray($sol['osm_tags_raw']);
        $this->assertSame('spanish', $sol['osm_tags_raw']['cuisine']);
        $this->assertSame('https://restaurantesol.es', $sol['osm_tags_raw']['website']);
    }

    public function test_ignora_elementos_sin_nombre(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => $this->fixtureElements()], 200),
        ]);

        $resultados = iterator_to_array($this->cliente()->buscarStream(
            [['nombre' => 'Madrid', 'admin_level' => 6]],
            [['amenity', 'restaurant']]
        ));

        $this->assertCount(2, $resultados);
    }

    public function test_deduplica_por_place_id(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => $this->fixtureElements()], 200),
        ]);

        $resultados = iterator_to_array($this->cliente()->buscarStream(
            [['nombre' => 'Madrid', 'admin_level' => 6]],
            [['amenity', 'restaurant'], ['amenity', 'bar']]
        ));

        $ids = array_column($resultados, 'place_id');
        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertCount(2, $resultados);
    }

    public function test_toma_coordenadas_de_center_en_ways(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => $this->fixtureElements()], 200),
        ]);

        $resultados = iterator_to_array($this->cliente()->buscarStream(
            [['nombre' => 'Madrid', 'admin_level' => 6]],
            [['amenity', 'restaurant']]
        ));

        $luna = collect($resultados)->firstWhere('place_id', 'way/2002');
        $this->assertSame(40.42, $luna['latitud']);
        $this->assertSame(-3.7, $luna['longitud']);
    }

    public function test_backoff_es_exponencial(): void
    {
        $this->assertSame(1_000_000, OverpassClient::backoffMicrosegundos(0));
        $this->assertSame(2_000_000, OverpassClient::backoffMicrosegundos(1));
        $this->assertSame(4_000_000, OverpassClient::backoffMicrosegundos(2));
        $this->assertSame(8_000_000, OverpassClient::backoffMicrosegundos(3));
        $this->assertSame(16_000_000, OverpassClient::backoffMicrosegundos(4));
        $this->assertSame(16_000_000, OverpassClient::backoffMicrosegundos(5));
    }

    public function test_lanza_si_todos_los_espejos_fallan(): void
    {
        Http::fake([
            '*' => Http::response('timeout', 504),
        ]);

        $this->expectException(OverpassNoDisponible::class);

        iterator_to_array($this->cliente()->buscarStream(
            [['nombre' => 'Madrid', 'admin_level' => 6]],
            [['amenity', 'restaurant']]
        ));
    }

    public function test_buscar_por_ids_agrupa_por_tipo(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => []], 200),
        ]);

        $this->cliente()->buscarPorIds(['node/1', 'node/2', 'way/9']);

        Http::assertSent(function ($request): bool {
            $data = $request->data()['data'] ?? '';

            return str_contains($data, 'node(id:1,2)')
                && str_contains($data, 'way(id:9)');
        });
    }

    public function test_buscar_por_ids_trocea_en_lotes(): void
    {
        Http::fake([
            '*' => Http::response(['elements' => []], 200),
        ]);

        $ids = [];
        for ($i = 1; $i <= 700; $i++) {
            $ids[] = 'node/'.$i;
        }

        $this->cliente()->buscarPorIds($ids);

        Http::assertSentCount(3);
    }
}
