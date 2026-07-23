<?php

namespace App\Models;

use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public const ESTADOS = [
        'nuevo' => 'Nuevo',
        'rastreado' => 'Rastreado',
        'auditado' => 'Auditado',
        'en_cola' => 'En cola',
        'contactado' => 'Contactado',
        'seguimiento' => 'Seguimiento enviado',
        'respondido' => 'Respondido',
        'cliente' => 'Cliente',
        'descartado' => 'Descartado',
        'baja' => 'Baja',
        'rebotado' => 'Rebotado',
    ];

    protected $fillable = [
        'place_id', 'nombre', 'website', 'website_dominio', 'osm_tag', 'osm_valor',
        'osm_tags_raw', 'sector', 'subsector', 'clasificacion_metodo',
        'clasificacion_confianza', 'telefono', 'direccion', 'ciudad', 'provincia',
        'codigo_postal', 'latitud', 'longitud', 'fuente', 'estado', 'capturado_at',
        'contactado_at', 'rastreado_at', 'notas',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'osm_tags_raw' => 'array',
            'capturado_at' => 'datetime',
            'contactado_at' => 'datetime',
            'rastreado_at' => 'datetime',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
        ];
    }

    public function emails(): HasMany
    {
        return $this->hasMany(LeadEmail::class);
    }

    public function emailPrincipal(): HasOne
    {
        return $this->hasOne(LeadEmail::class)->where('es_principal', true);
    }

    public function paginas(): HasMany
    {
        return $this->hasMany(Pagina::class);
    }

    public function auditoria(): HasOne
    {
        return $this->hasOne(Auditoria::class);
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    public function scopeConEmail(Builder $query): Builder
    {
        return $query->whereHas('emails');
    }

    public function scopePorSector(Builder $query, string $sector): Builder
    {
        return $query->where('sector', $sector);
    }

    public function scopeSinRastrear(Builder $query): Builder
    {
        return $query->whereNull('rastreado_at')->whereNotNull('website');
    }

    public function scopeSinClasificar(Builder $query): Builder
    {
        return $query->whereNull('sector');
    }

    public function scopeAuditables(Builder $query): Builder
    {
        return $query->whereNotNull('rastreado_at')->whereHas('paginas');
    }

    public function scopeCandidatosEnvio(Builder $query): Builder
    {
        return $query
            ->where('estado', 'auditado')
            ->whereNotNull('sector')
            ->whereHas('auditoria', fn (Builder $q) => $q->whereNotNull('hallazgo_principal'))
            ->whereHas('emails', fn (Builder $q) => $q
                ->where('es_principal', true)
                ->where('estado_verificacion', 'valido'));
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function etiquetaSector(): ?string
    {
        if ($this->sector === null) {
            return null;
        }

        return config("sectores.{$this->sector}.etiqueta");
    }

    public function plantilla(): ?string
    {
        if ($this->sector === null) {
            return null;
        }

        return config("sectores.{$this->sector}.plantilla");
    }

    public function homeCapturada(): ?Pagina
    {
        return $this->paginas()
            ->where(function (Builder $query): void {
                $query->where('ruta', '/')->orWhere('ruta', '');
            })
            ->orderByDesc('capturada_at')
            ->first();
    }
}
