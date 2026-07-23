<?php

namespace App\Services\Inbox;

use App\DTO\MensajeEntrante;
use App\DTO\ResultadoClasificacionInbox;
use App\Models\DiaEnvio;
use App\Models\EventoInbox;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Soporte\Latido;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcesadorBandeja
{
    public function __construct(
        private LectorBandeja $lector,
        private ClasificadorMensaje $clasificador,
    ) {}

    /**
     * @return array{ok: bool, resumen: array<string, int>, motivo?: string}
     */
    public function procesar(int $limite = 100, bool $dryRun = false): array
    {
        Latido::marcar('bandeja');

        try {
            $items = $this->lector->leerNoLeidos($limite);
        } catch (\Throwable $e) {
            Log::channel('outreach')->error('Fallo al conectar con la bandeja IMAP', [
                'error' => $e->getMessage(),
            ]);

            $fallos = (int) Cache::increment('bandeja:fallos_seguidos');
            if ($fallos >= 6) {
                Log::channel('outreach')->critical('La bandeja IMAP lleva una hora sin conectar');
            }

            return [
                'ok' => false,
                'resumen' => [],
                'motivo' => $e->getMessage(),
            ];
        }

        Cache::forget('bandeja:fallos_seguidos');

        $resumen = [
            'rebote_duro' => 0,
            'rebote_blando' => 0,
            'baja' => 0,
            'respuesta' => 0,
            'queja' => 0,
            'ignorado' => 0,
            'omitidos' => 0,
        ];

        foreach ($items as $item) {
            if (EventoInbox::query()->where('raw_hash', $item->entrante->rawHash)->exists()) {
                $resumen['omitidos']++;

                continue;
            }

            $resultado = $this->clasificador->clasificar($item->entrante);

            if (! $dryRun) {
                $tipoFinal = $this->aplicarEfectos($item->entrante, $resultado);
                $this->crearEvento($item->entrante, $resultado, $tipoFinal);
                $this->lector->marcarVisto($item->id);
                $resumen[$tipoFinal] = ($resumen[$tipoFinal] ?? 0) + 1;
            } else {
                $resumen[$resultado->tipo] = ($resumen[$resultado->tipo] ?? 0) + 1;
            }
        }

        return ['ok' => true, 'resumen' => $resumen];
    }

    private function aplicarEfectos(MensajeEntrante $entrante, ResultadoClasificacionInbox $resultado): string
    {
        $tipo = $resultado->tipo;

        if ($tipo === 'rebote_blando') {
            $email = $resultado->emailAfectado;
            $previos = $email
                ? EventoInbox::query()->where('tipo', 'rebote_blando')->where('email', $email)->count()
                : 0;

            if ($previos >= 2) {
                $tipo = 'rebote_duro';
            }
        }

        return match ($tipo) {
            'rebote_duro' => $this->aplicarReboteDuro($resultado),
            'rebote_blando' => $this->aplicarReboteBlando($resultado),
            'baja' => $this->aplicarBaja($entrante, $resultado),
            'queja' => $this->aplicarQueja($entrante, $resultado),
            'respuesta' => $this->aplicarRespuesta($entrante, $resultado),
            default => 'ignorado',
        };
    }

    private function aplicarReboteDuro(ResultadoClasificacionInbox $resultado): string
    {
        $email = $resultado->emailAfectado;
        if ($email) {
            Suppression::registrar($email, 'rebote_duro', $resultado->codigoSmtp);
            $this->leadPorEmail($email)?->update(['estado' => 'rebotado']);
        }

        $mensaje = $resultado->mensajeId ? Mensaje::query()->find($resultado->mensajeId) : null;
        if ($mensaje?->programado_para) {
            DiaEnvio::paraFecha($mensaje->programado_para)->incrementarContador('rebotes_duros');
        }

        return 'rebote_duro';
    }

    private function aplicarReboteBlando(ResultadoClasificacionInbox $resultado): string
    {
        $mensaje = $resultado->mensajeId ? Mensaje::query()->find($resultado->mensajeId) : null;
        $fechaContador = $mensaje?->programado_para ?? today();

        if ($mensaje !== null && $mensaje->estado === 'enviado') {
            $mensaje->update([
                'estado' => 'pendiente',
                'programado_para' => now()->addHours(48),
                'enviado_at' => null,
                'message_id' => null,
                'bloqueado_at' => null,
            ]);
        }

        DiaEnvio::paraFecha($fechaContador)->incrementarContador('rebotes_blandos');

        return 'rebote_blando';
    }

    private function aplicarBaja(MensajeEntrante $entrante, ResultadoClasificacionInbox $resultado): string
    {
        $email = Suppression::normalizarEmail($resultado->emailAfectado ?? $entrante->desdeEmail);
        Suppression::registrar($email, 'baja');

        $lead = $this->leadPorEmail($email);
        if ($lead !== null) {
            $lead->update(['estado' => 'baja']);
            Mensaje::query()
                ->where('lead_id', $lead->id)
                ->where('estado', 'pendiente')
                ->get()
                ->each(fn (Mensaje $m) => $m->cancelar('Baja solicitada'));
        }

        DiaEnvio::paraFecha(today())->incrementarContador('bajas');

        return 'baja';
    }

    private function aplicarQueja(MensajeEntrante $entrante, ResultadoClasificacionInbox $resultado): string
    {
        $email = Suppression::normalizarEmail($resultado->emailAfectado ?? $entrante->desdeEmail);
        Suppression::registrar($email, 'queja');
        $this->leadPorEmail($email)?->update(['estado' => 'baja']);

        Log::channel('outreach')->error('Queja de spam recibida', [
            'email' => $email,
            'asunto' => $entrante->asunto,
        ]);

        DiaEnvio::paraFecha(today())->update(['salud' => 'rojo']);

        return 'queja';
    }

    private function aplicarRespuesta(MensajeEntrante $entrante, ResultadoClasificacionInbox $resultado): string
    {
        $email = Suppression::normalizarEmail($resultado->emailAfectado ?? $entrante->desdeEmail);
        $lead = $this->leadPorEmail($email);

        if ($lead !== null && in_array($lead->estado, ['contactado', 'seguimiento'], true)) {
            $lead->update(['estado' => 'respondido']);
            Mensaje::query()
                ->where('lead_id', $lead->id)
                ->where('estado', 'pendiente')
                ->where('paso', 2)
                ->get()
                ->each(fn (Mensaje $m) => $m->cancelar('Lead respondió'));
        }

        DiaEnvio::paraFecha(today())->incrementarContador('respuestas');

        return 'respuesta';
    }

    private function crearEvento(
        MensajeEntrante $entrante,
        ResultadoClasificacionInbox $resultado,
        string $tipoFinal,
    ): void {
        EventoInbox::query()->create([
            'mensaje_id' => $resultado->mensajeId,
            'email' => $resultado->emailAfectado ?? Suppression::normalizarEmail($entrante->desdeEmail),
            'tipo' => $tipoFinal,
            'codigo_smtp' => $resultado->codigoSmtp,
            'asunto' => $entrante->asunto,
            'extracto' => $resultado->extracto,
            'raw_hash' => $entrante->rawHash,
            'recibido_at' => $entrante->recibidoAt,
        ]);
    }

    private function leadPorEmail(string $email): ?Lead
    {
        $email = Suppression::normalizarEmail($email);

        return LeadEmail::query()->where('email', $email)->first()?->lead;
    }
}
