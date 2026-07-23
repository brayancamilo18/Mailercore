<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_exige_autenticacion(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_el_login_responde_ok(): void
    {
        $this->get('/login')->assertOk();
    }
}
