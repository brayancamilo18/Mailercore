<?php

namespace App\Services\Clasificacion;

use App\DTO\ResultadoClasificacion;
use App\Models\Lead;
use App\Models\Suppression;

class ClasificadorSector
{
    /** @var array<string, list<string>>|null */
    private static ?array $palabrasCache = null;

    public function __construct(private CatalogoSectores $catalogo) {}

    public function clasificar(Lead $lead): ResultadoClasificacion
    {
        // 1. Filtro OSM que lo encontró (confianza 100)
        if ($lead->osm_tag && $lead->osm_valor) {
            $familia = $this->catalogo->porTag($lead->osm_tag, $lead->osm_valor);
            if ($familia !== null) {
                return new ResultadoClasificacion(
                    $familia,
                    $this->catalogo->subsector($lead->osm_tag, $lead->osm_valor),
                    'osm_filtro',
                    100,
                );
            }
        }

        // 2. Todos los tags de OSM (confianza 95)
        if (is_array($lead->osm_tags_raw) && $lead->osm_tags_raw !== []) {
            $familia = $this->catalogo->porTagsRaw($lead->osm_tags_raw);
            if ($familia !== null) {
                $subsector = null;
                foreach ($lead->osm_tags_raw as $tag => $valor) {
                    if ($this->catalogo->porTag($tag, $valor) === $familia) {
                        $subsector = $this->catalogo->subsector($tag, $valor);
                        break;
                    }
                }

                return new ResultadoClasificacion($familia, $subsector, 'osm_tags', 95);
            }
        }

        // 3. JSON-LD de sus páginas (confianza 80)
        $lead->loadMissing('paginas');
        $tipos = $lead->paginas
            ->pluck('jsonld_tipos')
            ->filter()
            ->flatten()
            ->unique()
            ->values()
            ->all();

        if ($tipos !== []) {
            $familia = $this->catalogo->porTiposSchema($tipos);
            if ($familia !== null) {
                return new ResultadoClasificacion($familia, null, 'schema', 80);
            }
        }

        // 4. Heurística sobre title + meta_description + h1 de la home (confianza 55)
        $porHeuristica = $this->porHeuristicaWeb($lead);
        if ($porHeuristica !== null) {
            return $porHeuristica;
        }

        // 5. Palabras del dominio (confianza 30)
        $porDominio = $this->porDominio($lead);
        if ($porDominio !== null) {
            return $porDominio;
        }

        // 6. Sin clasificar
        return new ResultadoClasificacion(null, null, 'sin_clasificar', 0);
    }

    public function aplicar(Lead $lead): ResultadoClasificacion
    {
        $resultado = $this->clasificar($lead);

        $lead->forceFill([
            'sector' => $resultado->sector,
            'subsector' => $resultado->subsector,
            'clasificacion_metodo' => $resultado->metodo,
            'clasificacion_confianza' => $resultado->confianza,
        ])->save();

        return $resultado;
    }

    private function porHeuristicaWeb(Lead $lead): ?ResultadoClasificacion
    {
        $home = $lead->homeCapturada();
        if ($home === null) {
            $lead->loadMissing('paginas');
            $home = $lead->paginas->first(fn ($p) => in_array($p->ruta, ['/', ''], true));
        }

        if ($home === null) {
            return null;
        }

        $texto = $this->normalizar(implode(' ', array_filter([
            $home->title,
            $home->meta_description,
            $home->h1_texto,
        ])));

        if ($texto === '') {
            return null;
        }

        $puntos = $this->contarCoincidencias($texto);
        if ($puntos === []) {
            return null;
        }

        arsort($puntos);
        $familias = array_keys($puntos);
        $valores = array_values($puntos);
        $primero = $valores[0];
        $segundo = $valores[1] ?? 0;

        if ($primero < 3) {
            return null;
        }

        if ($primero < ($segundo * 2)) {
            return null;
        }

        return new ResultadoClasificacion($familias[0], null, 'heuristica_web', 55);
    }

    private function porDominio(Lead $lead): ?ResultadoClasificacion
    {
        $dominio = $lead->website_dominio ?: Suppression::dominioDeWeb($lead->website);
        if ($dominio === null || $dominio === '') {
            return null;
        }

        $base = explode('.', $dominio)[0] ?? '';
        $texto = $this->normalizar(str_replace(['-', '_'], ' ', $base));

        if ($texto === '') {
            return null;
        }

        $puntos = $this->contarCoincidencias($texto);
        if ($puntos === []) {
            return null;
        }

        arsort($puntos);
        $familia = array_key_first($puntos);

        if (($puntos[$familia] ?? 0) < 1) {
            return null;
        }

        return new ResultadoClasificacion($familia, null, 'dominio', 30);
    }

    /**
     * @return array<string, int>
     */
    private function contarCoincidencias(string $textoNormalizado): array
    {
        $puntos = [];

        foreach ($this->palabras() as $familia => $lista) {
            $cuenta = 0;
            foreach ($lista as $palabra) {
                $needle = $this->normalizar($palabra);
                if ($needle !== '' && str_contains($textoNormalizado, $needle)) {
                    $cuenta++;
                }
            }
            if ($cuenta > 0) {
                $puntos[$familia] = $cuenta;
            }
        }

        return $puntos;
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto);
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * @return array<string, list<string>>
     */
    private function palabras(): array
    {
        return self::$palabrasCache ??= require resource_path('data/palabras_sector.php');
    }
}
