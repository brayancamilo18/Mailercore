<?php

namespace App\Models;

use App\Enums\PropositoRastreo;
use Database\Factories\PaginaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Pagina extends Model
{
    /** @use HasFactory<PaginaFactory> */
    use HasFactory;

    /** Valor de origen_ruta de las rutas halladas entre los enlaces de la home. */
    public const ORIGEN_DESCUBIERTA = 'descubierta';

    /**
     * Señales que viven en una página concreta del sitio, no en la home, y que
     * por tanto no se pueden dar por ausentes sin haber visitado esa página.
     *
     * @var array<string, string> campo de paginas => clave en enlaces_senal
     */
    public const SENALES_SECTORIALES = [
        'tiene_reservas' => 'reservas',
        'tiene_carrito' => 'carrito',
        'tiene_formulario' => 'formulario',
        'tiene_whatsapp' => 'whatsapp',
    ];

    public const MOTIVO_SIN_COBERTURA = 'sin cobertura sana';

    public const MOTIVO_SIN_RUTA_SECTORIAL = 'sin ruta sectorial visitada';

    public const MOTIVO_HOME_ENLAZA = 'la home enlaza a esa señal';

    protected $fillable = [
        'lead_id', 'url', 'ruta', 'http_status', 'content_type', 'bytes',
        'respuesta_ms', 'redirigida_a', 'title', 'title_longitud',
        'meta_description', 'meta_desc_longitud', 'h1_texto', 'h1_total',
        'h2_total', 'idioma', 'canonical', 'generador', 'charset',
        'tiene_viewport', 'tiene_favicon', 'tiene_og', 'tiene_jsonld',
        'jsonld_tipos', 'imagenes_total', 'imagenes_sin_alt',
        'enlaces_internos', 'enlaces_externos', 'enlaces_senal', 'redes_sociales', 'telefonos',
        'emails_encontrados', 'tiene_formulario', 'tiene_whatsapp',
        'tiene_reservas', 'tiene_carrito', 'tiene_aviso_legal',
        'tiene_privacidad', 'tiene_cookies', 'anio_copyright', 'es_https',
        'cert_valido', 'cert_cadena_valida', 'cert_expira_at', 'html_hash', 'error', 'capturada_at',
        'proposito', 'origen_ruta', 'contenido_truncado', 'intentos', 'fallo_tipo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jsonld_tipos' => 'array',
            'enlaces_senal' => 'array',
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
            'cert_cadena_valida' => 'boolean',
            'cert_expira_at' => 'datetime',
            'capturada_at' => 'datetime',
            'contenido_truncado' => 'boolean',
            'intentos' => 'integer',
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

    /**
     * ¿Esta página sirve para afirmar que algo no está? Solo si se rastreó con
     * propósito de auditoría y se leyó entera: un 404 o un timeout no demuestran
     * nada, aunque sumen al contador de páginas visitadas.
     */
    public function aportaCobertura(): bool
    {
        return (int) $this->http_status === 200
            && $this->fallo_tipo === null
            && in_array($this->proposito, self::propositosConCobertura(), true);
    }

    /**
     * Lectura de una señal booleana a nivel de lead, distinguiendo «no lo tiene»
     * de «no lo hemos mirado».
     *
     * - true: alguna página la detectó.
     * - false: se leyeron páginas sanas donde esa señal viviría y ninguna la
     *   detectó.
     * - null: nadie la detectó y no se miró donde había que mirar.
     *
     * Las señales sectoriales (reservas, carrito, formulario, WhatsApp) exigen
     * además haber leído una ruta del sector del lead o una ruta descubierta
     * entre los enlaces de la home. Que la home no enlace a la señal no basta:
     * el rastreo estático no ve los enlaces que pinta JavaScript.
     *
     * @param  Collection<int, Pagina>  $paginas
     * @param  string|null  $sector  sector del lead, para saber qué rutas cuentan
     */
    public static function señal(Collection $paginas, string $campo, ?string $sector = null): ?bool
    {
        return self::evaluarSenal($paginas, $campo, $sector)[0];
    }

    /**
     * Por qué una señal quedó en «no observado». null cuando sí se pudo decidir.
     *
     * @param  Collection<int, Pagina>  $paginas
     */
    public static function motivoSenal(Collection $paginas, string $campo, ?string $sector = null): ?string
    {
        return self::evaluarSenal($paginas, $campo, $sector)[1];
    }

    /**
     * @param  Collection<int, Pagina>  $paginas
     * @return array{0: bool|null, 1: string|null} valor y motivo del «no observado»
     */
    private static function evaluarSenal(Collection $paginas, string $campo, ?string $sector): array
    {
        /** @var list<Pagina> */
        $conCobertura = [];

        foreach ($paginas as $pagina) {
            if ($pagina->getAttribute($campo) === true) {
                return [true, null];
            }

            if ($pagina->aportaCobertura()) {
                $conCobertura[] = $pagina;
            }
        }

        if ($conCobertura === []) {
            return [null, self::MOTIVO_SIN_COBERTURA];
        }

        $senal = self::SENALES_SECTORIALES[$campo] ?? null;

        if ($senal === null) {
            return [false, null];
        }

        // La home prometía esa señal en algún enlace: aunque no la hayamos visto,
        // afirmar que no existe sería contradecir lo que la propia web anuncia.
        if (self::homeEnlazaSenal($conCobertura, $senal)) {
            return [null, self::MOTIVO_HOME_ENLAZA];
        }

        if (! self::seMiroDondeVive($conCobertura, $sector)) {
            return [null, self::MOTIVO_SIN_RUTA_SECTORIAL];
        }

        return [false, null];
    }

    /**
     * ¿Se leyó alguna página de las que suelen alojar la señal: una ruta del
     * sector del lead o una descubierta entre los enlaces de la home?
     *
     * @param  list<Pagina>  $conCobertura
     */
    private static function seMiroDondeVive(array $conCobertura, ?string $sector): bool
    {
        $delSector = $sector !== null
            ? config('outreach.scraper.rutas.auditoria_sector.'.$sector, [])
            : [];
        $delSector = is_array($delSector) ? $delSector : [];

        $rutasSector = [];
        foreach ($delSector as $ruta) {
            if (is_string($ruta)) {
                $rutasSector[] = self::rutaComparable($ruta);
            }
        }

        foreach ($conCobertura as $pagina) {
            if ($pagina->origen_ruta === self::ORIGEN_DESCUBIERTA) {
                return true;
            }

            if (in_array(self::rutaComparable((string) $pagina->ruta), $rutasSector, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Pagina>  $conCobertura
     */
    private static function homeEnlazaSenal(array $conCobertura, string $senal): bool
    {
        foreach ($conCobertura as $pagina) {
            if (! $pagina->esHome()) {
                continue;
            }

            $enlaces = $pagina->enlaces_senal;

            if (is_array($enlaces) && ($enlaces[$senal] ?? null) === true) {
                return true;
            }
        }

        return false;
    }

    private static function rutaComparable(string $ruta): string
    {
        $ruta = mb_strtolower(rtrim(trim($ruta), '/'));

        return $ruta === '' ? '/' : $ruta;
    }

    /**
     * Propósitos cuyo rastreo recorre el sitio entero y, por tanto, permiten
     * afirmar una ausencia.
     *
     * @return list<string>
     */
    private static function propositosConCobertura(): array
    {
        $valores = [];

        foreach (PropositoRastreo::cases() as $proposito) {
            if ($proposito->cubreAuditoria()) {
                $valores[] = $proposito->value;
            }
        }

        return $valores;
    }
}
