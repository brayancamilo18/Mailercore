<?php

namespace App\Models;

use Database\Factories\PaginaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pagina extends Model
{
    /** @use HasFactory<PaginaFactory> */
    use HasFactory;

    protected $fillable = [
        'lead_id', 'url', 'ruta', 'http_status', 'content_type', 'bytes',
        'respuesta_ms', 'redirigida_a', 'title', 'title_longitud',
        'meta_description', 'meta_desc_longitud', 'h1_texto', 'h1_total',
        'h2_total', 'idioma', 'canonical', 'generador', 'charset',
        'tiene_viewport', 'tiene_favicon', 'tiene_og', 'tiene_jsonld',
        'jsonld_tipos', 'imagenes_total', 'imagenes_sin_alt',
        'enlaces_internos', 'enlaces_externos', 'redes_sociales', 'telefonos',
        'emails_encontrados', 'tiene_formulario', 'tiene_whatsapp',
        'tiene_reservas', 'tiene_carrito', 'tiene_aviso_legal',
        'tiene_privacidad', 'tiene_cookies', 'anio_copyright', 'es_https',
        'cert_valido', 'cert_expira_at', 'html_hash', 'error', 'capturada_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jsonld_tipos' => 'array',
            'redes_sociales' => 'array',
            'telefonos' => 'array',
            'emails_encontrados' => 'array',
            'tiene_viewport' => 'boolean',
            'tiene_favicon' => 'boolean',
            'tiene_og' => 'boolean',
            'tiene_jsonld' => 'boolean',
            'tiene_formulario' => 'boolean',
            'tiene_whatsapp' => 'boolean',
            'tiene_reservas' => 'boolean',
            'tiene_carrito' => 'boolean',
            'tiene_aviso_legal' => 'boolean',
            'tiene_privacidad' => 'boolean',
            'tiene_cookies' => 'boolean',
            'es_https' => 'boolean',
            'cert_valido' => 'boolean',
            'cert_expira_at' => 'datetime',
            'capturada_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeHome(Builder $query): Builder
    {
        return $query->whereIn('ruta', ['/', '']);
    }

    public function scopeExitosas(Builder $query): Builder
    {
        return $query->whereBetween('http_status', [200, 299]);
    }

    public function esHome(): bool
    {
        return in_array($this->ruta, ['/', ''], true);
    }
}
