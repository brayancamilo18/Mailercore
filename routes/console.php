<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cosecha continua: procesa todas las áreas pendientes en bucle.
// withoutOverlapping con TTL largo evita solapes; si el proceso muere, el
// mutex caduca y el vigilante libera huérfanas.
Schedule::command('cosecha:ejecutar')
    ->cron('*/'.max(1, min(59, (int) config('outreach.cosecha.intervalo_minutos', 1))).' * * * *')
    ->withoutOverlapping((int) config('outreach.cosecha.lock_segundos', 7200) / 60)
    ->runInBackground();

// Watchdog de resiliencia: detecta y repara procesos parados cada minuto.
Schedule::command('sistema:vigilante')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Registro periódico de salud en el log (para auditoría/alertas externas).
Schedule::command('sistema:salud --json')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/salud.log'));

Schedule::command('envio:planificar')
    ->weekdays()
    ->at('07:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->when(fn (): bool => (bool) config('outreach.envio.activo'));

Schedule::command('envio:despachar')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('envio:recuperar')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('outreach:bandeja')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->when(fn (): bool => \App\Console\Commands\ProcesarBandejaCommand::imapConfigurado());

Schedule::command('emails:verificar --solo-cola')
    ->weekdays()
    ->at('20:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping();

Schedule::command('sistema:podar')
    ->weeklyOn(0, '03:15')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping();
