<?php

namespace Tests\Feature;

use App\Models\AreaCosecha;
use App\Models\PaisCosecha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CosechaPaisesTest extends TestCase
{
    use RefreshDatabase;

    private function sembrarDosPaises(): void
    {
        PaisCosecha::query()->updateOrCreate(
            ['codigo' => 'ES'],
            [
                'nombre' => 'España',
                'prioridad' => 1,
                'max_ciclos' => 3,
                'mapa_motor' => 'spain',
                'estado' => 'pendiente',
                'ciclos_completados' => 0,
            ]
        );
        PaisCosecha::query()->updateOrCreate(
            ['codigo' => 'CO'],
            [
                'nombre' => 'Colombia',
                'prioridad' => 2,
                'max_ciclos' => 3,
                'mapa_motor' => 'geojson',
                'mapa_src' => 'https://example.com/co.json',
                'estado' => 'pendiente',
                'ciclos_completados' => 0,
            ]
        );

        AreaCosecha::query()->updateOrCreate(
            ['pais_codigo' => 'ES', 'nombre' => 'Madrid', 'admin_level' => 6],
            ['estado' => 'pendiente', 'prioridad' => 1]
        );
        AreaCosecha::query()->updateOrCreate(
            ['pais_codigo' => 'CO', 'nombre' => 'Antioquia', 'admin_level' => 4],
            ['estado' => 'pendiente', 'prioridad' => 2]
        );
    }

    public function test_siguiente_pendiente_prioriza_pais_con_menor_prioridad(): void
    {
        $this->sembrarDosPaises();

        $area = AreaCosecha::siguientePendiente();

        $this->assertNotNull($area);
        $this->assertSame('ES', $area->pais_codigo);
        $this->assertSame('Madrid', $area->nombre);
    }

    public function test_pais_se_marca_hecho_tras_max_ciclos(): void
    {
        $this->sembrarDosPaises();
        $es = PaisCosecha::query()->find('ES');
        $madrid = AreaCosecha::query()->where('nombre', 'Madrid')->first();

        for ($i = 0; $i < 3; $i++) {
            $madrid->forceFill(['estado' => 'hecho', 'finalizada_at' => now()])->save();
            $es->refresh();
            $es->avanzarSiCicloCompleto();
            $es->refresh();
            $madrid->refresh();
        }

        $this->assertSame(3, (int) $es->ciclos_completados);
        $this->assertSame('hecho', $es->estado);
        $this->assertTrue($es->completado());
    }

    public function test_pais_hecho_ya_no_aporta_areas_pendientes(): void
    {
        $this->sembrarDosPaises();
        $es = PaisCosecha::query()->find('ES');
        $es->forceFill([
            'ciclos_completados' => 3,
            'estado' => 'hecho',
        ])->save();
        AreaCosecha::query()->where('pais_codigo', 'ES')->update(['estado' => 'pendiente']);

        $area = AreaCosecha::siguientePendiente();

        $this->assertNotNull($area);
        $this->assertSame('CO', $area->pais_codigo);
    }

    public function test_panel_cosecha_lista_paises(): void
    {
        $this->sembrarDosPaises();
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('cosecha.indice', ['pais' => 'CO']))
            ->assertOk()
            ->assertSee('Colombia')
            ->assertSee('Antioquia')
            ->assertSee('Países');
    }
}
