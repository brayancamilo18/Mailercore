<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    /** @use HasFactory<\Database\Factories\LeadFactory> */
    use HasFactory;

    /** Mapa de estados internos a etiquetas legibles. */
    public const ESTADOS = [
        'nuevo' => 'Nuevo',
        'sin_email' => 'Sin email',
        'contactado' => 'Contactado',
        'respondido' => 'Respondido',
        'cliente' => 'Cliente',
        'descartado' => 'Descartado',
        'baja' => 'Baja',
        'rebotado' => 'Rebotado',
    ];

    /** Segmentos de captación (origen de filtros Overpass u otros). */
    public const SEGMENTOS = [
        'agencia' => 'Agencia',
        'negocio' => 'Negocio',
    ];

    /**
     * Atributos asignables en masa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'place_id',
        'name',
        'website',
        'email',
        'email_check',
        'phone',
        'address',
        'status',
        'segmento',
        'captured_at',
        'contacted_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'contacted_at' => 'datetime',
        ];
    }

    /**
     * Leads con email persistido (excluye ruido sin correo).
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeWithEmail(Builder $query): Builder
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }

    /**
     * Leads listos para el primer envío de correo.
     *
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->where('status', 'nuevo')->whereNotNull('email');
    }

    /**
     * Clases Tailwind para mostrar el estado en la interfaz.
     */
    public function statusColor(): string
    {
        return match ($this->status) {
            'nuevo' => 'bg-blue-100 text-blue-800',
            'sin_email' => 'bg-gray-100 text-gray-800',
            'contactado' => 'bg-amber-100 text-amber-800',
            'respondido' => 'bg-green-100 text-green-800',
            'cliente' => 'bg-emerald-100 text-emerald-800',
            'descartado' => 'bg-red-100 text-red-800',
            'baja' => 'bg-rose-100 text-rose-800',
            'rebotado' => 'bg-orange-100 text-orange-800',
            default => 'bg-slate-100 text-slate-800',
        };
    }
}
