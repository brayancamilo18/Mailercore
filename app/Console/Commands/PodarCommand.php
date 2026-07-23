<?php

namespace App\Console\Commands;

use App\Models\EventoInbox;
use App\Models\Pagina;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PodarCommand extends Command
{
    protected $signature = 'sistema:podar
                            {--dias=30}
                            {--max-log-mb=50}
                            {--dry-run}';

    protected $description = 'Poda logs, failed_jobs, eventos ignorados y páginas antiguas';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $maxLogMb = max(1, (int) $this->option('max-log-mb'));
        $dryRun = (bool) $this->option('dry-run');

        $this->podarLogs($maxLogMb, $dryRun);
        $this->podarFailedJobs($dias, $dryRun);
        $this->podarEventosIgnorados($dryRun);
        $this->podarPaginasAntiguas($dryRun);

        return self::SUCCESS;
    }

    private function podarLogs(int $maxLogMb, bool $dryRun): void
    {
        $limiteBytes = $maxLogMb * 1024 * 1024;
        $conservar = (int) max(1, floor($limiteBytes * 0.25));
        $dir = storage_path('logs');

        foreach (glob($dir.'/*.log') ?: [] as $ruta) {
            $tamano = filesize($ruta);
            if ($tamano === false || $tamano <= $limiteBytes) {
                continue;
            }

            $this->line(sprintf(
                'Log %s: %.1f MB → conservar últimos %.1f MB',
                basename($ruta),
                $tamano / 1024 / 1024,
                $conservar / 1024 / 1024
            ));

            if ($dryRun) {
                continue;
            }

            $this->recortarLog($ruta, $conservar);
        }
    }

    private function recortarLog(string $ruta, int $conservarBytes): void
    {
        $tamano = filesize($ruta);
        if ($tamano === false || $tamano <= $conservarBytes) {
            return;
        }

        $tmp = $ruta.'.podar.tmp';
        $origen = fopen($ruta, 'rb');
        $destino = fopen($tmp, 'wb');

        if ($origen === false || $destino === false) {
            if (is_resource($origen)) {
                fclose($origen);
            }
            if (is_resource($destino)) {
                fclose($destino);
            }

            $this->error("No se pudo abrir {$ruta} para recortar.");

            return;
        }

        fseek($origen, -$conservarBytes, SEEK_END);
        stream_copy_to_stream($origen, $destino);
        fclose($origen);
        fclose($destino);

        rename($tmp, $ruta);
    }

    private function podarFailedJobs(int $dias, bool $dryRun): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->comment('Sin tabla failed_jobs.');

            return;
        }

        $query = DB::table('failed_jobs')
            ->where('failed_at', '<', now()->subDays($dias));

        $n = (clone $query)->count();
        $this->line("failed_jobs > {$dias} días: {$n}");

        if (! $dryRun && $n > 0) {
            $query->delete();
        }
    }

    private function podarEventosIgnorados(bool $dryRun): void
    {
        $query = EventoInbox::query()
            ->where('tipo', 'ignorado')
            ->where('recibido_at', '<', now()->subDays(90));

        $n = (clone $query)->count();
        $this->line("eventos ignorados > 90 días: {$n}");

        if (! $dryRun && $n > 0) {
            $query->delete();
        }
    }

    private function podarPaginasAntiguas(bool $dryRun): void
    {
        $idsConservar = Pagina::query()
            ->get(['id', 'lead_id', 'ruta', 'capturada_at'])
            ->groupBy(fn (Pagina $p): string => $p->lead_id.'|'.($p->ruta ?? ''))
            ->map(function ($grupo) {
                return $grupo->sortByDesc(fn (Pagina $p) => [
                    optional($p->capturada_at)?->timestamp ?? 0,
                    $p->id,
                ])->first()->id;
            })
            ->values()
            ->all();

        $query = Pagina::query()
            ->where('capturada_at', '<', now()->subDays(180))
            ->when(
                $idsConservar !== [],
                fn ($q) => $q->whereNotIn('id', $idsConservar),
                fn ($q) => $q->whereRaw('1 = 0')
            );

        $n = (clone $query)->count();
        $this->line("páginas antiguas (no últimas por ruta): {$n}");

        if (! $dryRun && $n > 0) {
            $query->delete();
        }
    }
}
