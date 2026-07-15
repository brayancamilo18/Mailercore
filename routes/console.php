<?php

use App\Services\HarvestControl;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envío automático: SOLO si outreach.sending.enabled = true (pausado por defecto).
// Independiente de la cosecha: ahora solo se recogen emails.
Schedule::command('agencies:send')
    ->weekdays()
    ->at('09:30')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('outreach.sending.enabled'));

Schedule::command('outreach:process-inbox')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

$harvestInterval = max(1, min(59, (int) config('outreach.harvest.interval', 5)));

Schedule::command('harvest:run')
    ->cron(sprintf('*/%d * * * *', $harvestInterval))
    ->withoutOverlapping(max(10, (int) config('outreach.harvest.lock_seconds', 900) / 60))
    ->when(fn (): bool => HarvestControl::isEnabled());

Schedule::command('harvest:prune-logs')
    ->weeklyOn(0, '03:15')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping();
