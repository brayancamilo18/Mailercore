<?php

namespace App\Services\Panel;

use App\Models\AreaCosecha;
use App\Models\Auditoria;
use App\Models\DiaEnvio;
use App\Models\EventoInbox;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Envio\RampaEnvio;
use App\Services\Soporte\Latido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DatosPanel
{
    public function __construct(private RampaEnvio $rampa) {}

    public function envioPausado(): bool
    {
        return (bool) Cache::get('envio:pausado', false);
    }

    /**
     * @return list<array{clave: string, etiqueta: string, total: int, porcentaje: float|null}>
     */
    public function embudo(): array
    {
        $totales = Lead::query()->count();
        $conEmailRol = Lead::query()->whereHas('emails', fn ($q) => $q->where('tipo', 'rol'))->count();
        $clasificados = Lead::query()->whereNotNull('sector')->count();
        $rastreados = Lead::query()->whereNotNull('rastreado_at')->count();
        $auditados = Lead::query()->whereHas('auditoria')->count();
        $enCola = Lead::query()->where('estado', 'en_cola')->count();
        $contactados = Lead::query()->whereIn('estado', ['contactado', 'seguimiento', 'respondido', 'cliente'])->count();
        $respondidos = Lead::query()->whereIn('estado', ['respondido', 'cliente'])->count();
        $clientes = Lead::query()->where('estado', 'cliente')->count();

        $etapas = [
            ['clave' => 'totales', 'etiqueta' => 'Leads totales', 'total' => $totales],
            ['clave' => 'email_rol', 'etiqueta' => 'Con email de rol', 'total' => $conEmailRol],
            ['clave' => 'clasificados', 'etiqueta' => 'Clasificados', 'total' => $clasificados],
            ['clave' => 'rastreados', 'etiqueta' => 'Rastreados', 'total' => $rastreados],
            ['clave' => 'auditados', 'etiqueta' => 'Auditados', 'total' => $auditados],
            ['clave' => 'en_cola', 'etiqueta' => 'En cola', 'total' => $enCola],
            ['clave' => 'contactados', 'etiqueta' => 'Contactados', 'total' => $contactados],
            ['clave' => 'respondidos', 'etiqueta' => 'Respondidos', 'total' => $respondidos],
            ['clave' => 'clientes', 'etiqueta' => 'Clientes', 'total' => $clientes],
        ];

        $anterior = null;
        foreach ($etapas as &$etapa) {
            $etapa['porcentaje'] = ($anterior !== null && $anterior > 0)
                ? round(($etapa['total'] / $anterior) * 100, 1)
                : null;
            $anterior = $etapa['total'];
        }
        unset($etapa);

        return $etapas;
    }

    /**
     * @return array{
     *   dia: DiaEnvio,
     *   dias_racha: int,
     *   pausado: bool,
     *   progreso: float
     * }
     */
    public function rampaHoy(): array
    {
        $dia = DiaEnvio::paraFecha(today());
        $cuota = max(0, (int) $dia->cuota_planificada);
        $enviados = (int) $dia->enviados;

        return [
            'dia' => $dia,
            'dias_racha' => $this->rampa->diasRacha(today()),
            'pausado' => $this->envioPausado(),
            'progreso' => $cuota > 0 ? min(100, round(($enviados / $cuota) * 100, 1)) : 0.0,
        ];
    }

    /**
     * @return list<array{
     *   sector: string,
     *   etiqueta: string,
     *   leads: int,
     *   auditados: int,
     *   puntuacion_media: float|null,
     *   contactados: int,
     *   respondidos: int,
     *   tasa_respuesta: float|null
     * }>
     */
    public function tablaSectores(): array
    {
        $sectores = array_keys(config('sectores', []));
        $filas = [];

        foreach ($sectores as $sector) {
            $leads = Lead::query()->where('sector', $sector)->count();
            $auditados = Lead::query()->where('sector', $sector)->whereHas('auditoria')->count();
            $media = Auditoria::query()
                ->whereHas('lead', fn ($q) => $q->where('sector', $sector))
                ->avg('puntuacion');
            $contactados = Lead::query()
                ->where('sector', $sector)
                ->whereIn('estado', ['contactado', 'seguimiento', 'respondido', 'cliente'])
                ->count();
            $respondidos = Lead::query()
                ->where('sector', $sector)
                ->whereIn('estado', ['respondido', 'cliente'])
                ->count();

            $filas[] = [
                'sector' => $sector,
                'etiqueta' => (string) (config("sectores.{$sector}.etiqueta") ?? $sector),
                'leads' => $leads,
                'auditados' => $auditados,
                'puntuacion_media' => $media !== null ? round((float) $media, 1) : null,
                'contactados' => $contactados,
                'respondidos' => $respondidos,
                'tasa_respuesta' => $contactados > 0
                    ? round(($respondidos / $contactados) * 100, 1)
                    : null,
            ];
        }

        usort($filas, fn (array $a, array $b): int => ($b['tasa_respuesta'] ?? -1) <=> ($a['tasa_respuesta'] ?? -1));

        return $filas;
    }

    /**
     * @return Collection<int, array{evento: EventoInbox, lead: ?Lead, dominio: ?string}>
     */
    public function ultimasRespuestas(int $limite = 10): Collection
    {
        return EventoInbox::query()
            ->where('tipo', 'respuesta')
            ->orderByDesc('recibido_at')
            ->limit($limite)
            ->with('mensaje.lead')
            ->get()
            ->map(function (EventoInbox $evento): array {
                $lead = $evento->mensaje?->lead;
                if ($lead === null && $evento->email) {
                    $lead = LeadEmail::query()->where('email', $evento->email)->first()?->lead;
                }

                return [
                    'evento' => $evento,
                    'lead' => $lead,
                    'dominio' => Suppression::dominioDeEmail($evento->email)
                        ?? $lead?->website_dominio,
                ];
            });
    }

    /**
     * @return array<string, array{edad: ?int, vivo: bool, ttl: int}>
     */
    public function latidos(): array
    {
        $resultado = [];
        foreach (Latido::todos() as $proceso => $info) {
            $resultado[$proceso] = [
                'edad' => $info['edad'],
                'vivo' => $info['vivo'],
                'ttl' => $info['umbral'],
            ];
        }

        return $resultado;
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoJson(): array
    {
        $rampa = $this->rampaHoy();
        $dia = $rampa['dia'];
        $embudo = [];
        foreach ($this->embudo() as $etapa) {
            $embudo[$etapa['clave']] = $etapa['total'];
        }

        $latidos = [];
        foreach ($this->latidos() as $proceso => $info) {
            $latidos[$proceso] = $info['edad'];
        }

        return [
            'enviados_hoy' => (int) $dia->enviados,
            'cuota' => (int) $dia->cuota_planificada,
            'salud' => $dia->salud,
            'tasa_rebote' => (float) $dia->tasa_rebote,
            'pausado' => $rampa['pausado'],
            'dias_racha' => $rampa['dias_racha'],
            'latidos' => $latidos,
            'mensajes_pendientes' => Mensaje::query()->where('estado', 'pendiente')->count(),
            'embudo' => $embudo,
        ];
    }

    /**
     * @return array{areas: Collection, avance: float, total: int, hechas: int}
     */
    public function cosecha(): array
    {
        $areas = AreaCosecha::query()->ordenadas()->get();
        $total = $areas->count();
        $hechas = $areas->where('estado', 'hecho')->count();

        return [
            'areas' => $areas,
            'total' => $total,
            'hechas' => $hechas,
            'avance' => $total > 0 ? round(($hechas / $total) * 100, 1) : 0.0,
        ];
    }

    public function listUnsubscribeHeader(): string
    {
        $emailBaja = config('outreach.envio.remitente.email_baja');
        $urlBaja = config('outreach.envio.remitente.url_baja');
        $valor = '<mailto:'.$emailBaja.'?subject=BAJA>';

        if (! empty($urlBaja)) {
            $valor .= ', <'.$urlBaja.'>';
        }

        return $valor;
    }
}
