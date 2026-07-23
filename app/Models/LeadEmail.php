<?php

namespace App\Models;

use Database\Factories\LeadEmailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadEmail extends Model
{
    /** @use HasFactory<LeadEmailFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_id', 'email', 'tipo', 'prefijo', 'origen', 'url_origen',
        'es_principal', 'prioridad', 'mx_ok', 'es_catch_all',
        'estado_verificacion', 'verificado_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'mx_ok' => 'boolean',
            'es_catch_all' => 'boolean',
            'verificado_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeValidos(Builder $query): Builder
    {
        return $query->where('estado_verificacion', 'valido');
    }

    public function scopeVerificacionVigente(Builder $query): Builder
    {
        $dias = (int) config('outreach.verificador.validez_verificacion_dias');

        return $query->where('verificado_at', '>=', now()->subDays($dias));
    }

    public function necesitaVerificacion(): bool
    {
        if ($this->verificado_at === null) {
            return true;
        }

        $dias = (int) config('outreach.verificador.validez_verificacion_dias');

        return $this->verificado_at->lt(now()->subDays($dias));
    }
}
