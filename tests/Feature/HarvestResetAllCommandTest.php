<?php

namespace Tests\Feature;

use App\Models\HarvestArea;
use App\Models\Lead;
use App\Services\HarvestControl;
use App\Services\HarvestHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HarvestResetAllCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_all_borra_leads_y_deja_areas_pendientes(): void
    {
        Bus::fake();

        HarvestArea::query()->create([
            'name' => 'Madrid',
            'admin_level' => 6,
            'status' => HarvestArea::STATUS_HECHO,
            'priority' => 1,
            'leads_found' => 57,
            'emails_found' => 20,
            'finished_at' => now(),
        ]);

        Lead::factory()->count(3)->create();

        DB::table('jobs')->insert([
            'queue' => 'scraping',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->artisan('harvest:reset-all', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Eliminando 3 leads');

        $this->assertSame(0, Lead::query()->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(52, HarvestArea::query()->where('status', HarvestArea::STATUS_PENDIENTE)->count());

        $madrid = HarvestArea::query()->where('name', 'Madrid')->first();
        $this->assertNotNull($madrid);
        $this->assertSame(0, $madrid->leads_found);
        $this->assertSame(0, $madrid->emails_found);
        $this->assertTrue(HarvestControl::isEnabled());
        $this->assertNotNull(HarvestHeartbeat::ageSeconds());
    }
}
