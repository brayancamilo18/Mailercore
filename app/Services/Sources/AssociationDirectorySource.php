<?php

namespace App\Services\Sources;

use App\Services\RobotsChecker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Plantilla reutilizable para scrapear listados de asociaciones / directorios.
 *
 * Configura base_url, rutas de listado y selectores CSS. No extrae emails:
 * eso lo hace EmailScraper en el pipeline de captura.
 */
class AssociationDirectorySource implements LeadSource
{
    private RobotsChecker $robots;

    /**
     * @param  array{
     *     base_url: string,
     *     listing_paths: array<int, string>,
     *     selectors: array{
     *         card: string,
     *         name: string,
     *         website: string,
     *         phone?: string|null,
     *         address?: string|null
     *     },
     *     pagination?: array{
     *         enabled?: bool,
     *         query_param?: string,
     *         start?: int,
     *         max_pages?: int
     *     },
     *     timeout?: int,
     *     user_agent?: string,
     *     pause_min_ms?: int,
     *     pause_max_ms?: int,
     *     respect_robots?: bool
     * }  $config
     */
    public function __construct(private array $config, ?RobotsChecker $robots = null)
    {
        $this->robots = $robots ?? new RobotsChecker([
            'timeout' => $config['timeout'] ?? 15,
            'user_agent' => $config['user_agent'] ?? 'SilgoDevBot/1.0',
            'respect_robots' => $config['respect_robots'] ?? true,
        ]);
    }

    public function key(): string
    {
        return 'association_directory';
    }

    /**
     * @return iterable<int, LeadCandidate>
     */
    public function fetch(): iterable
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');

        if ($baseUrl === '') {
            Log::warning('AssociationDirectorySource: base_url vacío; no se scrapea.');

            return;
        }

        $paths = array_values($this->config['listing_paths'] ?? []);
        $seenWebsites = [];

