<?php

namespace Tests\Unit;

use App\Services\RobotsChecker;
use App\Services\Sources\AssociationDirectorySource;
use App\Services\Sources\LeadCandidate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AssociationDirectorySourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function baseConfig(array $overrides = []): array
    {
        return array_replace_recursive([
            'base_url' => 'https://directorio-asociacion.test',
            'listing_paths' => ['/miembros'],
            'pagination' => [
                'enabled' => false,
                'query_param' => 'page',
                'start' => 1,
                'max_pages' => 5,
            ],
            'selectors' => [
                'card' => '.member-card',
                'name' => '.member-name',
                'website' => 'a.member-website',
                'phone' => '.member-phone',
                'address' => '.member-address',
            ],
            'timeout' => 5,
            'user_agent' => 'SilgoDevBot/1.0',
            'pause_min_ms' => 0,
            'pause_max_ms' => 0,
            'respect_robots' => true,
        ], $overrides);
    }

    public function test_fetch_devuelve_tres_dtos_desde_fixture(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/html/association-listing.html'));

        Http::fake([
            'https://directorio-asociacion.test/robots.txt' => Http::response(
                "User-agent: *\nDisallow:\n",
                200
            ),
            'https://directorio-asociacion.test/miembros' => Http::response($html, 200),
        ]);

        $source = new AssociationDirectorySource($this->baseConfig());
        $candidates = iterator_to_array($source->fetch());

        $this->assertCount(3, $candidates);
        $this->assertContainsOnlyInstancesOf(LeadCandidate::class, $candidates);

        $this->assertSame('Estudio Alfa', $candidates[0]->name);
        $this->assertSame('https://estudio-alfa.example', $candidates[0]->website);
        $this->assertSame('+34 600 111 111', $candidates[0]->phone);
        $this->assertSame('Calle Uno 1, Madrid', $candidates[0]->address);
        $this->assertNull($candidates[0]->email);
        $this->assertSame('association_directory', $candidates[0]->source);

        $this->assertSame('Agencia Beta', $candidates[1]->name);
        $this->assertSame('https://agencia-beta.example', $candidates[1]->website);

        $this->assertSame('Creativos Gamma', $candidates[2]->name);
        $this->assertSame('https://creativos-gamma.example', $candidates[2]->website);
    }

    public function test_omite_ruta_prohibida_por_robots(): void
    {
        Http::fake([
            'https://directorio-asociacion.test/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /miembros\n",
                200
            ),
            'https://directorio-asociacion.test/miembros' => Http::response(
                file_get_contents(base_path('tests/Fixtures/html/association-listing.html')),
                200
            ),
        ]);

        Log::spy();

        $robots = new RobotsChecker([
            'timeout' => 5,
            'user_agent' => 'SilgoDevBot/1.0',
            'respect_robots' => true,
        ]);

        $source = new AssociationDirectorySource($this->baseConfig(), $robots);
        $candidates = iterator_to_array($source->fetch());

        $this->assertSame([], $candidates);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/miembros')
            && ! str_contains($request->url(), 'robots.txt'));

        Log::assertLogged('info', fn ($message, $context) => str_contains($message, 'robots.txt')
            || (isset($context['url']) && str_contains((string) $context['url'], '/miembros')));
    }
}
