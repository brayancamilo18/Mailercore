<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HarvestPruneLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_logs_trunca_laravel_log_grande(): void
    {
        $path = storage_path('logs/laravel.log');
        File::ensureDirectoryExists(dirname($path));

        // ~2 MB de ruido
        File::put($path, str_repeat("linea de log de prueba\n", 80_000));

        $this->artisan('harvest:prune-logs --max-log-mb=1 --days=14')
            ->assertSuccessful();

        clearstatcache(true, $path);
        $this->assertTrue(File::exists($path));
        $this->assertLessThan(1.5 * 1024 * 1024, File::size($path));
        $this->assertStringContainsString('truncado por harvest:prune-logs', File::get($path));
    }
}
