<?php

namespace App\Services\Web;

use App\DTO\EnlaceHtml;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Recorrido único de los <a> de un documento y resolución de rutas relativas.
 * Lo comparten ExtractorMetadatos, que solo cuenta internos y externos, y
 * DescubridorRutas, que los puntúa.
 */
class LectorEnlaces
{
    /**
     * Enlaces navegables del documento: se descartan los vacíos, las anclas y
     * los esquemas mailto:, tel: y javascript:.
     *
     * @return list<EnlaceHtml>
     */
    public function leer(Crawler $crawler, string $urlBase): array
    {
        $hostPropio = strtolower((string) parse_url($urlBase, PHP_URL_HOST));
        $enlaces = [];

        foreach ($crawler->filter('a') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $href = trim($nodo->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $hrefLower = strtolower($href);

            if (
                str_starts_with($hrefLower, '#')
                || str_starts_with($hrefLower, 'mailto:')
                || str_starts_with($hrefLower, 'tel:')
                || str_starts_with($hrefLower, 'javascript:')
            ) {
                continue;
            }

            if (str_starts_with($href, '/') || ! preg_match('#^https?://#i', $href)) {
                $esInterno = true;
            } else {
                $esInterno = strtolower((string) parse_url($href, PHP_URL_HOST)) === $hostPropio;
            }

            $texto = preg_replace('/\s+/', ' ', $nodo->textContent ?? '') ?? '';

            $enlaces[] = new EnlaceHtml(
                href: $href,
                texto: trim($texto),
                esInterno: $esInterno,
                ruta: $esInterno ? $this->rutaDe($href, $urlBase) : null,
            );
        }

        return $enlaces;
    }

    /**
     * Ruta normalizada de un href relativo o absoluto del mismo host.
     * Devuelve null si apunta fuera del sitio o no es una ruta usable.
     */
    public function rutaDe(string $href, string $urlBase): ?string
    {
        $href = trim($href);

        // La query y el fragmento no participan en la ruta.
        $href = preg_replace('/[#?].*$/', '', $href) ?? $href;

        if ($href === '') {
            return null;
        }

        $esquemaBase = strtolower((string) (parse_url($urlBase, PHP_URL_SCHEME) ?: 'https'));
        $hostBase = strtolower((string) parse_url($urlBase, PHP_URL_HOST));

        if (str_starts_with($href, '//')) {
            $href = $esquemaBase.':'.$href;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            if (strtolower((string) parse_url($href, PHP_URL_HOST)) !== $hostBase) {
                return null;
            }

            return $this->normalizar((string) (parse_url($href, PHP_URL_PATH) ?? ''));
        }

        // Cualquier otro esquema (ftp:, data:, whatsapp:…) no es una ruta.
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $href) === 1) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            return $this->normalizar($href);
        }

        return $this->normalizar($this->resolverPuntos($this->directorioDe($urlBase).'/'.$href));
    }

    /**
     * Colapsa barras, quita la barra final y pasa a minúsculas.
     * La home queda como cadena vacía.
     */
    public function normalizar(string $ruta): string
    {
        $ruta = preg_replace('/[#?].*$/', '', $ruta) ?? $ruta;
        $ruta = preg_replace('#/+#', '/', $ruta) ?? $ruta;
        $ruta = mb_strtolower(rtrim($ruta, '/'));

        if ($ruta !== '' && ! str_starts_with($ruta, '/')) {
            $ruta = '/'.$ruta;
        }

        return $ruta;
    }

    /**
     * Forma en la que se comparan rutas, textos ancla y palabras clave:
     * minúsculas, sin tildes y con los separadores convertidos en espacios, para
     * que «/pedir-cita» case con «pedir cita» y «wa.me» con «wa me».
     */
    public function comparable(string $texto): string
    {
        $texto = strtr(mb_strtolower(trim($texto)), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $texto = preg_replace('#[/_.\-]+#', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function directorioDe(string $urlBase): string
    {
        $ruta = (string) (parse_url($urlBase, PHP_URL_PATH) ?? '');

        if ($ruta === '' || str_ends_with($ruta, '/')) {
            return rtrim($ruta, '/');
        }

        $ultima = strrpos($ruta, '/');

        return $ultima === false ? '' : rtrim(substr($ruta, 0, $ultima), '/');
    }

    private function resolverPuntos(string $ruta): string
    {
        $segmentos = [];

        foreach (explode('/', $ruta) as $segmento) {
            if ($segmento === '' || $segmento === '.') {
                continue;
            }

            if ($segmento === '..') {
                array_pop($segmentos);

                continue;
            }

            $segmentos[] = $segmento;
        }

        return '/'.implode('/', $segmentos);
    }
}
