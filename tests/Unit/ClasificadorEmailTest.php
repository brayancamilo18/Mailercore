<?php

namespace Tests\Unit;

use App\Services\Web\ClasificadorEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClasificadorEmailTest extends TestCase
{
    private ClasificadorEmail $clasificador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clasificador = new ClasificadorEmail;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function casosClasificacion(): array
    {
        return [
            'info rol' => ['info@restaurante.es', ClasificadorEmail::ROL],
            'contacto rol' => ['contacto@clinica.com', ClasificadorEmail::ROL],
            'mayusculas rol' => ['INFO@Hotel.ES', ClasificadorEmail::ROL],
            'info2 rol' => ['info2@tienda.es', ClasificadorEmail::ROL],
            'atencion cliente rol' => ['atencion.cliente@empresa.es', ClasificadorEmail::ROL],
            'reservas rol' => ['reservas@hotelcentral.com', ClasificadorEmail::ROL],
            'citas rol' => ['citas@dentista.es', ClasificadorEmail::ROL],
            'comercial rol' => ['comercial@joyeria.es', ClasificadorEmail::ROL],
            'maria personal' => ['maria@restaurante.es', ClasificadorEmail::PERSONAL],
            'maria lopez personal' => ['maria.lopez@restaurante.es', ClasificadorEmail::PERSONAL],
            'j perez personal' => ['j.perez@despacho.es', ClasificadorEmail::PERSONAL],
            'carlos ruiz personal' => ['carlos_ruiz@empresa.com', ClasificadorEmail::PERSONAL],
            'jordi personal' => ['jordi@estudi.cat', ClasificadorEmail::PERSONAL],
            'info gmail personal' => ['info@gmail.com', ClasificadorEmail::PERSONAL],
            'contacto hotmail personal' => ['contacto@hotmail.es', ClasificadorEmail::PERSONAL],
            'yahoo personal' => ['cualquiera@yahoo.es', ClasificadorEmail::PERSONAL],
            'sentry ruido' => ['algo@sentry.io', ClasificadorEmail::RUIDO],
            'wixpress ruido' => ['x@wixpress.com', ClasificadorEmail::RUIDO],
            'png ruido' => ['logo@empresa.es.png', ClasificadorEmail::RUIDO],
            'hash ruido' => ['a3f9c2b1d4e5f6a7b8c9@empresa.es', ClasificadorEmail::RUIDO],
            'sin arroba invalido' => ['sinarroba', ClasificadorEmail::INVALIDO],
            'solo dominio invalido' => ['@empresa.es', ClasificadorEmail::INVALIDO],
            'sin dominio invalido' => ['info@', ClasificadorEmail::INVALIDO],
        ];
    }

    #[DataProvider('casosClasificacion')]
    public function test_clasifica_casos(string $email, string $esperado): void
    {
        $this->assertSame($esperado, $this->clasificador->clasificar($email));
    }

    public function test_punto_inicial_es_ruido_o_invalido(): void
    {
        $resultado = $this->clasificador->clasificar('.info@empresa.es');

        $this->assertContains($resultado, [
            ClasificadorEmail::RUIDO,
            ClasificadorEmail::INVALIDO,
        ]);
    }

    public function test_prioridad_info_es_cero(): void
    {
        $this->assertSame(0, $this->clasificador->prioridad('info@x.es'));
    }

    public function test_prioridad_reservas_es_uno(): void
    {
        $this->assertSame(1, $this->clasificador->prioridad('reservas@x.es'));
    }

    public function test_prioridad_desconocido_es_nueve(): void
    {
        $this->assertSame(9, $this->clasificador->prioridad('zxqw@x.es'));
    }

    public function test_info_gana_a_reservas(): void
    {
        $this->assertLessThan(
            $this->clasificador->prioridad('reservas@x.es'),
            $this->clasificador->prioridad('info@x.es')
        );
    }
}
