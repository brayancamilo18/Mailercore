<?php

namespace Tests\Unit;

use App\Services\OverpassClient;
use App\Services\ScrapeRateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitAndBackoffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('scrape:global');
        RateLimiter::clear('scrape:host:ejemplo.test');
    }

    public function test_overpass_backoff_exponencial(): void
    {
        $this->assertSame(1_000_000, OverpassClient::backoffMicroseconds(0));
        $this->assertSame(2_000_000, OverpassClient::backoffMicroseconds(1));
        $this->assertSame(4_000_000, OverpassClient::backoffMicroseconds(2));
        $this->assertSame(16_000_000, OverpassClient::backoffMicroseconds(10));
    }

    public function test_overpass_request_pause_respeta_harvest_delay(): void
    {
        config([
            'outreach.harvest.overpass_delay' => 1500,
        ]);

        $client = new OverpassClient([
            'timeout' => 10,
            'request_pause_ms' => 500,
            'filters' => [],
            'endpoints' => ['https://overpass.test/api'],
        ]);

        $this->assertSame(1500, $client->requestPauseMs());
    }

    public function test_scrape_rate_limiter_marca_intentos(): void
    {
        config([
            'outreach.harvest.requests_per_minute' => 100,
            'outreach.harvest.requests_per_domain_per_minute' => 100,
        ]);

        $limiter = new ScrapeRateLimiter;
        $limiter->throttle('https://ejemplo.test/contacto');

        $this->assertSame('ejemplo.test', $limiter->hostFromUrl('https://ejemplo.test/x'));
        $this->assertTrue(RateLimiter::attempts('scrape:global') >= 1);
        $this->assertTrue(RateLimiter::attempts('scrape:host:ejemplo.test') >= 1);
    }
}
