<?php

namespace App\Services\Overpass;

use App\Excepciones\OverpassNoDisponible;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OverpassClient
{
    /**
     * @param  array<string, mixed>  $config  config('outreach.overpass') + opcionales
     */
    public function __construct(private array $config) {}

    /**
     * Emite candidatos filtro a filtro.
     *
     * @param  list<array{nombre: string, admin_level: int}>  $areas
     * @param  list<array{0: string, 1: string}>  $filtros
     * @return \Generator<int, array<string, mixed>>
     */
    public function buscarStream(array $areas, array $filtros): \Generator
    {
        $vistos = [];
        $consultas = 0;
        $fallos = 0;
        $primera = true;
        $pausaMs = (int) ($this->config['pausa_peticion_ms'] ?? 1500);
        $porConsulta = max(1, (int) ($this->config['max_filtros_por_consulta'] ?? 15));

        foreach ($areas as $area) {
            $nombre = $area['nombre'];
            $adminLevel = (int) $area['admin_level'];

            // Agrupamos los filtros en lotes y hacemos una consulta combinada por
            // lote (una unión de nwr en Overpass). Así pasamos de ~90 peticiones
            // por área a unas pocas: mucho más rápido y resistente a la saturación.
            foreach (array_chunk($filtros, $porConsulta) as $lote) {
                if (! $primera && $pausaMs > 0) {
                    usleep($pausaMs * 1000);
                }
                $primera = false;

                $consultas++;

                try {
                    $elementos = $this->fetchElementos(
                        $this->construirConsultaCombinada($nombre, $adminLevel, $lote)
                    );
                } catch (\Throwable $e) {
                    $fallos++;
                    Log::channel('outreach')->warning('Fallo Overpass en lote', [
                        'area' => $nombre,
                        'filtros' => count($lote),
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                foreach ($elementos as $elemento) {
                    $tags = $elemento['tags'] ?? [];
                    if (! is_array($tags)) {
                        continue;
                    }

                    $nombreNegocio = trim((string) ($tags['name'] ?? ''));
                    if ($nombreNegocio === '') {
                        continue;
                    }

                    $tipo = (string) ($elemento['type'] ?? 'node');
                    $id = (string) ($elemento['id'] ?? '');
                    $placeId = $tipo.'/'.$id;

                    if (isset($vistos[$placeId])) {
                        continue;
                    }
                    $vistos[$placeId] = true;

                    [$tag, $valor] = $this->filtroCoincidente($tags, $lote);

                    yield [
                        'place_id' => $placeId,
                        'nombre' => $nombreNegocio,
                        'website' => $tags['website'] ?? $tags['contact:website'] ?? null,
                        'telefono' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                        'email' => $tags['email'] ?? $tags['contact:email'] ?? null,
                        'direccion' => $this->construirDireccion($tags),
                        'ciudad' => $tags['addr:city'] ?? null,
                        'codigo_postal' => $tags['addr:postcode'] ?? null,
                        'latitud' => $elemento['lat'] ?? $elemento['center']['lat'] ?? null,
                        'longitud' => $elemento['lon'] ?? $elemento['center']['lon'] ?? null,
                        'osm_tag' => $tag,
                        'osm_valor' => $valor,
                        'osm_tags_raw' => $tags,
                    ];
                }
            }
        }

        if ($consultas > 0 && $fallos === $consultas) {
            throw new OverpassNoDisponible('Ningún espejo respondió');
        }
    }

    /**
     * Devuelve el primer par [tag, valor] del lote que coincide con los tags del
     * elemento, para poder clasificar el sector. Si ninguno coincide (raro),
     * devuelve [null, null].
     *
     * @param  array<string, string>  $tags
     * @param  list<array{0: string, 1: string}>  $lote
     * @return array{0: ?string, 1: ?string}
     */
    private function filtroCoincidente(array $tags, array $lote): array
    {
        foreach ($lote as [$tag, $valor]) {
            if (isset($tags[$tag]) && $tags[$tag] === $valor) {
                return [$tag, $valor];
            }
        }

        return [null, null];
    }

    /**
     * Recupera los tags de una lista de place_id ya conocidos.
     *
     * @param  list<string>  $placeIds  formato "node/123", "way/456"
     * @return array<string, array<string, mixed>> place_id => tags (+ _lat/_lon)
     */
    public function buscarPorIds(array $placeIds): array
    {
        $maxLote = max(1, (int) ($this->config['max_ids_por_lote'] ?? 300));
        $resultado = [];

        foreach (array_chunk(array_values($placeIds), $maxLote) as $lote) {
            $porTipo = ['node' => [], 'way' => [], 'relation' => []];

            foreach ($lote as $placeId) {
                $partes = explode('/', (string) $placeId, 2);
                if (count($partes) !== 2) {
                    continue;
                }
                [$tipo, $id] = $partes;
                if (! isset($porTipo[$tipo])) {
                    continue;
                }
                $porTipo[$tipo][] = $id;
            }

            $lineas = ['[out:json][timeout:180];'];
            foreach (['node', 'way', 'relation'] as $tipo) {
                if ($porTipo[$tipo] === []) {
                    continue;
                }
                $lineas[] = $tipo.'(id:'.implode(',', $porTipo[$tipo]).');out center tags;';
            }

            if (count($lineas) === 1) {
                continue;
            }

            $elementos = $this->fetchElementos(implode("\n", $lineas));

            foreach ($elementos as $elemento) {
                $tags = $elemento['tags'] ?? [];
                if (! is_array($tags)) {
                    $tags = [];
                }

                $lat = $elemento['lat'] ?? $elemento['center']['lat'] ?? null;
                $lon = $elemento['lon'] ?? $elemento['center']['lon'] ?? null;
                if ($lat !== null) {
                    $tags['_lat'] = $lat;
                }
                if ($lon !== null) {
                    $tags['_lon'] = $lon;
                }

                $tipo = (string) ($elemento['type'] ?? 'node');
                $id = (string) ($elemento['id'] ?? '');
                $resultado[$tipo.'/'.$id] = $tags;
            }
        }

        return $resultado;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchElementos(string $consulta): array
    {
        foreach ($this->endpoints() as $indice => $endpoint) {
            try {
                $respuesta = Http::asForm()
                    ->withHeaders(['User-Agent' => $this->config['user_agent']])
                    ->timeout(((int) $this->config['timeout']) + 30)
                    ->post($endpoint, ['data' => $consulta]);

                if (in_array($respuesta->status(), [429, 503, 504, 406], true)) {
                    usleep(self::backoffMicrosegundos($indice));

                    continue;
                }

                $respuesta->throw();

                return $respuesta->json('elements', []) ?? [];
            } catch (\Throwable $e) {
                usleep(self::backoffMicrosegundos($indice));
            }
        }

        throw new OverpassNoDisponible('Ningún espejo respondió');
    }

    public static function backoffMicrosegundos(int $intento): int
    {
        $us = 1_000_000 * (2 ** max(0, $intento));

        return min($us, 16_000_000);
    }

    private function construirConsulta(string $area, int $adminLevel, string $tag, string $valor): string
    {
        return $this->construirConsultaCombinada($area, $adminLevel, [[$tag, $valor]]);
    }

    /**
     * Construye una consulta con la unión de varios filtros sobre la misma área.
     * El área solo se resuelve una vez, lo que reduce mucho el coste en Overpass.
     *
     * @param  list<array{0: string, 1: string}>  $filtros
     */
    private function construirConsultaCombinada(string $area, int $adminLevel, array $filtros): string
    {
        $areaEscapada = addslashes($area);
        $timeout = $this->config['timeout'];

        $lineas = [];
        foreach ($filtros as [$tag, $valor]) {
            $tagEsc = addslashes($tag);
            $valorEsc = addslashes($valor);
            $lineas[] = "  nwr(area.a)[\"{$tagEsc}\"=\"{$valorEsc}\"];";
        }
        $union = implode("\n", $lineas);

        return <<<CONSULTA
[out:json][timeout:{$timeout}];
area["name"="{$areaEscapada}"]["admin_level"="{$adminLevel}"]->.a;
(
{$union}
);
out center tags;
CONSULTA;
    }

    /**
     * @param  array<string, string>  $tags
     */
    private function construirDireccion(array $tags): ?string
    {
        $calleNumero = trim(implode(' ', array_filter([
            $tags['addr:street'] ?? null,
            $tags['addr:housenumber'] ?? null,
        ])));

        $partes = array_filter([
            $calleNumero !== '' ? $calleNumero : null,
            $tags['addr:postcode'] ?? null,
            $tags['addr:city'] ?? null,
        ], fn ($p) => is_string($p) && trim($p) !== '');

        if ($partes === []) {
            return null;
        }

        return implode(', ', $partes);
    }

    /**
     * @return list<string>
     */
    private function endpoints(): array
    {
        return array_values($this->config['endpoints'] ?? []);
    }
}
