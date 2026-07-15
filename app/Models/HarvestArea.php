<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
}
