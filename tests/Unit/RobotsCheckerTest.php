<?php

namespace Tests\Unit;

use App\Services\RobotsChecker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RobotsCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_bloquea_ruta_disallow_y_cachea_por_host(): void
    {
        Http::fake([
            'https://ejemplo-robots.test/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /privado\n",
                200
            ),
        ]);

        $checker = new RobotsChecker([
            'timeout' => 5,
            'user_agent' => 'SilgoDevBot/1.0',
            'respect_robots' => true,
        ]);

        $this->assertFalse($checker->isPathAllowed('https://ejemplo-robots.test', '/privado'));
        $this->assertTrue($checker->isPathAllowed('https://ejemplo-robots.test', '/contacto'));

        // Segunda llamada: no debe volver a pedir robots.txt (caché por dominio).
        $this->assertFalse($checker->isUrlAllowed('https://ejemplo-robots.test/privado/x'));

        Http::assertSentCount(1);
    }

    public function test_respeta_flag_desactivado(): void
    {
        Http::fake();

        $checker = new RobotsChecker([
            'respect_robots' => false,
            'user_agent' => 'SilgoDevBot/1.0',
        ]);

        $this->assertTrue($checker->isPathAllowed('https://ejemplo-robots.test', '/cualquier'));
        Http::assertNothingSent();
    }
}
