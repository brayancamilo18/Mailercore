<?php

namespace App\Services\Web;

use App\DTO\EnlaceHtml;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Las rutas fijas fallan porque cada web nombra sus páginas a su manera
 * (/carta, /menu, /nuestra-carta, /es/carta…). Esta clase mira los enlaces de
 * la home y propone las rutas internas que más pintan de ser interesantes.
 */
class DescubridorRutas
{
    /** Tope de rutas propuestas por sitio. */
    private const MAX_RUTAS = 4;

    /** Coincidencia de una palabra clave en la ruta. */
    private const PUNTOS_RUTA = 3;

    /** Coincidencia de una palabra clave en el texto ancla. */
    private const PUNTOS_TEXTO = 2;

    private const EXTENSIONES_FICHERO = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'rtf', 'txt', 'csv',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'bmp', 'ico', 'tiff',
        'zip', 'rar', '7z', 'gz', 'tar', 'dmg', 'exe', 'apk', 'msi',
        'mp3', 'mp4', 'avi', 'mov', 'wmv', 'webm', 'ogg', 'wav',
        'css', 'js', 'json', 'xml', 'rss', 'woff', 'woff2', 'ttf', 'eot',
    ];

    private LectorEnlaces $lector;

    public function __construct(?LectorEnlaces $lector = null)
    {
        $this->lector = $lector ?? new LectorEnlaces;
    }

    /**
     * Rutas internas candidatas, ordenadas de más a menos relevante.
     *
     * @return list<string>
     */
    public function descubrir(string $htmlHome, string $urlBase, ?string $sector): array
    {
        $palabras = $this->palabrasClave($sector);

        if ($palabras === []) {
            return [];
        }

        try {
            $enlaces = $this->lector->leer(new Crawler($htmlHome, $urlBase), $urlBase);
        } catch (\Throwable) {
            return [];
        }

        $fijas = $this->rutasFijas($sector);
        /** @var array<string, int> ruta => mejor puntuación */
        $puntuadas = [];

        foreach ($enlaces as $enlace) {
            $ruta = $enlace->ruta;

            if (! $enlace->esInterno || $ruta === null || $ruta === '') {
                continue;
            }

            if (in_array($ruta, $fijas, true) || $this->esFichero($ruta)) {
                continue;
            }

            $puntos = $this->puntuar($enlace, $ruta, $palabras);

            if ($puntos <= 0) {
                continue;
            }

            // Si dos enlaces llevan a la misma ruta, se queda la mejor puntuación.
            $puntuadas[$ruta] = max($puntos, $puntuadas[$ruta] ?? 0);
        }

        // arsort es estable: a igual puntuación manda el orden del documento.
        arsort($puntuadas);

        return array_slice(array_keys($puntuadas), 0, self::MAX_RUTAS);
    }

    /**
     * @param  list<string>  $palabras
     */
    private function puntuar(EnlaceHtml $enlace, string $ruta, array $palabras): int
    {
        $rutaComparable = $this->comparable($ruta);
        $textoComparable = $this->comparable($enlace->texto);
        $puntos = 0;

        foreach ($palabras as $palabra) {
            if ($palabra === '') {
                continue;
            }

            if (str_contains($rutaComparable, $palabra)) {
                $puntos += self::PUNTOS_RUTA;
            }

            if ($textoComparable !== '' && str_contains($textoComparable, $palabra)) {
                $puntos += self::PUNTOS_TEXTO;
            }
        }

        return $puntos;
    }

    private function comparable(string $texto): string
    {
        return $this->lector->comparable($texto);
    }

    /**
     * Palabras del sector. Sin sector conocido no hay pistas, así que se usan
     * las de todas las familias.
     *
     * @return list<string>
     */
    private function palabrasClave(?string $sector): array
    {
        $porSector = config('outreach.scraper.palabras_clave_sector', []);

        if (! is_array($porSector)) {
            return [];
        }

        if ($sector !== null && is_array($porSector[$sector] ?? null)) {
            $palabras = $porSector[$sector];
        } else {
            $palabras = [];
            foreach ($porSector as $lista) {
                if (is_array($lista)) {
                    $palabras = array_merge($palabras, $lista);
                }
            }
        }

        $limpias = [];
        foreach ($palabras as $palabra) {
            if (! is_string($palabra)) {
                continue;
            }

            $comparable = $this->comparable($palabra);
            if ($comparable !== '' && ! in_array($comparable, $limpias, true)) {
                $limpias[] = $comparable;
            }
        }

        return $limpias;
    }

    /**
     * Rutas que el rastreador ya visita de serie para este sector.
     *
     * @return list<string>
     */
    private function rutasFijas(?string $sector): array
    {
        $comunes = config('outreach.scraper.rutas.auditoria_comunes', []);
        $comunes = is_array($comunes) ? $comunes : [];

        $deSector = $sector !== null
            ? config('outreach.scraper.rutas.auditoria_sector.'.$sector, [])
            : [];
        $deSector = is_array($deSector) ? $deSector : [];

        $fijas = [];
        foreach (array_merge($comunes, $deSector) as $ruta) {
            if (is_string($ruta)) {
                $fijas[] = $this->lector->normalizar($ruta);
            }
        }

        return $fijas;
    }

    private function esFichero(string $ruta): bool
    {
        $ultima = strtolower((string) pathinfo($ruta, PATHINFO_EXTENSION));

        return $ultima !== '' && in_array($ultima, self::EXTENSIONES_FICHERO, true);
    }
}
