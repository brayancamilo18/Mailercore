<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Poda logs, jobs fallidos y batches viejos para no llenar disco/SQLite.
 */
class HarvestPruneLogsCommand extends Command
{
    protected $signature = 'harvest:prune-logs
                            {--days=14 : Días a retener en logs rotados y failed jobs / batches}
                            {--max-log-mb=50 : Si laravel.log supera este tamaño (MB), lo trunca}';

    protected $description = 'Limpia logs, failed_jobs y job_batches antiguos';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $maxLogMb = max(1, (int) $this->option('max-log-mb'));

        $this->pruneLaravelLog($maxLogMb);
        $this->pruneRotatedLogs($days);
        $this->pruneQueueArtifacts($days);

        $this->info("Poda completada (retención {$days} días, log máx. {$maxLogMb} MB).");

        return self::SUCCESS;
    }

    private function pruneLaravelLog(int $maxLogMb): void
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return;
        }

        clearstatcache(true, $path);
        $bytes = (int) filesize($path);
        $limit = $maxLogMb * 1024 * 1024;

        if ($bytes <= $limit) {
            $this->line(sprintf('laravel.log: %.1f MB (OK)', $bytes / 1024 / 1024));

            return;
        }

        // Conserva solo la cola del fichero (últimos ~25% del límite), sin cargar todo en RAM.
        $keep = (int) ($limit * 0.25);
        $fp = fopen($path, 'rb');

        if ($fp === false) {
            $this->warn('No se pudo abrir laravel.log para truncar.');

            return;
        }

        fseek($fp, -$keep, SEEK_END);
        $trimmed = stream_get_contents($fp) ?: '';
        fclose($fp);

        $tmp = $path.'.tmp-prune';
        file_put_contents(
            $tmp,
            '--- truncado por harvest:prune-logs '.now()->toIso8601String()." ---\n".$trimmed
        );
        rename($tmp, $path);
        clearstatcache(true, $path);

        $this->warn(sprintf(
            'laravel.log truncado: %.1f MB → %.1f MB',
            $bytes / 1024 / 1024,
            (int) filesize($path) / 1024 / 1024
        ));
    }

    private function pruneRotatedLogs(int $days): void
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $deleted = 0;

        foreach (File::glob(storage_path('logs/*.log')) as $file) {
            if (basename($file) === 'laravel.log') {
                continue;
            }

            if (File::lastModified($file) < $cutoff) {
                File::delete($file);
                $deleted++;
            }
        }

        $this->line("Logs rotados eliminados: {$deleted}");
    }

    private function pruneQueueArtifacts(int $days): void
    {
        try {
            Artisan::call('queue:prune-failed', ['--hours' => $days * 24]);
            $this->line(trim(Artisan::output()) ?: 'queue:prune-failed OK');
        } catch (\Throwable $e) {
            $this->warn('queue:prune-failed: '.$e->getMessage());
        }

        try {
            Artisan::call('queue:prune-batches', [
                '--hours' => $days * 24,
                '--unfinished' => $days * 24,
                '--cancelled' => $days * 24,
            ]);
            $this->line(trim(Artisan::output()) ?: 'queue:prune-batches OK');
        } catch (\Throwable $e) {
            $this->warn('queue:prune-batches: '.$e->getMessage());
        }
    }
}
