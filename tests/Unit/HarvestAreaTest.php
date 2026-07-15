<?php

namespace Tests\Unit;

use App\Models\HarvestArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_pending_respeta_priority_y_salta_hechas(): void
    {
        HarvestArea::query()->create([
            'name' => 'Zamora',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 40,
        ]);

        HarvestArea::query()->create([
            'name' => 'Madrid',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_HECHO,
            'priority' => 1,
            'finished_at' => now(),
        ]);

        HarvestArea::query()->create([
            'name' => 'Barcelona',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 2,
        ]);

        HarvestArea::query()->create([
            'name' => 'Valencia',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_EN_PROCESO,
            'priority' => 3,
            'started_at' => now(),
        ]);

        $next = HarvestArea::nextPending();

        $this->assertNotNull($next);
        $this->assertSame('Barcelona', $next->name);
        $this->assertSame(2, $next->priority);
        $this->assertSame(HarvestArea::STATUS_PENDIENTE, $next->status);
    }

    public function test_next_pending_devuelve_null_si_no_queda_ninguna(): void
    {
        HarvestArea::query()->create([
            'name' => 'Madrid',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_HECHO,
            'priority' => 1,
        ]);

        $this->assertNull(HarvestArea::nextPending());
    }

    public function test_reset_to_pending_limpia_error_y_fechas(): void
    {
        $area = HarvestArea::query()->create([
            'name' => 'Sevilla',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_ERROR,
            'priority' => 4,
            'last_error' => 'timeout Overpass',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        $area->resetToPending();
        $area->refresh();

        $this->assertSame(HarvestArea::STATUS_PENDIENTE, $area->status);
        $this->assertNull($area->last_error);
        $this->assertNull($area->started_at);
        $this->assertNull($area->finished_at);
    }
}
