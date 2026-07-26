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
    // TTL del mutex de schedule: si el proceso muere, no bloquear horas.
    // El lock real de negocio es Cache::lock('cosecha:run').
    ->withoutOverlapping(30)
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

// La noche anterior prepara la cola del siguiente día de envío (domingo → lunes).
Schedule::command('envio:planificar --proximo')
    ->dailyAt('20:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->when(function (): bool {
        if (! (bool) config('outreach.envio.activo')) {
            return false;
        }

        $dias = array_map('intval', config('outreach.envio.dias', [1, 2, 3, 4]));
        $manana = now('Europe/Madrid')->addDay();

        return in_array($manana->dayOfWeekIso, $dias, true);
    });

// Red de seguridad: si la noche anterior falló, planifica el día en curso a las 07:00.
Schedule::command('envio:planificar')
    ->weekdays()
    ->at('07:00')
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->when(function (): bool {
        if (! (bool) config('outreach.envio.activo')) {
            return false;
        }

        $dias = array_map('intval', config('outreach.envio.dias', [1, 2, 3, 4]));

        return in_array(now('Europe/Madrid')->dayOfWeekIso, $dias, true);
    });

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
