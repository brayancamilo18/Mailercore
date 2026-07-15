<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Dispara el orquestador harvest:run (una área pendiente + batch scrape).
 */
class RunSearchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put('outreach:search_running', true, now()->addMinutes(45));
        Cache::put('outreach:search_started_at', now()->toIso8601String(), now()->addMinutes(45));

        try {
            Artisan::call('harvest:run');
        } finally {
            Cache::forget('outreach:search_running');
            Cache::forget('outreach:search_started_at');
            Cache::put('outreach:search_finished_at', now()->toIso8601String(), now()->addHour());
        }
    }
}
