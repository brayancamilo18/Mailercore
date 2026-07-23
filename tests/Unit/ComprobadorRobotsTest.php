<?php

namespace Tests\Unit;

use App\Services\Soporte\ComprobadorRobots;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComprobadorRobotsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_disallow_bloquea_la_ruta(): void
    {
        Http::fake([
            'https://ejemplo.es/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /equipo\n",
                200
            ),
        ]);

        $comprobador = new ComprobadorRobots([
            'respetar_robots' => true,
            'user_agent' => 'SilgoDevBot/2.0',
        ]);

        $this->assertFalse($comprobador->rutaPermitida('https://ejemplo.es', '/equipo'));
        $this->assertTrue($comprobador->rutaPermitida('https://ejemplo.es', '/contacto'));
    }

    public function test_disallow_barra_bloquea_todo(): void
    {
        Http::fake([
            'https://ejemplo.es/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /\n",
                200
            ),
        ]);

        $comprobador = new ComprobadorRobots([
            'respetar_robots' => true,
            'user_agent' => 'SilgoDevBot/2.0',
        ]);

        $this->assertFalse($comprobador->rutaPermitida('https://ejemplo.es', '/'));
        $this->assertFalse($comprobador->rutaPermitida('https://ejemplo.es', '/contacto'));
    }

    public function test_robots_ausente_permite_todo(): void
    {
        Http::fake([
            'https://ejemplo.es/robots.txt' => Http::response('Not Found', 404),
        ]);

        $comprobador = new ComprobadorRobots([
            'respetar_robots' => true,
            'user_agent' => 'SilgoDevBot/2.0',
        ]);

        $this->assertTrue($comprobador->rutaPermitida('https://ejemplo.es', '/equipo'));
    }

    public function test_cachea_por_host(): void
    {
        Http::fake([
            'https://ejemplo.es/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /privado\n",
                200
            ),
        ]);

        $comprobador = new ComprobadorRobots([
            'respetar_robots' => true,
            'user_agent' => 'SilgoDevBot/2.0',
        ]);

        $comprobador->rutaPermitida('https://ejemplo.es', '/a');
        $comprobador->rutaPermitida('https://ejemplo.es', '/b');

        Http::assertSentCount(1);
    }

    public function test_flag_desactivado_permite_todo(): void
    {
        Http::fake([
            'https://ejemplo.es/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /\n",
                200
            ),
        ]);

        $comprobador = new ComprobadorRobots([
            'respetar_robots' => false,
            'user_agent' => 'SilgoDevBot/2.0',
        ]);

        $this->assertTrue($comprobador->rutaPermitida('https://ejemplo.es', '/cualquier-cosa'));
        Http::assertSentCount(0);
    }
}
