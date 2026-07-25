<?php

namespace Tests\Unit;

use App\Models\AreaCosecha;
use App\Support\ProvinciasIne;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProvinciasIneTest extends TestCase
{
    #[Test]
    public function catalogo_tiene_52_provincias(): void
    {
        $this->assertCount(52, ProvinciasIne::todas());
    }

    #[Test]
    public function mapea_nombres_del_seeder_a_codigos_ine(): void
    {
        $this->assertSame('28', ProvinciasIne::codigoDeNombre('Madrid'));
        $this->assertSame('48', ProvinciasIne::codigoDeNombre('Vizcaya'));
        $this->assertSame('20', ProvinciasIne::codigoDeNombre('Guipúzcoa'));
        $this->assertSame('07', ProvinciasIne::codigoDeNombre('Baleares'));
        $this->assertSame('01', ProvinciasIne::codigoDeNombre('Álava'));
    }

    #[Test]
    public function statuses_traduce_en_proceso_a_proceso(): void
    {
        $area = new AreaCosecha(['nombre' => 'Madrid', 'estado' => 'en_proceso']);
        $statuses = ProvinciasIne::statusesDesdeAreas([$area]);

        $this->assertSame(['28' => 'proceso'], $statuses);
    }
}
