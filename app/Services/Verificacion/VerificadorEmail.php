<?php

namespace App\Services\Verificacion;

use App\Models\EventoInbox;
use App\Models\LeadEmail;
use App\Models\Suppression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VerificadorEmail
{
    /** @return 'valido'|'riesgo'|'invalido' */
    public function verificar(LeadEmail $leadEmail): string
    {
        $email = strtolower(trim($leadEmail->email));

        // 1. Sintaxis
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->guardar($leadEmail, 'invalido', false, null);

            return 'invalido';
        }

        $dominio = Suppression::dominioDeEmail($email);

        if ($dominio === null) {
            $this->guardar($leadEmail, 'invalido', false, null);

            return 'invalido';
        }

        // 2. Excluido
        if (Suppression::existe($email)) {
            $this->guardar($leadEmail, 'invalido', null, null);

            return 'invalido';
        }

        // 3. Rebote duro previo en ese dominio → dominio quemado
        $rebotesDominio = EventoInbox::query()
            ->where('tipo', 'rebote_duro')
            ->where('email', 'like', '%@'.$dominio)
            ->count();

        if ($rebotesDominio >= 2) {
            Suppression::registrarDominio($dominio, 'rebote_duro',
                "{$rebotesDominio} rebotes duros previos");
            $this->guardar($leadEmail, 'invalido', null, null);

            return 'invalido';
        }

        // 4. MX
        if (! $this->tieneMx($dominio)) {
            $this->guardar($leadEmail, 'invalido', false, null);

            return 'invalido';
        }

        // 5. Desechable
        if (in_array($dominio, config('outreach.verificador.dominios_desechables'), true)) {
            $this->guardar($leadEmail, 'riesgo', true, null);

            return 'riesgo';
        }

        // 6. Catch-all
        $catchAll = $this->esCatchAll($dominio);

        if ($catchAll === true) {
            $this->guardar($leadEmail, 'riesgo', true, true);

            return 'riesgo';
        }

        $this->guardar($leadEmail, 'valido', true, $catchAll);

        return 'valido';
    }

    /** Comprueba MX con caché de 7 días. */
    private function tieneMx(string $dominio): bool
    {
        return (bool) Cache::remember(
            'mx:'.$dominio,
            now()->addDays((int) config('outreach.verificador.cache_mx_dias')),
            function () use ($dominio): bool {
                if (checkdnsrr($dominio, 'MX')) {
                    return true;
                }

                $registros = @dns_get_record($dominio, DNS_MX);

                return is_array($registros) && $registros !== [];
            }
        );
    }

    /**
     * ¿El dominio acepta cualquier buzón? Si la sonda está desactivada,
     * devuelve null y no se hace ninguna conexión.
     */
    public function esCatchAll(string $dominio): ?bool
    {
        if (! config('outreach.verificador.sonda_smtp')) {
            return null;
        }

        return Cache::remember(
            'catchall:'.$dominio,
            now()->addDays((int) config('outreach.verificador.cache_catchall_dias')),
            function () use ($dominio): bool {
                $inventado = 'zzq'.Str::random(12).'@'.$dominio;

                return $this->sondaRcpt($inventado, $dominio) === true;
            }
        );
    }

    /**
     * Abre el MX principal en el puerto 25, hace EHLO / MAIL FROM / RCPT TO,
     * lee el código y cierra con QUIT. Devuelve true si el código es 2xx,
     * false si es 5xx, null si no pudo conectar.
     */
    private function sondaRcpt(string $email, string $dominio): ?bool
    {
        $timeout = (int) config('outreach.verificador.timeout_smtp', 5);

        try {
            $mx = $this->mxPrincipal($dominio);
            if ($mx === null) {
                return null;
            }

            $socket = @fsockopen($mx, 25, $errno, $errstr, $timeout);
            if ($socket === false) {
                return null;
            }

            stream_set_timeout($socket, $timeout);

            $this->leerRespuestaSmtp($socket);
            fwrite($socket, "EHLO silgodev.es\r\n");
            $this->leerRespuestaSmtp($socket);
            fwrite($socket, "MAIL FROM:<verificar@silgodev.es>\r\n");
            $this->leerRespuestaSmtp($socket);
            fwrite($socket, 'RCPT TO:<'.$email.">\r\n");
            $codigo = $this->leerRespuestaSmtp($socket);
            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            if ($codigo === null) {
                return null;
            }

            if ($codigo >= 200 && $codigo < 300) {
                return true;
            }

            if ($codigo >= 500 && $codigo < 600) {
                return false;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function mxPrincipal(string $dominio): ?string
    {
        $registros = @dns_get_record($dominio, DNS_MX);
        if (! is_array($registros) || $registros === []) {
            return null;
        }

        usort($registros, fn (array $a, array $b): int => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));

        $host = $registros[0]['target'] ?? null;

        return is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * @param  resource  $socket
     */
    private function leerRespuestaSmtp($socket): ?int
    {
        $linea = '';
        while (($chunk = fgets($socket, 512)) !== false) {
            $linea = $chunk;
            if (isset($chunk[3]) && $chunk[3] === ' ') {
                break;
            }
        }

        if ($linea === '' || ! preg_match('/^(\d{3})/', $linea, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    private function guardar(LeadEmail $e, string $estado, ?bool $mx, ?bool $catchAll): void
    {
        $e->update([
            'estado_verificacion' => $estado,
            'mx_ok' => $mx,
            'es_catch_all' => $catchAll,
            'verificado_at' => now(),
        ]);
    }
}
