<?php

namespace App\Models;

use Database\Factories\EventoInboxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoInbox extends Model
{
    /** @use HasFactory<EventoInboxFactory> */
    use HasFactory;

    protected $table = 'eventos_inbox';

    public const TIPOS = [
        'rebote_duro',
        'rebote_blando',
        'baja',
        'respuesta',
        'queja',
        'ignorado',
    ];

    protected $fillable = [
        'mensaje_id', 'email', 'tipo', 'codigo_smtp', 'asunto',
        'extracto', 'raw_hash', 'recibido_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recibido_at' => 'datetime',
        ];
    }

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(Mensaje::class);
    }
}
