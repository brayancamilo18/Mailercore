<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelAutenticacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutasGetProtegidas(): array
    {
        return [
            'resumen' => ['/'],
            'leads' => ['/leads'],
            'leads.ficha' => ['/leads/{lead}'],
            'cola' => ['/cola'],
            'mensajes.ver' => ['/mensajes/{mensaje}'],
            'salud' => ['/salud'],
            'cosecha' => ['/cosecha'],
            'api.estado' => ['/api/estado'],
        ];
    }

    #[DataProvider('rutasGetProtegidas')]
    public function test_todas_las_rutas_redirigen_a_login_sin_autenticar(string $ruta): void
    {
        $url = $this->resolverRuta($ruta);

        $this->get($url)->assertRedirect(route('login'));
    }

    #[DataProvider('rutasGetProtegidas')]
    public function test_con_usuario_devuelven_doscientos(string $ruta): void
    {
        $usuario = User::factory()->create();
        $url = $this->resolverRuta($ruta);

        $this->actingAs($usuario)->get($url)->assertOk();
    }

    public function test_post_sin_csrf_devuelve_419(): void
    {
        $this->forzarValidacionCsrf();

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->post('/envio/pausar')
            ->assertStatus(419);
    }

    public function test_no_existe_ruta_de_registro(): void
    {
        $this->get('/register')->assertNotFound();
    }

    private function forzarValidacionCsrf(): void
    {
        $this->app->instance(
            ValidateCsrfToken::class,
            new class($this->app, $this->app->make(Encrypter::class)) extends ValidateCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            }
        );
    }

    private function resolverRuta(string $ruta): string
    {
        if (str_contains($ruta, '{lead}')) {
            $lead = Lead::factory()->create();

            return str_replace('{lead}', (string) $lead->id, $ruta);
        }

        if (str_contains($ruta, '{mensaje}')) {
            $mensaje = Mensaje::factory()->create();

            return str_replace('{mensaje}', (string) $mensaje->id, $ruta);
        }

        return $ruta;
    }
}
