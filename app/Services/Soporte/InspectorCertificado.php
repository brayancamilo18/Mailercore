<?php

namespace App\Services\Soporte;

use App\DTO\ResultadoCertificado;

/**
 * Lee el certificado TLS del host en dos pasadas:
 * 1. Con verificación de cadena y nombre. Si conecta, la cadena es válida.
 * 2. Sin verificación, solo para sacar la fecha de caducidad cuando la cadena falla.
 *
 * cert_valido (noCaducado) significa exclusivamente «no ha caducado».
 */
class InspectorCertificado
{
    /**
     * @param  (callable(string $host, bool $verificarCadena): ?array{cert: mixed})|null  $conector
     */
    public function __construct(
        private mixed $conector = null,
    ) {
        $this->conector ??= fn (string $host, bool $verificar): ?array => $this->abrir($host, $verificar);
    }

    public function inspeccionar(string $host): ResultadoCertificado
    {
        $conCadena = ($this->conector)($host, true);
        if (is_array($conCadena) && array_key_exists('cert', $conCadena) && $conCadena['cert']) {
            return $this->desdeCertificado($conCadena['cert'], cadenaValida: true);
        }

        $sinCadena = ($this->conector)($host, false);
        if (is_array($sinCadena) && array_key_exists('cert', $sinCadena) && $sinCadena['cert']) {
            return $this->desdeCertificado($sinCadena['cert'], cadenaValida: false);
        }

        return new ResultadoCertificado(null, null, null);
    }

    private function desdeCertificado(mixed $cert, bool $cadenaValida): ResultadoCertificado
    {
        $info = @openssl_x509_parse($cert);

        if (! is_array($info) || ! isset($info['validTo_time_t'])) {
            return new ResultadoCertificado($cadenaValida, null, null);
        }

        $expiraTs = (int) $info['validTo_time_t'];

        return new ResultadoCertificado(
            $cadenaValida,
            $expiraTs > time(),
            now()->setTimestamp($expiraTs),
        );
    }

    /**
     * @return array{cert: mixed}|null
     */
    private function abrir(string $host, bool $verificarCadena): ?array
    {
        try {
            $contexto = stream_context_create(['ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => $verificarCadena,
                'verify_peer_name' => $verificarCadena,
            ]]);

            $socket = @stream_socket_client(
                "ssl://{$host}:443",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $contexto
            );

            if ($socket === false) {
                return null;
            }

            $params = stream_context_get_params($socket);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;
            fclose($socket);

            if ($cert === null) {
                return null;
            }

            return ['cert' => $cert];
        } catch (\Throwable) {
            return null;
        }
    }
}
