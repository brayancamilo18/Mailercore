<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class HarvestArea extends Model
{
    /** Estados del recorrido de cosecha. */
    public const STATUS_PENDIENTE = 'pendiente';

    public const STATUS_EN_PROCESO = 'en_proceso';

    public const STATUS_HECHO = 'hecho';

    public const STATUS_ERROR = 'error';

    public const STATUSES = [
        self::STATUS_PENDIENTE => 'Pendiente',
        self::STATUS_EN_PROCESO => 'En proceso',
        self::STATUS_HECHO => 'Hecho',
        self::STATUS_ERROR => 'Error',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'admin_level',
        'status',
        'priority',
        'leads_found',
        'emails_found',
        'last_error',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'admin_level' => 'integer',
            'priority' => 'integer',
            'leads_found' => 'integer',
            'emails_found' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Siguiente área pendiente por prioridad (menor primero).
     */
    public static function nextPending(): ?self
    {
        return static::query()
            ->where('status', self::STATUS_PENDIENTE)
            ->orderBy('priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * Áreas ordenadas para el panel/CLI de estado.
     *
     * @param  Builder<HarvestArea>  $query
     * @return Builder<HarvestArea>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderBy('name');
    }

    /**
     * Marca el área como pendiente (reintento).
     */
    public function resetToPending(): void
    {
        $this->update([
            'status' => self::STATUS_PENDIENTE,
            'last_error' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);
    }

    /**
     * Clave de caché con los IDs de leads de la sesión de cosecha en curso.
     */
    public function sessionLeadIdsCacheKey(): string
    {
        return "harvest:area:{$this->id}:lead_ids";
    }

    /**
     * Limpia la sesión de IDs acumulados durante la cosecha.
     */
    public function clearSessionLeadIds(): void
    {
        Cache::forget($this->sessionLeadIdsCacheKey());
    }

    /**
     * Registra un lead creado en la sesión de cosecha (respaldo ante caídas).
     */
    public function rememberSessionLeadId(int $leadId): void
    {
        $key = $this->sessionLeadIdsCacheKey();
        /** @var list<int> $ids */
        $ids = Cache::get($key, []);

        if (! is_array($ids)) {
            $ids = [];
        }

        $ids[] = $leadId;
        Cache::put($key, array_values(array_unique($ids)), now()->addDays(7));
    }

    /**
     * IDs de leads capturados en la ventana temporal de esta cosecha.
     *
     * @return list<int>
     */
    public function leadIdsEnVentana(?Carbon $hasta = null): array
    {
        if ($this->started_at === null) {
            return [];
        }

        $query = Lead::query()->withEmail()->where('captured_at', '>=', $this->started_at);

        if ($hasta !== null) {
            $query->where('captured_at', '<=', $hasta);
        } elseif ($this->finished_at !== null) {
            $query->where('captured_at', '<=', $this->finished_at);
        }

        return $query
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Recalcula leads_found / emails_found a partir de leads reales.
     *
     * @param  list<int>|null  $leadIds
     */
    public function syncStatsFromLeads(?array $leadIds = null): void
    {
        $leadIds ??= $this->leadIdsEnVentana();

        if ($leadIds === []) {
            $cached = Cache::get($this->sessionLeadIdsCacheKey());
            if (is_array($cached) && $cached !== []) {
                $leadIds = array_map(intval(...), $cached);
            }
        }

        $leadsCount = count($leadIds);
        $emailsCount = $leadsCount === 0
            ? 0
            : Lead::query()
                ->whereIn('id', $leadIds)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->count();

        $this->update([
            'leads_found' => $emailsCount,
            'emails_found' => $emailsCount,
        ]);
    }

    /**
     * IDs de sesión (caché) + ventana temporal, deduplicados.
     *
     * @return list<int>
     */
    public function leadIdsDeSesionActual(): array
    {
        $ids = $this->leadIdsEnVentana();
        $cached = Cache::get($this->sessionLeadIdsCacheKey());

        if (is_array($cached)) {
            foreach ($cached as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Stats visibles en panel (lead = email). Recalcula en vivo y persist e si cambia.
     *
     * @return array{leads_found: int, emails_found: int}
     */
    public function statsParaPanel(): array
    {
        if (in_array($this->status, [self::STATUS_EN_PROCESO, self::STATUS_HECHO, self::STATUS_ERROR], true)
            && $this->started_at !== null
        ) {
            $leadIds = $this->leadIdsDeSesionActual();
            $count = $leadIds === []
                ? 0
                : Lead::query()->withEmail()->whereIn('id', $leadIds)->count();

            if ($count !== (int) $this->leads_found || $count !== (int) $this->emails_found) {
                $this->update([
                    'leads_found' => $count,
                    'emails_found' => $count,
                ]);
                $this->refresh();
            }

            return [
                'leads_found' => $count,
                'emails_found' => $count,
            ];
        }

        // Lead ≡ email: unificar columnas si quedaron desfasadas.
        $leads = (int) $this->leads_found;
        $emails = (int) $this->emails_found;
        $unified = max($leads, $emails);

        if ($leads !== $emails) {
            $this->update([
                'leads_found' => $unified,
                'emails_found' => $unified,
            ]);
        }

        return [
            'leads_found' => $unified,
            'emails_found' => $unified,
        ];
    }
}
