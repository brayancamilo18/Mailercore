<?php

namespace App\Services\Soporte;

use App\Excepciones\UrlNoPermitida;

class GuardiaUrl
{
    /** Rangos privados y reservados que nunca se deben visitar. */
    private const RANGOS_PROHIBIDOS = [
        ['10.0.0.0', '10.255.255.255'],
        ['127.0.0.0', '127.255.255.255'],
        ['169.254.0.0', '169.254.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.0.0.0', '192.0.0.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['198.18.0.0', '198.19.255.255'],
        ['0.0.0.0', '0.255.255.255'],
        ['100.64.0.0', '100.127.255.255'],
        ['224.0.0.0', '255.255.255.255'],
    ];

    /**
     * Comprueba que la URL es segura de visitar.
     *
     * @throws UrlNoPermitida
     */
    public function comprobar(string $url): void
    {
        $partes = parse_url($url);

        if ($partes === false || empty($partes['host'])) {
            throw new UrlNoPermitida("URL sin host: {$url}");
        }

        $esquema = strtolower($partes['scheme'] ?? '');

        if (! in_array($esquema, ['http', 'https'], true)) {
            throw new UrlNoPermitida("Esquema no permitido: {$esquema}");
        }

        $host = strtolower($partes['host']);

        if (in_array($host, ['localhost', 'metadata.google.internal'], true)) {
            throw new UrlNoPermitida("Host prohibido: {$host}");
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new UrlNoPermitida("Dominio interno: {$host}");
        }

        foreach ($this->resolver($host) as $ip) {
            if ($this->esIpProhibida($ip)) {
                throw new UrlNoPermitida("IP no permitida ({$ip}) para {$host}");
            }
        }
    }

    /** Igual que comprobar() pero devuelve bool en vez de lanzar. */
    public function esSegura(string $url): bool
    {
        try {
            $this->comprobar($url);

            return true;
        } catch (UrlNoPermitida) {
            return false;
        }
    }

    /** @return list<string> */
    private function resolver(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        $v4 = @dns_get_record($host, DNS_A);
        if (is_array($v4)) {
            foreach ($v4 as $r) {
                if (isset($r['ip'])) {
                    $ips[] = $r['ip'];
                }
            }
        }

        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $r) {
                if (isset($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $resuelta = gethostbyname($host);
            if ($resuelta !== $host) {
                $ips[] = $resuelta;
            }
        }

        return $ips;
    }

    private function esIpProhibida(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalizada = strtolower($ip);

            return $normalizada === '::1'
                || str_starts_with($normalizada, 'fc')
                || str_starts_with($normalizada, 'fd')
                || str_starts_with($normalizada, 'fe80');
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        $valor = ip2long($ip);

        if ($valor === false) {
            return true;
        }

        foreach (self::RANGOS_PROHIBIDOS as [$desde, $hasta]) {
            if ($valor >= ip2long($desde) && $valor <= ip2long($hasta)) {
                return true;
            }
        }

        return false;
    }
}
