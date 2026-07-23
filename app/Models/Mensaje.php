<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    use HasFactory;

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'enviando' => 'Enviando',
        'enviado' => 'Enviado',
        'fallido' => 'Fallido',
        'cancelado' => 'Cancelado',
    ];

    protected $fillable = [
        'lead_id', 'lead_email_id', 'destinatario', 'plantilla', 'paso', 'asunto',
        'cuerpo_texto', 'cuerpo_html', 'programado_para', 'estado', 'intentos',
        'ultimo_error', 'message_id', 'bloqueado_at', 'enviado_at',
    ];

    protected function casts(): array
    {
        return [
            'programado_para' => 'datetime',
            'bloqueado_at' => 'datetime',
            'enviado_at' => 'datetime',
        ];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function leadEmail()
    {
        return $this->belongsTo(LeadEmail::class);
    }

    /**
     * Bloqueo atómico. Devuelve true solo si ESTE proceso ha conseguido pasar
     * el mensaje de 'pendiente' a 'enviando'. Si otro proceso llegó antes,
     * devuelve false y este proceso NO debe enviar nada.
     */
    public function marcarEnviando(): bool
    {
        $afectadas = static::query()
            ->where('id', $this->id)
            ->where('estado', 'pendiente')
            ->update([
                'estado' => 'enviando',
                'bloqueado_at' => now(),
                'intentos' => $this->intentos + 1,
                'updated_at' => now(),
            ]);

        if ($afectadas === 1) {
            $this->refresh();

            return true;
        }

        return false;
    }

    public function marcarEnviado(?string $messageId): void
    {
        $this->update([
            'estado' => 'enviado',
            'message_id' => $messageId,
            'enviado_at' => now(),
            'bloqueado_at' => null,
            'ultimo_error' => null,
        ]);
    }

    public function marcarFallido(string $error): void
    {
        $this->update([
            'estado' => 'fallido',
            'ultimo_error' => mb_substr($error, 0, 2000),
            'bloqueado_at' => null,
        ]);
    }

    public function cancelar(string $motivo): void
    {
        $this->update([
            'estado' => 'cancelado',
            'ultimo_error' => mb_substr($motivo, 0, 2000),
            'bloqueado_at' => null,
        ]);
    }

    /** @param  Builder<Mensaje>  $query */
    public function scopePendientesVencidos(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente')
            ->where('programado_para', '<=', now());
    }

    /** @param  Builder<Mensaje>  $query */
    public function scopeColgados(Builder $query, int $minutos = 15): Builder
    {
        return $query->where('estado', 'enviando')
            ->where('bloqueado_at', '<', now()->subMinutes($minutos));
    }

    /** @param  Builder<Mensaje>  $query */
    public function scopeUltimosEnviados(Builder $query, int $cantidad): Builder
    {
        return $query->where('estado', 'enviado')
            ->orderByDesc('enviado_at')
            ->limit($cantidad);
    }
}
