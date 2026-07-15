<?php

namespace App\Console\Commands;

use App\Mail\AgencyOutreachMail;
use App\Models\Lead;
use App\Models\Suppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendOutreachCommand extends Command
{
    protected $signature = 'agencies:send {--limit= : Sobrescribe el límite diario de envíos} {--dry-run : Lista los envíos sin enviar nada}';

    protected $description = 'Envía correos de outreach a los leads listos respetando el warm-up diario';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            return $this->ejecutarEnvio(true);
        }

        // Bloqueo atómico: evita que dos envíos se solapen (doble clic en el
        // panel, schedule + botón, o re-despacho de la cola). Sin esto, dos
        // procesos podrían leer el mismo lead antes de marcarlo y duplicar el correo.
        $lock = Cache::lock('agencies:send', 3600);

        if (! $lock->get()) {
            $this->warn('Ya hay un envío en curso. Se omite esta ejecución.');

            return self::SUCCESS;
        }

        try {
            return $this->ejecutarEnvio(false);
        } finally {
            $lock->release();
        }
    }

    /**
     * Ejecuta el envío (o su simulación en dry-run).
     */
    private function ejecutarEnvio(bool $dryRun): int
    {
        $cfg = config('outreach.sending');

        if (! in_array(now()->dayOfWeekIso, $cfg['send_days'], true) && ! $dryRun) {
            $this->warn('Hoy no es un día de envío configurado. No se envía nada.');

            return self::SUCCESS;
        }

        $alreadyToday = Lead::query()->whereDate('contacted_at', today())->count();
        $totalSent = Lead::query()->whereNotNull('contacted_at')->count();

        $limit = $this->option('limit') !== null
            ? (int) $this->option('limit')
            : $this->effectiveLimit($cfg, $totalSent);

        $remaining = max(0, $limit - $alreadyToday);

        if ($remaining === 0) {
            $this->warn("Cupo diario alcanzado ({$alreadyToday}/{$limit}). No se envía nada.");

            return self::SUCCESS;
        }

        $leads = Lead::readyToSend()->limit($remaining)->get();

        if ($leads->isEmpty()) {
            $this->warn('No hay leads listos para enviar. Lanza primero "php artisan agencies:search".');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se enviará ningún correo.');
        }

        $enviados = 0;
        $total = $leads->count();

        foreach ($leads as $index => $lead) {
            if ($dryRun) {
                $this->line("• {$lead->name} — {$lead->email}");

                continue;
            }

            try {
                if ($lead->email !== null && Suppression::has($lead->email)) {
                    $this->line("⏭ {$lead->name} — omitido (suprimido); no se recontacta");
                    $lead->update(['status' => 'baja']);

                    continue;
                }

                Mail::to($lead->email)->send(new AgencyOutreachMail(
                    agencyName: $lead->name,
                    unsubscribeEmail: $cfg['unsubscribe_email'],
                    unsubscribeUrl: $cfg['unsubscribe_url'] ?? '',
                ));

                $lead->update([
                    'status' => 'contactado',
                    'contacted_at' => now(),
                ]);

                $enviados++;
                $this->line("✅ {$lead->name} — {$lead->email}");
            } catch (\Throwable $e) {
                $this->error("❌ {$lead->name} — {$lead->email}: {$e->getMessage()}");
                report($e);

                continue;
            }

            if ($index < $total - 1) {
                sleep(random_int($cfg['delay_min'], $cfg['delay_max']));
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Resumen (dry-run): {$total} leads listos, límite {$limit}, ya enviados hoy {$alreadyToday}.");
        } else {
            $this->info("Resumen: {$enviados} correos enviados.");
        }

        return self::SUCCESS;
    }

    /**
     * Calcula el límite diario según la etapa de warm-up alcanzada, con techo max_daily.
     *
     * @param  array{warmup: array<int, int>, max_daily: int}  $cfg
     */
    private function effectiveLimit(array $cfg, int $totalSent): int
    {
        $limit = 0;

        foreach ($cfg['warmup'] as $threshold => $value) {
            if ($totalSent >= $threshold) {
                $limit = $value;
            }
        }

        return min($limit, $cfg['max_daily']);
    }
}
