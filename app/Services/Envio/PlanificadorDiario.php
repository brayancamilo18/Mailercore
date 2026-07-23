<?php

namespace App\Services\Envio;

use App\Excepciones\PlantillaInvalida;
use App\Models\DiaEnvio;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Mensaje;
use App\Models\Suppression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlanificadorDiario
{
    public function __construct(
        private RampaEnvio $rampa,
        private Renderizador $renderizador,
    ) {}

    /**
     * @return array{
     *     cuota: int,
     *     primer_contacto: int,
     *     seguimientos: int,
     *     omitidos: int,
     *     motivo: string,
     *     plan?: list<array{hora: string, sector: string|null, dominio: string, asunto: string, hallazgo: string|null, paso: int}>
     * }
     */
    public function planificar(Carbon $fecha, bool $dryRun = false): array
    {
        if (! config('outreach.envio.activo')) {
            return $this->vacio(0, 'El envío está desactivado (OUTREACH_ENVIO_ACTIVO=false)');
        }

        if (! in_array($fecha->dayOfWeekIso, config('outreach.envio.dias'), true)) {
            return $this->vacio(0, 'No es día de envío');
        }

        $rampa = $this->rampa->calcular($fecha);

        if ($rampa['cuota'] === 0) {
            return $this->vacio(0, $rampa['motivo']);
        }

        $cuota = (int) $rampa['cuota'];
        $escalon = (int) $rampa['escalon'];
        $paraSeguimiento = (int) floor($cuota * (int) config('outreach.envio.porcentaje_seguimientos') / 100);

        $seguimientos = $this->seleccionarSeguimientos($paraSeguimiento);
        $cupoPrimeros = max(0, $cuota - $seguimientos->count());
        $primeros = $this->seleccionarPrimerContacto($cupoPrimeros, $escalon);

        /** @var Collection<int, array{lead: Lead, email: LeadEmail, paso: int}> $seleccion */
        $seleccion = $seguimientos->concat($primeros)->values();
        $seleccion = $this->aplicarLimiteDominio($seleccion, $fecha);

        $horarios = $this->repartirHorarios($fecha, $seleccion->count());

        if (count($horarios) < $seleccion->count()) {
            Log::channel('outreach')->warning('No caben todos los correos respetando el intervalo mínimo; se recorta la lista.', [
                'solicitados' => $seleccion->count(),
                'cabidos' => count($horarios),
            ]);
            $seleccion = $seleccion->take(count($horarios))->values();
        }

        $omitidos = 0;
        $creadosPrimer = 0;
        $creadosSeguimiento = 0;
        $plan = [];

        foreach ($seleccion as $indice => $item) {
            $lead = $item['lead'];
            $email = $item['email'];
            $paso = $item['paso'];

            try {
                $render = $this->renderizador->renderizar($lead, $paso);
            } catch (PlantillaInvalida $e) {
                $omitidos++;
                Log::channel('outreach')->info('Lead omitido por plantilla inválida', [
                    'lead_id' => $lead->id,
                    'paso' => $paso,
                    'motivo' => $e->getMessage(),
                ]);

                continue;
            }

            if ($render === null) {
                $omitidos++;
                Log::channel('outreach')->info('Lead omitido: sin renderizado', [
                    'lead_id' => $lead->id,
                    'paso' => $paso,
                ]);

                continue;
            }

            $programadoPara = $horarios[$indice] ?? $fecha->copy()->setTime(9, 15);

            $plan[] = [
                'hora' => $programadoPara->format('H:i'),
                'sector' => $lead->sector,
                'dominio' => Suppression::dominioDeEmail($email->email) ?? '',
                'asunto' => $render['asunto'],
                'hallazgo' => $paso === 2
                    ? $lead->auditoria?->hallazgo_secundario_codigo
                    : $lead->auditoria?->hallazgo_codigo,
                'paso' => $paso,
                'lead_id' => $lead->id,
                'lead_email_id' => $email->id,
            ];

            if ($dryRun) {
                if ($paso === 2) {
                    $creadosSeguimiento++;
                } else {
                    $creadosPrimer++;
                }

                continue;
            }

            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

            Mensaje::query()->create([
                'lead_id' => $lead->id,
                'lead_email_id' => $email->id,
                'destinatario' => $email->email,
                'plantilla' => $lead->plantilla(),
                'paso' => $paso,
                'asunto' => $render['asunto'],
                'cuerpo_texto' => $render['texto'],
                'cuerpo_html' => $render['html'],
                'programado_para' => $programadoPara,
                'estado' => 'pendiente',
                'intentos' => 0,
                'message_id' => Str::uuid()->toString().'@'.$host,
            ]);

            $lead->update(['estado' => 'en_cola']);

            if ($paso === 2) {
                $creadosSeguimiento++;
            } else {
                $creadosPrimer++;
            }
        }

        if (! $dryRun) {
            $dia = DiaEnvio::query()->whereDate('fecha', $fecha->toDateString())->first();
            if ($dia !== null) {
                $dia->update(['generados' => $creadosPrimer + $creadosSeguimiento]);
            }
        }

        $resultado = [
            'cuota' => $cuota,
            'primer_contacto' => $creadosPrimer,
            'seguimientos' => $creadosSeguimiento,
            'omitidos' => $omitidos,
            'motivo' => $rampa['motivo'],
        ];

        if ($dryRun) {
            $resultado['plan'] = $plan;
        }

        return $resultado;
    }

    /**
     * @return Collection<int, array{lead: Lead, email: LeadEmail, paso: int}>
     */
    private function seleccionarSeguimientos(int $limite): Collection
    {
        if ($limite <= 0) {
            return collect();
        }

        $ventana = config('outreach.envio.ventana_seguimiento_dias');
        $desde = now()->subDays((int) $ventana[1]);
        $hasta = now()->subDays((int) $ventana[0]);

        $mensajes = Mensaje::query()
            ->with(['lead.auditoria', 'lead.emailPrincipal'])
            ->where('paso', 1)
            ->where('estado', 'enviado')
            ->whereBetween('enviado_at', [$desde, $hasta])
            ->whereHas('lead', fn ($q) => $q->where('estado', 'contactado'))
            ->whereHas('lead.auditoria', fn ($q) => $q->whereNotNull('hallazgo_secundario_codigo'))
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')
                    ->from('mensajes as m2')
                    ->whereColumn('m2.lead_id', 'mensajes.lead_id')
                    ->whereColumn('m2.plantilla', 'mensajes.plantilla')
                    ->where('m2.paso', 2);
            })
            ->orderBy('enviado_at')
            ->limit($limite * 3)
            ->get();

        $seleccion = collect();

        foreach ($mensajes as $mensaje) {
            if ($seleccion->count() >= $limite) {
                break;
            }

            $lead = $mensaje->lead;
            $email = $lead?->emailPrincipal;

            if ($lead === null || $email === null) {
                continue;
            }

            if (Suppression::existe($email->email) || Suppression::existe($mensaje->destinatario)) {
                continue;
            }

            $seleccion->push([
                'lead' => $lead,
                'email' => $email,
                'paso' => 2,
            ]);
        }

        return $seleccion->values();
    }

    /**
     * @return Collection<int, array{lead: Lead, email: LeadEmail, paso: int}>
     */
    private function seleccionarPrimerContacto(int $limite, int $escalon): Collection
    {
        if ($limite <= 0) {
            return collect();
        }

        $diasValidez = (int) config('outreach.verificador.validez_verificacion_dias');

        $leads = Lead::query()
            ->candidatosEnvio()
            ->with(['auditoria', 'emailPrincipal'])
            ->whereHas('emails', function ($q) use ($diasValidez, $escalon): void {
                $q->where('es_principal', true)
                    ->where('estado_verificacion', 'valido')
                    ->where('verificado_at', '>=', now()->subDays($diasValidez));

                if ($escalon < 4) {
                    $q->where(function ($inner): void {
                        $inner->where('es_catch_all', false)
                            ->orWhereNull('es_catch_all');
                    });
                }
            })
            ->join('auditorias', 'auditorias.lead_id', '=', 'leads.id')
            ->select('leads.*')
            ->orderByDesc('auditorias.puntuacion')
            ->orderByDesc('leads.clasificacion_confianza')
            ->orderByRaw('case when auditorias.psi_rendimiento is null then 1 else 0 end')
            ->orderBy('auditorias.psi_rendimiento')
            ->limit($limite * 5)
            ->get();

        $seleccion = collect();

        foreach ($leads as $lead) {
            if ($seleccion->count() >= $limite) {
                break;
            }

            $plantilla = $lead->plantilla();
            if ($plantilla === null) {
                continue;
            }

            $yaContactado = Mensaje::query()
                ->where('lead_id', $lead->id)
                ->where('plantilla', $plantilla)
                ->where('paso', 1)
                ->exists();

            if ($yaContactado) {
                continue;
            }

            $email = $lead->emailPrincipal;
            if ($email === null) {
                continue;
            }

            if (Suppression::existe($email->email)) {
                continue;
            }

            $dominio = Suppression::dominioDeEmail($email->email);
            if (Suppression::dominioExcluido($dominio)) {
                continue;
            }

            $seleccion->push([
                'lead' => $lead,
                'email' => $email,
                'paso' => 1,
            ]);
        }

        return $seleccion->values();
    }

    /**
     * @param  Collection<int, array{lead: Lead, email: LeadEmail, paso: int}>  $seleccion
     * @return Collection<int, array{lead: Lead, email: LeadEmail, paso: int}>
     */
    private function aplicarLimiteDominio(Collection $seleccion, Carbon $fecha): Collection
    {
        $max = (int) config('outreach.envio.max_por_dominio_destino');
        $conteo = [];

        $yaProgramados = Mensaje::query()
            ->whereDate('programado_para', $fecha->toDateString())
            ->whereNotIn('estado', ['cancelado'])
            ->pluck('destinatario');

        foreach ($yaProgramados as $destinatario) {
            $dominio = Suppression::dominioDeEmail($destinatario);
            if ($dominio === null) {
                continue;
            }
            $conteo[$dominio] = ($conteo[$dominio] ?? 0) + 1;
        }

        return $seleccion->filter(function (array $item) use (&$conteo, $max): bool {
            $dominio = Suppression::dominioDeEmail($item['email']->email);
            if ($dominio === null) {
                return false;
            }

            if (($conteo[$dominio] ?? 0) >= $max) {
                return false;
            }

            $conteo[$dominio] = ($conteo[$dominio] ?? 0) + 1;

            return true;
        })->values();
    }

    /**
     * @return list<Carbon>
     */
    private function repartirHorarios(Carbon $fecha, int $cantidad): array
    {
        if ($cantidad <= 0) {
            return [];
        }

        $cfg = config('outreach.envio');
        [$hIni, $mIni] = array_map('intval', explode(':', (string) $cfg['hora_inicio']));
        [$hFin, $mFin] = array_map('intval', explode(':', (string) $cfg['hora_fin']));

        $inicio = $fecha->copy()->setTime($hIni, $mIni, 0);
        $fin = $fecha->copy()->setTime($hFin, $mFin, 0);
        $minutosTotales = (int) $inicio->diffInMinutes($fin);
        $minEntre = (int) $cfg['minutos_min_entre_correos'];

        if ($minutosTotales < 0) {
            return [];
        }

        $maxCabida = $minEntre > 0
            ? (int) floor($minutosTotales / $minEntre) + 1
            : $cantidad;

        $n = min($cantidad, max(1, $maxCabida));

        $offsets = [];
        for ($i = 0; $i < $n; $i++) {
            $offsets[] = $minutosTotales === 0 ? 0 : random_int(0, $minutosTotales);
        }

        sort($offsets);

        for ($i = 1; $i < count($offsets); $i++) {
            if ($minEntre > $offsets[$i] - $offsets[$i - 1]) {
                $offsets[$i] = $offsets[$i - 1] + $minEntre;
            }
        }

        while ($offsets !== [] && end($offsets) > $minutosTotales) {
            array_pop($offsets);
        }

        return array_map(
            fn (int $mins): Carbon => $inicio->copy()->addMinutes($mins),
            array_values($offsets)
        );
    }

    /**
     * @return array{cuota: int, primer_contacto: int, seguimientos: int, omitidos: int, motivo: string}
     */
    private function vacio(int $cuota, string $motivo): array
    {
        return [
            'cuota' => $cuota,
            'primer_contacto' => 0,
            'seguimientos' => 0,
            'omitidos' => 0,
            'motivo' => $motivo,
        ];
    }
}
