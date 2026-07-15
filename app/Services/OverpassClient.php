<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OverpassClient
{
    /** Backoff base (µs) ante 429/504; se multiplica 2^intento. */
    private const BACKOFF_BASE_US = 1_000_000;

    private const BACKOFF_MAX_US = 16_000_000;

    /**
     * @param  array{
     *     endpoint?: string,
     *     endpoints?: array<int, string>,
     *     timeout: int,
     *     request_pause_ms?: int,
     *     area?: string,
     *     areas?: array<int, array{name: string, admin_level: int|string}>,
     *     filters: array<int, array{0: string, 1: string}>,
     *     filters_negocios?: array<int, array{0: string, 1: string}>,
     *     noisy_filters?: array<int, array{0: string, 1: string}>
     * }  $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * Busca leads en Overpass (API estable: array completo).
     *
     * @param  'filters'|'filters_negocios'  $filtersKey
     * @return array<int, array{
     *     place_id: string,
     *     name: string,
     *     website: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     address: ?string,
     *     segmento: string
     * }>
     */
    public function search(string $filtersKey = 'filters'): array
    {
        return iterator_to_array($this->searchStream($filtersKey), false);
    }

    /**
     * Emite candidatos filtro a filtro (el panel puede ir mostrando leads al vuelo).
     *
     * @param  'filters'|'filters_negocios'  $filtersKey
     * @return \Generator<int, array{
     *     place_id: string,
     *     name: string,
     *     website: ?string,
     *     phone: ?string,
     *     email: ?string,
     *     address: ?string,
     *     segmento: string
     * }>
     */
    public function searchStream(string $filtersKey = 'filters'): \Generator
    {
        /** @var array<string, true> $seen */
        $seen = [];
        $areas = $this->areas();
        $filters = $this->config[$filtersKey] ?? [];
        $segmento = $filtersKey === 'filters_negocios' ? 'negocio' : 'agencia';
        $pauseMs = $this->requestPauseMs();
        $primera = true;
        $consultas = 0;
        $fallos = 0;
        $acumulado = 0;

        foreach ($areas as $area) {
            $areaName = $area['name'];
            $adminLevel = (string) $area['admin_level'];

            foreach ($filters as [$tag, $value]) {
                if (! $primera && $pauseMs > 0) {
                    usleep($pauseMs * 1000);
                }
                $primera = false;
                $consultas++;

                try {
                    $elements = $this->fetchElements($areaName, $adminLevel, $tag, $value);
                } catch (\Throwable $e) {
                    $fallos++;
                    Log::warning('Overpass filtro omitido tras fallos de espejo', [
                        'area' => $areaName,
                        'admin_level' => $adminLevel,
                        'filtro' => "{$tag}={$value}",
                        'error' => $e->getMessage(),
                    ]);
                    HarvestHeartbeat::touch("overpass:fail:{$areaName}:{$tag}={$value}");

                    continue;
                }

                $nuevosEnEsteCorte = 0;

                foreach ($elements as $element) {
                    $tags = $element['tags'] ?? [];

                    if (empty($tags['name'])) {
                        continue;
                    }

                    $placeId = $element['type'].'/'.$element['id'];

                    if (isset($seen[$placeId])) {
                        continue;
                    }

                    $seen[$placeId] = true;
                    $nuevosEnEsteCorte++;
                    $acumulado++;

                    yield [
                        'place_id' => $placeId,
                        'name' => $tags['name'],
                        'website' => $tags['website'] ?? $tags['contact:website'] ?? null,
                        'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                        'email' => $tags['email'] ?? $tags['contact:email'] ?? null,
                        'address' => $this->buildAddress($tags),
                        'segmento' => $segmento,
                    ];
                }

                Log::info('Overpass resultados', [
                    'area' => $areaName,
                    'admin_level' => $adminLevel,
                    'grupo' => $filtersKey,
                    'segmento' => $segmento,
                    'filtro' => "{$tag}={$value}",
                    'elementos' => count($elements),
                    'nuevos' => $nuevosEnEsteCorte,
                    'acumulado' => $acumulado,
                ]);

                HarvestHeartbeat::touch("overpass:{$areaName}:{$tag}={$value}");
            }
        }

        if ($consultas > 0 && $fallos === $consultas) {
            throw new \RuntimeException(
                "Overpass falló en los {$consultas} filtros de [{$filtersKey}]; no se pudo cosechar el área."
            );
        }
    }

    /**
     * Pausa mínima entre peticiones (config overpass + harvest.overpass_delay).
     */
    public function requestPauseMs(): int
    {
        $fromOverpass = (int) ($this->config['request_pause_ms'] ?? 750);

        // En tests se fuerza 0 en el config del cliente: respetarlo.
        if ($fromOverpass === 0 && array_key_exists('request_pause_ms', $this->config)) {
            return 0;
        }

        $fromHarvest = (int) config('outreach.harvest.overpass_delay', $fromOverpass);

        return max($fromOverpass, $fromHarvest, 0);
    }

    /**
     * Microsegundos de espera ante rate-limit / gateway timeout (exponencial).
     */
    public static function backoffMicroseconds(int $attempt): int
    {
        $attempt = max(0, $attempt);
        $us = self::BACKOFF_BASE_US * (2 ** $attempt);

        return min($us, self::BACKOFF_MAX_US);
    }

    /**
     * @return list<array{name: string, admin_level: int|string}>
     */
    private function areas(): array
    {
        $areas = $this->config['areas'] ?? [];

        if ($areas !== []) {
            return array_values($areas);
        }

        $fallback = $this->config['area'] ?? 'Madrid';

        return [
            ['name' => $fallback, 'admin_level' => 8],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchElements(string $areaName, string $adminLevel, string $tag, string $value): array
    {
        $query = $this->buildQuery($areaName, $adminLevel, $tag, $value);
        $endpoints = $this->endpoints();
        $lastError = null;
        $attempt = 0;

        foreach ($endpoints as $endpoint) {
            try {
                // Overpass exige User-Agent. No enviar Accept: application/json (provoca 406).
                $response = Http::asForm()
                    ->withHeaders([
                        'User-Agent' => 'SilgoDevBot/1.0 (contacto@onez.es; +https://silgodev.es)',
                    ])
                    ->timeout($this->config['timeout'] + 30)
                    ->post($endpoint, ['data' => $query]);

                if (in_array($response->status(), [429, 504, 406, 503], true)) {
                    $waitUs = self::backoffMicroseconds($attempt);
                    Log::warning("Overpass {$endpoint} respondió {$response->status()} para {$areaName}/{$tag}={$value}; backoff ".($waitUs / 1_000_000).'s y espejo.');
                    $lastError = $response->body();
                    usleep($waitUs);
                    $attempt++;

                    continue;
                }

                $response->throw();

                return $response->json('elements', []);
            } catch (\Throwable $e) {
                $waitUs = self::backoffMicroseconds($attempt);
                $lastError = $e->getMessage();
                Log::warning("Overpass falló en {$endpoint} [{$areaName} {$tag}={$value}]: {$e->getMessage()}; backoff ".($waitUs / 1_000_000).'s');
                usleep($waitUs);
                $attempt++;
            }
        }

        if ($lastError !== null) {
            throw new \RuntimeException(
                "Overpass no disponible para {$areaName} [{$tag}={$value}]: {$lastError}"
            );
        }

        throw new \RuntimeException(
            "Overpass no disponible para {$areaName} [{$tag}={$value}]: sin respuesta de espejos"
        );
    }

    /**
     * @return list<string>
     */
    private function endpoints(): array
    {
        $list = $this->config['endpoints'] ?? [];

        if ($list === [] && ! empty($this->config['endpoint'])) {
            $list = [$this->config['endpoint']];
        }

        return array_values(array_unique($list));
    }

    private function buildQuery(string $areaName, string $adminLevel, string $tag, string $value): string
    {
        $area = addslashes($areaName);
        $level = addslashes($adminLevel);
        $timeout = $this->config['timeout'];

        return <<<QUERY
            [out:json][timeout:{$timeout}];
            area["name"="{$area}"]["admin_level"="{$level}"]->.a;
            (
              nwr(area.a)["{$tag}"="{$value}"];
            );
            out center tags;
            QUERY;
    }

    /**
     * @param  array<string, string>  $tags
     */
    private function buildAddress(array $tags): ?string
    {
        $street = trim(($tags['addr:street'] ?? '').' '.($tags['addr:housenumber'] ?? ''));

        $parts = array_filter([
            $street,
            $tags['addr:postcode'] ?? null,
            $tags['addr:city'] ?? null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