        foreach ($paths as $listingPath) {
            yield from $this->fetchListingPath($baseUrl, (string) $listingPath, $seenWebsites);
        }
    }

    /**
     * Recorre una ruta de listado (con o sin paginación ?page=N).
     *
     * @param  array<string, true>  $seenWebsites
     * @return iterable<int, LeadCandidate>
     */
    private function fetchListingPath(string $baseUrl, string $listingPath, array &$seenWebsites): iterable
    {
        $pagination = $this->config['pagination'] ?? [];
        $paginate = (bool) ($pagination['enabled'] ?? false);
        $param = (string) ($pagination['query_param'] ?? 'page');
        $page = (int) ($pagination['start'] ?? 1);
        $maxPages = (int) ($pagination['max_pages'] ?? 50);
        $primeraPeticion = true;

        do {
            $url = $this->buildListingUrl($baseUrl, $listingPath, $paginate ? $page : null, $param);

            if (! $primeraPeticion) {
                $this->pausar();
            }
            $primeraPeticion = false;

            if (! $this->robots->isUrlAllowed($url)) {
                Log::info('AssociationDirectorySource: ruta omitida por robots.txt', [
                    'url' => $url,
                ]);

                return;
            }

            $html = $this->download($url);

            if ($html === null) {
                return;
            }

            $cards = $this->parseCards($html);
            $yielded = 0;

            foreach ($cards as $card) {
                try {
                    $candidate = $this->cardToCandidate($card);

                    if ($candidate === null) {
                        continue;
                    }

                    $websiteKey = $candidate->website !== null
                        ? strtolower($candidate->website)
                        : 'name:'.strtolower($candidate->name);

                    if (isset($seenWebsites[$websiteKey])) {
                        continue;
                    }

                    $seenWebsites[$websiteKey] = true;
                    $yielded++;

                    yield $candidate;
                } catch (\Throwable $e) {
                    Log::warning('AssociationDirectorySource: ficha omitida', [
                        'error' => $e->getMessage(),
                    ]);
                    report($e);
                }
            }

            if (! $paginate || $yielded === 0 || count($cards) === 0) {
                return;
            }

            $page++;
        } while ($page < ($pagination['start'] ?? 1) + $maxPages);
    }

    /**
     * @return list<Crawler>
     */
    private function parseCards(string $html): array
    {
        $selectors = $this->config['selectors'] ?? [];
        $cardSelector = $selectors['card'] ?? null;

        if (! is_string($cardSelector) || $cardSelector === '') {
            return [];
        }

        $crawler = new Crawler($html);
        $cards = [];

        $crawler->filter($cardSelector)->each(function (Crawler $node) use (&$cards): void {
            $cards[] = $node;
        });

        return $cards;
    }

    private function cardToCandidate(Crawler $card): ?LeadCandidate
    {
        $selectors = $this->config['selectors'] ?? [];
        $nameSelector = $selectors['name'] ?? null;
        $websiteSelector = $selectors['website'] ?? null;

        if (! is_string($nameSelector) || ! is_string($websiteSelector)) {
            return null;
        }

        $nameNode = $card->filter($nameSelector);

        if ($nameNode->count() === 0) {
            return null;
        }

        $name = trim($nameNode->text(''));

        if ($name === '') {
            return null;
        }

        $websiteNode = $card->filter($websiteSelector);

        if ($websiteNode->count() === 0) {
            return null;
        }

        $website = $this->extractWebsite($websiteNode);

        if ($website === null || $website === '') {
            return null;
        }

        $phone = $this->optionalText($card, $selectors['phone'] ?? null);
        $address = $this->optionalText($card, $selectors['address'] ?? null);

        return new LeadCandidate(
            name: $name,
            website: $website,
            source: $this->key(),
            phone: $phone,
            email: null,
            address: $address,
            externalId: null,
        );
    }

    private function extractWebsite(Crawler $node): ?string
    {
        $href = $node->attr('href');

        if (is_string($href) && trim($href) !== '') {
            return $this->normalizeWebsite(trim($href));
        }

        // Si el selector apunta a un contenedor con un <a> hijo.
        $link = $node->filter('a[href]');

        if ($link->count() > 0) {
            $childHref = $link->attr('href');

            if (is_string($childHref) && trim($childHref) !== '') {
                return $this->normalizeWebsite(trim($childHref));
            }
        }

        $text = trim($node->text(''));

        return $text !== '' ? $this->normalizeWebsite($text) : null;
    }

    private function normalizeWebsite(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, 'mailto:') || str_starts_with($url, '#')) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        } elseif (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        return $url;
    }

    private function optionalText(Crawler $card, mixed $selector): ?string
    {
        if (! is_string($selector) || $selector === '') {
            return null;
        }

        $node = $card->filter($selector);

        if ($node->count() === 0) {
            return null;
        }

        $text = trim($node->text(''));

        return $text !== '' ? $text : null;
    }

    private function buildListingUrl(string $baseUrl, string $listingPath, ?int $page, string $param): string
    {
        $path = $listingPath === '' ? '/' : $listingPath;

        if ($path[0] !== '/') {
            $path = '/'.$path;
        }

        $url = $baseUrl.$path;

        if ($page === null) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.$param.'='.$page;
    }

    private function download(string $url): ?string
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);
        $userAgent = (string) ($this->config['user_agent'] ?? 'SilgoDevBot/1.0');

        try {
            $response = Http::withHeaders(['User-Agent' => $userAgent])
                ->timeout($timeout)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('AssociationDirectorySource: HTTP no exitoso', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('AssociationDirectorySource: error al descargar', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function pausar(): void
    {
        $min = (int) ($this->config['pause_min_ms'] ?? 2000);
        $max = (int) ($this->config['pause_max_ms'] ?? 4000);

        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        if ($max <= 0) {
            return;
        }

        usleep(random_int($min, $max) * 1000);
    }
}
