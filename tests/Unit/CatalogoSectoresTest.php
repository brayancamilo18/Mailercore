<?php

namespace Tests\Unit;

use App\Services\Clasificacion\CatalogoSectores;
use Tests\TestCase;

class CatalogoSectoresTest extends TestCase
{
    private CatalogoSectores $catalogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogo = $this->app->make(CatalogoSectores::class);
    }

    public function test_todos_los_pares_del_config_resuelven(): void
    {
        foreach (config('sectores') as $familia => $datos) {
            foreach ($datos['tags'] as [$tag, $valor]) {
                $this->assertSame(
                    $familia,
                    $this->catalogo->porTag($tag, $valor),
                    "El par [{$tag}, {$valor}] debería resolver a {$familia}"
                );
            }
        }
    }

    public function test_prioridad_menor_gana(): void
    {
        $this->assertSame(
            'hosteleria',
            $this->catalogo->porTagsRaw([
                'amenity' => 'restaurant',
                'office' => 'company',
            ])
        );
    }

    public function test_tag_desconocido_devuelve_null(): void
    {
        $this->assertNull($this->catalogo->porTag('amenity', 'fuente'));
    }

    public function test_schema_restaurant_es_hosteleria(): void
    {
        $this->assertSame('hosteleria', $this->catalogo->porTipoSchema('Restaurant'));
    }

    public function test_schema_insensible_a_mayusculas(): void
    {
        $this->assertSame('hosteleria', $this->catalogo->porTipoSchema('restaurant'));
    }

    public function test_subsector_legible(): void
    {
        $this->assertSame('Restaurante', $this->catalogo->subsector('amenity', 'restaurant'));
    }

    public function test_todos_los_pares_tienen_subsector(): void
    {
        foreach (config('sectores') as $familia => $datos) {
            foreach ($datos['tags'] as [$tag, $valor]) {
                $this->assertNotNull(
                    $this->catalogo->subsector($tag, $valor),
                    "Falta subsector para [{$tag}, {$valor}] en {$familia}"
                );
            }
        }
    }
}
