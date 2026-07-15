<?php

namespace Tests\Feature;

use App\Models\HarvestArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarvestAreaCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_area_marca_como_pendiente(): void
    {
        HarvestArea::query()->create([
            'name' => 'Madrid',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_HECHO,
            'priority' => 1,
            'finished_at' => now(),
        ]);

        $this->artisan('harvest:reset-area', ['name' => 'Madrid'])
            ->assertSuccessful();

        $this->assertDatabaseHas('harvest_areas', [
            'name' => 'Madrid',
            'status' => HarvestArea::STATUS_PENDIENTE,
        ]);
    }

    public function test_areas_status_lista_areas(): void
    {
        HarvestArea::query()->create([
            'name' => 'Barcelona',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_PENDIENTE,
            'priority' => 2,
        ]);

        $this->artisan('harvest:areas-status')
            ->assertSuccessful()
            ->expectsOutputToContain('Barcelona');
    }
}
