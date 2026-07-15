<?php

namespace Tests\Unit;

use App\Services\EmailScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmailScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    private function scraper(): EmailScraper
    {
        return new EmailScraper([
            'timeout' => 5,
            'user_agent' => 'SilgoDevBot/1.0',
            'respect_robots' => false,
            'contact_paths' => ['', '/contacto'],
            'team_paths' => ['/equipo', '/team'],
            'blacklist_domains' => ['sentry.io', 'wixpress.com'],
        ]);
    }

    public function test_extrae_mailto(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/html/mailto.html'));
        $emails = $this->scraper()->extractEmails($html);

        $this->assertContains('contacto@agencia-fixture.com', $emails);
    }

    public function test_extrae_json_ld(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/html/jsonld.html'));
        $emails = $this->scraper()->extractEmails($html);

        $this->assertContains('organizacion@agencia-fixture.com', $emails);
        $this->assertContains('ventas@agencia-fixture.com', $emails);
    }

    public function test_extrae_email_ofuscado(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/html/obfuscated.html'));
        $emails = $this->scraper()->extractEmails($html);

        $this->assertContains('ana@agencia-fixture.com', $emails);
        $this->assertContains('luis@agencia-fixture.com', $emails);
        $this->assertContains('maria@agencia-fixture.com', $emails);
    }

    public function test_pagina_equipo_prioriza_email_personal(): void
    {
        Http::fake([
            'https://agencia-fixture.com/robots.txt' => Http::response("User-agent: *\nDisallow:", 200),
            'https://agencia-fixture.com' => Http::response('<html><body>info@agencia-fixture.com</body></html>', 200),
            'https://agencia-fixture.com/contacto' => Http::response('', 404),
            'https://agencia-fixture.com/equipo' => Http::response(
                file_get_contents(base_path('tests/Fixtures/html/equipo.html')),
                200
            ),
            'https://agencia-fixture.com/team' => Http::response('', 404),
        ]);

        $email = $this->scraper()->findEmail('https://agencia-fixture.com');

        $this->assertSame('carlos.perez@agencia-fixture.com', $email);
    }

    public function test_respeta_robots_en_rutas_extra(): void
    {
        Http::fake([
            'https://agencia-fixture.com/robots.txt' => Http::response(
                "User-agent: *\nDisallow: /equipo\nDisallow: /team\n",
                200
            ),
            'https://agencia-fixture.com' => Http::response('<html>info@agencia-fixture.com</html>', 200),
            'https://agencia-fixture.com/contacto' => Http::response('', 404),
            'https://agencia-fixture.com/equipo' => Http::response(
                file_get_contents(base_path('tests/Fixtures/html/equipo.html')),
                200
            ),
        ]);

        $scraper = new EmailScraper([
            'timeout' => 5,
            'user_agent' => 'SilgoDevBot/1.0',
            'respect_robots' => true,
            'contact_paths' => [''],
            'team_paths' => ['/equipo'],
            'blacklist_domains' => [],
        ]);

        $email = $scraper->findEmail('https://agencia-fixture.com');

        // /equipo bloqueado: solo queda el genérico de la home.
        $this->assertSame('info@agencia-fixture.com', $email);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/equipo'));
    }
}
