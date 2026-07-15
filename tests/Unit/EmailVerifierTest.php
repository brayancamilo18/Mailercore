<?php

namespace Tests\Unit;

use App\Services\EmailVerifier;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmailVerifierTest extends TestCase
{
    /**
     * Un email con sintaxis inválida debe marcarse como invalido.
     */
    public function test_sintaxis_invalida_devuelve_invalido(): void
    {
        $verifier = new EmailVerifier([
            'smtp_probe' => false,
            'disposable_domains' => [],
        ]);

        $this->assertSame('invalido', $verifier->verify('esto-no-es-un-email'));
        $this->assertSame('invalido', $verifier->verify('sin-arroba.com'));
    }

    /**
     * Un dominio sin registros MX debe marcarse como invalido.
     */
    public function test_dominio_sin_mx_devuelve_invalido(): void
    {
        Cache::flush();

        $verifier = new EmailVerifier([
            'smtp_probe' => false,
            'disposable_domains' => [],
        ]);

        // .invalid es un TLD reservado; no debe resolver MX.
        $this->assertSame('invalido', $verifier->verify('usuario@dominio-inexistente-xyz.invalid'));
    }

    /**
     * Un dominio desechable con MX (o sin llegar a MX) se marca riesgo si pasa sintaxis;
     * usamos un dominio de la lista y forzamos MX vía cache.
     */
    public function test_dominio_desechable_devuelve_riesgo(): void
    {
        Cache::put('outreach:mx:mailinator.com', true, now()->addDay());

        $verifier = new EmailVerifier([
            'smtp_probe' => false,
            'disposable_domains' => ['mailinator.com'],
        ]);

        $this->assertSame('riesgo', $verifier->verify('test@mailinator.com'));
    }
}
