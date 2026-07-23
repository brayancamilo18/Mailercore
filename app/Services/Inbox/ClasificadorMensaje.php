<?php

namespace App\Services\Inbox;

use App\DTO\MensajeEntrante;
use App\DTO\ResultadoClasificacionInbox;
use App\Models\LeadEmail;
use App\Models\Mensaje;
use App\Models\Suppression;

class ClasificadorMensaje
{
    public function __construct(private RecortadorCitas $recortador) {}

    public function clasificar(MensajeEntrante $m): ResultadoClasificacionInbox
    {
        if ($this->esRebote($m)) {
            return $this->clasificarRebote($m);
        }

        if ($this->esAutorespuesta($m)) {
            return $this->resultado('ignorado', $m, null, null);
        }

        if ($this->esQueja($m)) {
            return $this->resultado('queja', $m, Suppression::normalizarEmail($m->desdeEmail), null);
        }

        $recorte = $this->recortador->recortar($m->cuerpo);
        $textoNuevo = $recorte['texto'];

        if ($this->pideBaja($m->asunto, $textoNuevo, $recorte['solo_citado'])) {
            return $this->resultado(
                'baja',
                $m,
                Suppression::normalizarEmail($m->desdeEmail),
                null,
                $textoNuevo
            );
        }

        if (! $recorte['solo_citado'] && mb_strlen(trim($textoNuevo)) >= 5) {
            return $this->resultado(
                'respuesta',
                $m,
                Suppression::normalizarEmail($m->desdeEmail),
                null,
                $textoNuevo
            );
        }

        return $this->resultado('ignorado', $m, null, null, $textoNuevo);
    }

    private function esRebote(MensajeEntrante $m): bool
    {
        $from = mb_strtolower($m->desdeEmail.' '.$m->desdeNombre);
        foreach (['mailer-daemon', 'postmaster', 'mail delivery', 'mail-daemon'] as $token) {
            if (str_contains($from, $token)) {
                return true;
            }
        }

        $failed = $this->cabecera($m, 'x-failed-recipients');
        if ($failed !== null && trim($failed) !== '') {
            return true;
        }

        $contentType = mb_strtolower($this->cabecera($m, 'content-type') ?? '');
        if (str_contains($contentType, 'multipart/report')
            || str_contains($contentType, 'report-type=delivery-status')) {
            return true;
        }

        $asunto = mb_strtolower($m->asunto);
        foreach ([
            'delivery status notification',
            'undelivered mail',
            'mail delivery failed',
            'returned mail',
            'delivery failure',
            'failure notice',
            'no se ha entregado',
            'mensaje no entregado',
        ] as $token) {
            if (str_contains($asunto, $token)) {
                return true;
            }
        }

        $cuerpo = mb_strtolower($m->cuerpo);
        if (str_contains($cuerpo, 'final-recipient:') || str_contains($cuerpo, 'original-recipient:')) {
            return true;
        }

        return false;
    }

    private function clasificarRebote(MensajeEntrante $m): ResultadoClasificacionInbox
    {
        $email = $this->extraerDestinatarioRebote($m);
        $codigo = $this->extraerCodigoSmtp($m->cuerpo.' '.(implode("\n", $m->cabeceras)));

        $tipo = 'rebote_blando';
        if ($codigo !== null && str_starts_with($codigo, '5')) {
            $tipo = 'rebote_duro';
        }

        return $this->resultado($tipo, $m, $email, $codigo, $m->cuerpo);
    }

