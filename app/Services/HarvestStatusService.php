<?php

namespace App\Services;

use App\Models\HarvestArea;
use App\Models\Lead;

/**
 * Snapshot del recorrido de cosecha para panel, JSON y CLI.
 */
class HarvestStatusService
{
    /**
     * @return array{
     *     enabled: bool,
     *     paused: bool,
     *     area_en_proceso: ?array{id: int, name: string, started_at: ?string},
     *     areas_hechas: int,
     *     areas_total: int,
     *     areas_pendientes: int,
     *     areas_error: int,
     *     areas_en_proceso: int,
     *     progress_percent: float,
     *     leads_total: int,
     *     emails_total: int,
     *     emails_hoy: int,
     *     leads_found_sum: int,
     *     emails_found_sum: int,
     *     heartbeat_at: ?string,
     *     heartbeat_age_seconds: ?int,
     *     heartbeat_ok: bool,
     *     heartbeat_stale: bool,
     *     heartbeat_source: ?string,
     *     ultimas_areas: list<array{name: string, status: string, leads_found: int, emails_found: int, finished_at: ?string}>
     * }
     */
    public function snapshot(): array
    {
        $enabled = HarvestControl::isEnabled();

        $total = HarvestArea::query()->count();
        $hechas = HarvestArea::query()->where('status', HarvestArea::STATUS_HECHO)->count();
        $pendientes = HarvestArea::query()->where('status', HarvestArea::STATUS_PENDIENTE)->count();
        $errores = HarvestArea::query()->where('status', HarvestArea::STATUS_ERROR)->count();
        $enProcesoCount = HarvestArea::query()->where('status', HarvestArea::STATUS_EN_PROCESO)->count();

        $enProceso = HarvestArea::query()
            ->where('status', HarvestArea::STATUS_EN_PROCESO)
            ->orderByDesc('started_at')
            ->first();

        $progress = $total > 0 ? round(($hechas / $total) * 100, 1) : 0.0;

        $rawHb = \Illuminate\Support\Facades\Cache::get(HarvestHeartbeat::CACHE_KEY);
        $source = is_array($rawHb) ? ($rawHb['source'] ?? null) : null;

        $ultimas = HarvestArea::query()
            ->whereIn('status', [HarvestArea::STATUS_HECHO, HarvestArea::STATUS_ERROR, HarvestArea::STATUS_EN_PROCESO])
            ->orderByDesc('finished_at')
            ->orderByDesc('started_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (HarvestArea $a): array => [
                'name' => $a->name,
                'status' => $a->status,
                'leads_found' => (int) $a->leads_found,
                'emails_found' => (int) $a->emails_found,
                'finished_at' => $a->finished_at?->toIso8601String(),
            ])
            ->all();

        $age = HarvestHeartbeat::ageSeconds();

        return [
            'enabled' => $enabled,
            'paused' => ! $enabled,
            'area_en_proceso' => $enProceso === null ? null : [
                'id' => $enProceso->id,
                'name' => $enProceso->name,
                'started_at' => $enProceso->started_at?->toIso8601String(),
            ],
            'areas_hechas' => $hechas,
            'areas_total' => $total,
            'areas_pendientes' => $pendientes,
            'areas_error' => $errores,
            'areas_en_proceso' => $enProcesoCount,
            'progress_percent' => $progress,
            'leads_total' => Lead::query()->count(),
            'emails_total' => Lead::query()->whereNotNull('email')->where('email', '!=', '')->count(),
            'emails_hoy' => Lead::query()
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereDate('updated_at', today())
                ->count(),
            'leads_found_sum' => (int) HarvestArea::query()->sum('leads_found'),
            'emails_found_sum' => (int) HarvestArea::query()->sum('emails_found'),
            'heartbeat_at' => HarvestHeartbeat::lastCarbon()?->toIso8601String(),
            'heartbeat_age_seconds' => $age,
            'heartbeat_ok' => HarvestHeartbeat::isFresh(),
            'heartbeat_stale' => HarvestHeartbeat::isStale(),
            'heartbeat_source' => is_string($source) ? $source : null,
            'ultimas_areas' => $ultimas,
        ];
    }
}
