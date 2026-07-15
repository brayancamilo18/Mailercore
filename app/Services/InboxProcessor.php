<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Suppression;
use Illuminate\Support\Facades\Log;

/**
 * Clasifica correos entrantes (rebote / BAJA / respuesta) y actualiza CRM + suppressions.
 */
class InboxProcessor
{
    /**
     * Procesa un mensaje ya parseado.
     *
     * @return 'rebote'|'baja'|'respondido'|'ignorado'
     */
    public function process(InboxMessage $message): string
    {
        if ($this->esRebote($message)) {
            return $this->manejarRebote($message);
        }

        if ($this->contieneBaja($message)) {
            return $this->manejarBaja($message);
        }

        return $this->manejarRespuestaHumana($message);
    }

    /**
     * @return 'rebote'|'ignorado'
     */
    private function manejarRebote(InboxMessage $message): string
    {
        $email = $this->extraerEmailRebotado($message);

        if ($email === null) {
            Log::warning('InboxProcessor: rebote sin dirección extraíble', [
                'from' => $message->fromAddress,
                'subject' => $message->subject,
            ]);

            return 'ignorado';
        }

        $this->registrarSupresion($email, 'rebote');
        $this->actualizarLeads($email, 'rebotado');

        return 'rebote';
    }

    /**
     * @return 'baja'|'ignorado'
     */
    private function manejarBaja(InboxMessage $message): string
    {
        $email = Suppression::normalizeEmail($message->fromAddress);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'ignorado';
        }

        $this->registrarSupresion($email, 'baja');
        $this->actualizarLeads($email, 'baja');

        return 'baja';
    }

    /**
     * @return 'respondido'|'ignorado'
     */
    private function manejarRespuestaHumana(InboxMessage $message): string
    {
        $email = Suppression::normalizeEmail($message->fromAddress);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'ignorado';
        }

        $updated = Lead::query()
            ->where('email', $email)
            ->whereNotIn('status', ['baja', 'rebotado', 'cliente', 'descartado'])
            ->update(['status' => 'respondido']);

        return $updated > 0 ? 'respondido' : 'ignorado';
    }

    private function registrarSupresion(string $email, string $reason): void
    {
        $email = Suppression::normalizeEmail($email);
        $domain = Suppression::domainFromEmail($email);

        Suppression::query()->updateOrCreate(
            ['email' => $email],
            [
                'domain' => $domain,
                'reason' => $reason,
                'created_at' => now(),
            ]
        );
    }

    private function actualizarLeads(string $email, string $status): void
    {
        Lead::query()
            ->where('email', Suppression::normalizeEmail($email))
            ->update(['status' => $status]);
    }

    private function esRebote(InboxMessage $message): bool
    {
        $from = strtolower($message->fromAddress.' '.$message->fromName);

        if (
            str_contains($from, 'mailer-daemon')
            || str_contains($from, 'postmaster')
            || str_contains($from, 'mail delivery')
            || str_contains($from, 'mail-daemon')
        ) {
            return true;
        }

        $headers = $message->headers;

        if (! empty($headers['x-failed-recipients'])) {
            return true;
        }

        $contentType = strtolower($headers['content-type'] ?? '');

        if (
            str_contains($contentType, 'report-type=delivery-status')
            || str_contains($contentType, 'multipart/report')
        ) {
            return true;
        }

        $subject = strtolower($message->subject);

        foreach ([
            'delivery status notification',
            'undelivered mail',
            'mail delivery failed',
            'returned mail',
            'delivery failure',
            'failure notice',
        ] as $needle) {
            if (str_contains($subject, $needle)) {
                return true;
            }
        }

        $body = strtolower($message->body);

        return str_contains($body, 'final-recipient:')
            || str_contains($body, 'original-recipient:');
    }

    private function contieneBaja(InboxMessage $message): bool
    {
        return (bool) preg_match('/\bbaja\b/iu', $message->textoCompleto());
    }

    private function extraerEmailRebotado(InboxMessage $message): ?string
    {
        if (! empty($message->headers['x-failed-recipients'])) {
            $candidate = $this->primerEmailValido($message->headers['x-failed-recipients']);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        if (preg_match(
            '/(?:Final-Recipient|Original-Recipient):\s*(?:rfc822;)?\s*([^\s>;]+)/i',
            $message->body,
            $m
        )) {
            $candidate = $this->primerEmailValido($m[1]);

            if ($candidate !== null) {
                return $candidate;
            }
        }

        if (preg_match(
            '/(?:The following address|Recipient address rejected|failed permanently to|could not be delivered to)[^\n<]*[<\s]([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i',
            $message->body,
            $m
        )) {
            return Suppression::normalizeEmail($m[1]);
        }

        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $message->body, $matches)) {
            foreach ($matches[0] as $raw) {
                $email = Suppression::normalizeEmail($raw);

                if ($this->esDireccionSistema($email)) {
                    continue;
                }

                if (Lead::query()->where('email', $email)->exists()) {
                    return $email;
                }
            }

            foreach ($matches[0] as $raw) {
                $email = Suppression::normalizeEmail($raw);

                if (! $this->esDireccionSistema($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }

        return null;
    }

    private function primerEmailValido(string $raw): ?string
    {
        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $raw, $m)) {
            $email = Suppression::normalizeEmail($m[0]);

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        return null;
    }

    private function esDireccionSistema(string $email): bool
    {
        $local = explode('@', $email)[0] ?? '';

        return in_array($local, [
            'mailer-daemon',
            'postmaster',
            'mail-daemon',
            'noreply',
            'no-reply',
        ], true);
    }
}
