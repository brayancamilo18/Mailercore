<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class EmailVerifier
{
    /**
     * @param  array{smtp_probe?: bool, smtp_timeout?: int, disposable_domains?: array<int, string>}  $config
     */
    public function __construct(private array $config = [])
    {
        if ($this->config === []) {
            $this->config = config('outreach.verifier', []);
        }
    }

    /**
     * Valida un email: 'valido', 'invalido' o 'riesgo'.
     */
    public function verify(string $email): string
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'invalido';
        }

        $domain = substr(strrchr($email, '@'), 1) ?: '';

        if ($domain === '') {
            return 'invalido';
        }

        if (! $this->domainHasMx($domain)) {
            return 'invalido';
        }

        $disposable = array_map('strtolower', $this->config['disposable_domains'] ?? []);

        if (in_array($domain, $disposable, true)) {
            return 'riesgo';
        }

        if (! ($this->config['smtp_probe'] ?? false)) {
            return 'valido';
        }

        return $this->probeSmtp($email, $domain);
    }

    /**
     * Comprueba si el dominio tiene registros MX (cache 1 día).
     */
    private function domainHasMx(string $domain): bool
    {
        return (bool) Cache::remember(
            'outreach:mx:'.$domain,
            now()->addDay(),
            function () use ($domain): bool {
                if (checkdnsrr($domain, 'MX')) {
                    return true;
                }

                $records = @dns_get_record($domain, DNS_MX);

                return is_array($records) && $records !== [];
            }
        );
    }

    /**
     * Handshake SMTP RCPT TO. Fallo de conexión => 'riesgo' (nunca 'invalido').
     */
    private function probeSmtp(string $email, string $domain): string
    {
        $timeout = (int) ($this->config['smtp_timeout'] ?? 5);

        try {
            $mxHost = $this->primaryMxHost($domain);

            if ($mxHost === null) {
                return 'riesgo';
            }

            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($mxHost, 25, $errno, $errstr, $timeout);

            if ($socket === false) {
                return 'riesgo';
            }

            stream_set_timeout($socket, $timeout);

            $this->leerRespuestaSmtp($socket);
            $this->escribirSmtp($socket, 'EHLO silgodev.es');
            $this->leerRespuestaSmtp($socket);
            $this->escribirSmtp($socket, 'MAIL FROM:<noreply@silgodev.es>');
            $this->leerRespuestaSmtp($socket);
            $this->escribirSmtp($socket, 'RCPT TO:<'.$email.'>');
            $rcpt = $this->leerRespuestaSmtp($socket);
            $this->escribirSmtp($socket, 'QUIT');
            fclose($socket);

            $codigo = (int) substr(trim($rcpt), 0, 3);

            // 2xx acepta el destinatario; resto lo tratamos como riesgo (no invalido por SMTP).
            return ($codigo >= 200 && $codigo < 300) ? 'valido' : 'riesgo';
        } catch (\Throwable) {
            return 'riesgo';
        }
    }

    /**
     * Devuelve el host MX de mayor prioridad (número más bajo).
     */
    private function primaryMxHost(string $domain): ?string
    {
        $records = @dns_get_record($domain, DNS_MX);

        if (! is_array($records) || $records === []) {
            return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA') ? $domain : null;
        }

        usort($records, fn (array $a, array $b): int => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));

        $host = $records[0]['target'] ?? null;

        return is_string($host) && $host !== '' ? rtrim($host, '.') : null;
    }

    private function escribirSmtp($socket, string $line): void
    {
        fwrite($socket, $line."\r\n");
    }

    private function leerRespuestaSmtp($socket): string
    {
        $respuesta = '';

        while (($line = fgets($socket, 512)) !== false) {
            $respuesta .= $line;

            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $respuesta;
    }
}
