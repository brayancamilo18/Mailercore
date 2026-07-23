<?php

namespace Tests\Unit;

use App\Services\Inbox\RecortadorCitas;
use Tests\TestCase;

class RecortadorCitasTest extends TestCase
{
    public function test_no_confunde_respuesta_interesada_con_baja(): void
    {
        $cuerpo = <<<'TEXTO'
        Buenas Camilo,

        Sí, me interesa. Mándame lo que has visto y lo vemos.

        Un saludo,
        Marta

        El 15 de julio de 2026, Camilo Silva escribió:
        > Hola,
        >
        > Vuestra web no se adapta al móvil.
        >
        > Un saludo,
        > Camilo Silva
        > silgodev.es
        >
        > ---
        > Camilo Silva, Madrid.
        > Si no quieres recibir más mensajes míos, responde BAJA a este
        > correo o escribe a contacto@silgodev.es.
        TEXTO;

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertStringContainsString('me interesa', $resultado['texto']);
        $this->assertStringNotContainsString('BAJA', $resultado['texto']);
        $this->assertFalse($resultado['solo_citado']);
    }

    public function test_recorta_en_mensaje_original(): void
    {
        $cuerpo = "Vale, gracias.\n\n-- Mensaje original --\nHola, esto es lo antiguo con BAJA al final.";

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertStringContainsString('Vale, gracias', $resultado['texto']);
        $this->assertStringNotContainsString('Mensaje original', $resultado['texto']);
        $this->assertStringNotContainsString('BAJA', $resultado['texto']);
        $this->assertFalse($resultado['solo_citado']);
    }

    public function test_recorta_en_on_wrote_en_ingles(): void
    {
        $cuerpo = "Sounds good, send me details.\n\nOn Mon, Jul 15, 2026 at 10:00 AM Camilo Silva wrote:\n> Hello,\n> responde BAJA if you want out.";

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertStringContainsString('Sounds good', $resultado['texto']);
        $this->assertStringNotContainsString('wrote:', $resultado['texto']);
        $this->assertStringNotContainsString('BAJA', $resultado['texto']);
        $this->assertFalse($resultado['solo_citado']);
    }

    public function test_recorta_en_bloque_de_mayor_que(): void
    {
        $cuerpo = "Perfecto, quedamos así.\n\n> línea citada vieja\n> responde BAJA";

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertStringContainsString('Perfecto, quedamos así', $resultado['texto']);
        $this->assertStringNotContainsString('línea citada', $resultado['texto']);
        $this->assertStringNotContainsString('BAJA', $resultado['texto']);
        $this->assertFalse($resultado['solo_citado']);
    }

    public function test_recorta_en_de_dos_puntos_de_outlook(): void
    {
        $cuerpo = "Ok, mándame el informe.\n\nDe: Camilo Silva <hola@silgodev.es>\nEnviado: lunes\nAsunto: vuestra web\nresponde BAJA";

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertStringContainsString('mándame el informe', $resultado['texto']);
        $this->assertStringNotContainsString('De:', $resultado['texto']);
        $this->assertStringNotContainsString('BAJA', $resultado['texto']);
        $this->assertFalse($resultado['solo_citado']);
    }

    public function test_recorta_enviado_desde_mi_iphone(): void
    {
        $cuerpo = "Sí, me interesa verlo.\n\nEnviado desde mi iPhone";

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertStringContainsString('me interesa', $resultado['texto']);
        $this->assertStringNotContainsString('Enviado desde mi', $resultado['texto']);
        $this->assertFalse($resultado['solo_citado']);
    }

    public function test_texto_sin_citas_se_devuelve_entero(): void
    {
        $cuerpo = "Hola Camilo,\n\nNos interesa. ¿Cuándo podemos hablar?\n\nGracias.";

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertSame(trim($cuerpo), $resultado['texto']);
        $this->assertFalse($resultado['solo_citado']);
    }

    public function test_respuesta_vacia_marca_solo_citado(): void
    {
        $cuerpo = "> Hola,\n> Vuestra web no se adapta.\n> Si no quieres más mensajes, responde BAJA.";

        $resultado = (new RecortadorCitas)->recortar($cuerpo);

        $this->assertTrue($resultado['solo_citado']);
        $this->assertStringContainsString('BAJA', $resultado['texto']);
    }
}
