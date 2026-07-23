<?php

namespace Tests\Unit;

use App\Excepciones\UrlNoPermitida;
use App\Services\Soporte\GuardiaUrl;
use Tests\TestCase;

class GuardiaUrlTest extends TestCase
{
    private GuardiaUrl $guardia;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guardia = new GuardiaUrl;
    }

    public function test_rechaza_localhost(): void
    {
        $this->expectException(UrlNoPermitida::class);
        $this->guardia->comprobar('http://localhost/x');
    }

    public function test_rechaza_ip_privada_directa(): void
    {
        $this->expectException(UrlNoPermitida::class);
        $this->guardia->comprobar('http://192.168.1.1/');
    }

    public function test_rechaza_loopback(): void
    {
        $this->expectException(UrlNoPermitida::class);
        $this->guardia->comprobar('http://127.0.0.1/');
    }

    public function test_rechaza_link_local(): void
    {
        $this->expectException(UrlNoPermitida::class);
        $this->guardia->comprobar('http://169.254.169.254/');
    }

    public function test_rechaza_esquema_file(): void
    {
        $this->expectException(UrlNoPermitida::class);
        $this->guardia->comprobar('file:///etc/passwd');
    }

    public function test_rechaza_dominio_local(): void
    {
        $this->expectException(UrlNoPermitida::class);
        $this->guardia->comprobar('http://servidor.local/');
    }

    public function test_acepta_dominio_publico(): void
    {
        $this->guardia->comprobar('https://example.com/');
        $this->assertTrue($this->guardia->esSegura('https://example.com/'));
    }
}
