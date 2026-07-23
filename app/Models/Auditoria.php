<?php

namespace App\Models;

use Database\Factories\AuditoriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    /** @use HasFactory<AuditoriaFactory> */
    use HasFactory;

    protected $table = 'auditorias';

    protected $fillable = [
        'lead_id', 'puntuacion', 'hallazgo_codigo', 'hallazgo_principal',
        'hallazgo_secundario_codigo', 'hallazgo_secundario', 'hallazgos',
        'psi_rendimiento', 'psi_seo', 'psi_accesibilidad', 'psi_buenas_practicas',
        'psi_lcp_ms', 'psi_cls', 'psi_tbt_ms', 'psi_peso_kb',
        'psi_solicitado_at', 'psi_error', 'auditada_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hallazgos' => 'array',
            'psi_cls' => 'decimal:3',
            'psi_solicitado_at' => 'datetime',
            'auditada_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeConPsi(Builder $query): Builder
    {
        return $query->whereNotNull('psi_rendimiento');
    }

    public function scopePsiCaducado(Builder $query): Builder
    {
        $dias = (int) config('outreach.pagespeed.validez_dias');

        return $query->where(function (Builder $q) use ($dias): void {
            $q->whereNull('psi_solicitado_at')
                ->orWhere('psi_solicitado_at', '<', now()->subDays($dias));
        });
    }

    /**
     * @return list<array{codigo:string,peso:int,titulo:string,detalle:string}>
     */
    public function hallazgosOrdenados(): array
    {
        if ($this->hallazgos === null) {
            return [];
        }

        $hallazgos = $this->hallazgos;
        usort($hallazgos, fn (array $a, array $b): int => ($b['peso'] ?? 0) <=> ($a['peso'] ?? 0));

        return array_values($hallazgos);
    }

    public function tienePsi(): bool
    {
        return $this->psi_rendimiento !== null;
    }

    public function segundosLcp(): ?float
    {
        if ($this->psi_lcp_ms === null) {
            return null;
        }

        return round($this->psi_lcp_ms / 1000, 1);
    }
}
