<?php

namespace Tests\Unit;

use App\Services\Soporte\InspectorCertificado;
use Tests\TestCase;

class InspectorCertificadoTest extends TestCase
{
    /** Certificado X.509 autofirmado vigente ~1 año. */
    private function certificado(int $diasValidez = 365): mixed
    {
        $clave = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($clave);

        $csr = openssl_csr_new(['commonName' => 'ejemplo.es'], $clave, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($csr);

        $cert = openssl_csr_sign($csr, null, $clave, $diasValidez, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($cert);

        return $cert;
    }

    public function test_cadena_valida_cuando_la_primera_conexion_verifica(): void
    {
        $cert = $this->certificado();
        $llamadas = [];

        $inspector = new InspectorCertificado(function (string $host, bool $verificar) use ($cert, &$llamadas): ?array {
            $llamadas[] = $verificar;
            $this->assertSame('ejemplo.es', $host);

            if ($verificar) {
                return ['cert' => $cert];
            }

            $this->fail('No debería reintentar sin verificación si la cadena ya pasó');
        });

        $resultado = $inspector->inspeccionar('ejemplo.es');

        $this->assertSame([true], $llamadas);
        $this->assertTrue($resultado->cadenaValida);
        $this->assertTrue($resultado->noCaducado);
        $this->assertNotNull($resultado->expiraAt);
        $this->assertTrue($resultado->expiraAt->isFuture());
    }

    public function test_cadena_invalida_pero_lee_la_fecha_en_el_reintento(): void
    {
        $cert = $this->certificado();
        $llamadas = [];

        $inspector = new InspectorCertificado(function (string $host, bool $verificar) use ($cert, &$llamadas): ?array {
            $llamadas[] = $verificar;

            // Como un autofirmado o un nombre que no coincide: verify_peer falla.
            if ($verificar) {
                return null;
            }

            return ['cert' => $cert];
        });

        $resultado = $inspector->inspeccionar('ejemplo.es');

        $this->assertSame([true, false], $llamadas);
        $this->assertFalse($resultado->cadenaValida);
        $this->assertTrue($resultado->noCaducado);
        $this->assertNotNull($resultado->expiraAt);
        $this->assertTrue($resultado->expiraAt->isFuture());
    }

    public function test_todo_null_si_no_hay_tls(): void
    {
        $inspector = new InspectorCertificado(fn (): ?array => null);

        $resultado = $inspector->inspeccionar('ejemplo.es');

        $this->assertNull($resultado->cadenaValida);
        $this->assertNull($resultado->noCaducado);
        $this->assertNull($resultado->expiraAt);
    }

    public function test_cert_valido_refleja_solo_la_caducidad(): void
    {
        $cert = $this->certificado(0);
        $info = openssl_x509_parse($cert);
        $this->assertIsArray($info);

        $inspector = new InspectorCertificado(function (string $host, bool $verificar) use ($cert): ?array {
            return $verificar ? null : ['cert' => $cert];
        });

        $resultado = $inspector->inspeccionar('viejo.es');

        $this->assertFalse($resultado->cadenaValida);
        $this->assertSame(((int) $info['validTo_time_t']) > time(), $resultado->noCaducado);
        $this->assertNotNull($resultado->expiraAt);
    }
}