    private function extraerDestinatarioRebote(MensajeEntrante $m): ?string
    {
        $failed = $this->cabecera($m, 'x-failed-recipients');
        if ($failed !== null && trim($failed) !== '') {
            $candidato = trim(explode(',', $failed)[0]);
            $candidato = trim($candidato, "<> \t");

            return Suppression::normalizarEmail($candidato);
        }

        if (preg_match(
            '/(?:Final-Recipient|Original-Recipient):\s*(?:rfc822;)?\s*([^\s>;]+)/i',
            $m->cuerpo,
            $coincidencias
        )) {
            return Suppression::normalizarEmail(trim($coincidencias[1], "<> \t"));
        }

        if (preg_match(
            '/(?:The following address|Recipient address rejected|failed permanently to|could not be delivered to)[^\n<]*[<\s]([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i',
            $m->cuerpo,
            $coincidencias
        )) {
            return Suppression::normalizarEmail($coincidencias[1]);
        }

        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $m->cuerpo, $todos)) {
            foreach ($todos[0] as $candidato) {
                $normalizado = Suppression::normalizarEmail($candidato);
                if (LeadEmail::query()->where('email', $normalizado)->exists()) {
                    return $normalizado;
                }
            }
        }

        return null;
    }

    private function extraerCodigoSmtp(string $texto): ?string
    {
        if (preg_match('/^Status:\s*([45]\.\d{1,3}\.\d{1,3})/im', $texto, $m)) {
            return $m[1];
        }

        if (preg_match('/\b([45]\.\d{1,3}\.\d{1,3})\b/', $texto, $m)) {
            return $m[1];
        }

        if (preg_match('/Diagnostic-Code:.*?\b([45]\d{2})\b/is', $texto, $m)) {
            return $m[1];
        }

        return null;
    }

    private function esAutorespuesta(MensajeEntrante $m): bool
    {
        $auto = mb_strtolower($this->cabecera($m, 'auto-submitted') ?? '');
        if ($auto !== '' && (str_contains($auto, 'auto-replied') || str_contains($auto, 'auto-generated'))) {
            $contentType = mb_strtolower($this->cabecera($m, 'content-type') ?? '');
            $esDsn = str_contains($contentType, 'multipart/report')
                || str_contains($contentType, 'report-type=delivery-status');

            if (! $esDsn) {
                return true;
            }
        }

        $asunto = mb_strtolower($m->asunto);
        foreach ([
            'fuera de la oficina',
            'out of office',
            'automatic reply',
            'respuesta automática',
            'respuesta automatica',
            'vacaciones',
            'autoreply',
        ] as $token) {
            if (str_contains($asunto, $token)) {
                return true;
            }
        }

        return false;
    }

    private function esQueja(MensajeEntrante $m): bool
    {
        $from = mb_strtolower($m->desdeEmail);
        foreach (['abuse@', 'complaints@', 'spam@'] as $prefijo) {
            if (str_starts_with($from, $prefijo)) {
                return true;
            }
        }

        $cuerpo = mb_strtolower($m->cuerpo);
        foreach (['feedback loop', 'abuse report', 'this is a spam complaint'] as $token) {
            if (str_contains($cuerpo, $token)) {
                return true;
            }
        }

        return false;
    }

    private function pideBaja(string $asunto, string $textoNuevo, bool $soloCitado): bool
    {
        if (preg_match('/\bbaja\b/iu', $asunto) || preg_match('/\bunsubscribe\b/i', $asunto)) {
            return true;
        }

        if ($soloCitado) {
            return false;
        }

        if (preg_match('/\bbaja\b/iu', $textoNuevo)) {
            return true;
        }

        $minusculas = mb_strtolower($textoNuevo);
        foreach ([
            'no me interesa',
            'dejad de',
            'dejen de',
            'no quiero recibir',
            'borrarme',
            'borradme',
            'darme de baja',
            'eliminar mis datos',
            'quitadme de',
            'no volváis a',
            'no volvais a',
            'stop',
        ] as $token) {
            if (str_contains($minusculas, $token)) {
                return true;
            }
        }

        return false;
    }

    private function correlacionar(MensajeEntrante $m, ?string $emailAfectado): ?int
    {
        foreach ([$m->inReplyTo, $m->references] as $campo) {
            if ($campo === null || trim($campo) === '') {
                continue;
            }

            if (preg_match_all('/<[^>]+>/', $campo, $ids) === 0) {
                $ids = [[trim($campo)]];
            }

            foreach ($ids[0] as $id) {
                $id = trim($id);
                $sinAngles = trim($id, '<>');

                $mensaje = Mensaje::query()
                    ->where(function ($q) use ($id, $sinAngles): void {
                        $q->where('message_id', $id)
                            ->orWhere('message_id', $sinAngles)
                            ->orWhere('message_id', '<'.$sinAngles.'>');
                    })
                    ->first();

                if ($mensaje !== null) {
                    return $mensaje->id;
                }
            }
        }

        $email = Suppression::normalizarEmail($emailAfectado ?? $m->desdeEmail);
        if ($email === '') {
            return null;
        }

        $mensaje = Mensaje::query()
            ->where('estado', 'enviado')
            ->where('destinatario', $email)
            ->orderByDesc('enviado_at')
            ->first();

        return $mensaje?->id;
    }

    private function extracto(string $texto): string
    {
        $plano = preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $texto)) ?? $texto;

        return mb_substr(trim($plano), 0, 300);
    }

    private function resultado(
        string $tipo,
        MensajeEntrante $m,
        ?string $emailAfectado,
        ?string $codigoSmtp,
        ?string $textoParaExtracto = null,
    ): ResultadoClasificacionInbox {
        $texto = $textoParaExtracto ?? $m->cuerpo;

        return new ResultadoClasificacionInbox(
            tipo: $tipo,
            emailAfectado: $emailAfectado,
            codigoSmtp: $codigoSmtp,
            mensajeId: $this->correlacionar($m, $emailAfectado),
            extracto: $this->extracto($texto),
        );
    }

    private function cabecera(MensajeEntrante $m, string $nombre): ?string
    {
        foreach ($m->cabeceras as $clave => $valor) {
            if (strcasecmp((string) $clave, $nombre) === 0) {
                return (string) $valor;
            }
        }

        return null;
    }
}
