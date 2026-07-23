<?php

namespace App\Services\Web;

use App\DTO\MetadatosPagina;
use Symfony\Component\DomCrawler\Crawler;

class ExtractorMetadatos
{
    public function extraer(
        string $html,
        string $url,
        ?int $httpStatus = 200,
        ?int $respuestaMs = null,
        ?string $contentType = null,
        ?string $redirigidaA = null,
    ): MetadatosPagina {
        $maxBytes = (int) config('outreach.scraper.max_bytes_html');
        if (strlen($html) > $maxBytes) {
            $html = substr($html, 0, $maxBytes);
        }

        $htmlDecodificado = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $crawler = new Crawler($html, $url);
        $textoPlano = $this->seguro(fn () => $crawler->text('')) ?? '';
        $textoNormalizado = $this->sinTildes($textoPlano.' '.$htmlDecodificado);

        $title = $this->seguro(fn () => $this->title($crawler));
        $metaDescription = $this->seguro(fn () => $this->metaDescription($crawler));
        $h1 = $this->seguro(fn () => $this->h1($crawler)) ?? ['texto' => null, 'total' => null];
        $h2Total = $this->seguro(fn () => $this->h2Total($crawler));
        $idioma = $this->seguro(fn () => $this->idioma($crawler));
        $canonical = $this->seguro(fn () => $this->canonical($crawler));
        $charset = $this->seguro(fn () => $this->charset($crawler));
        $generador = $this->seguro(fn () => $this->generador($crawler, $html));
        $tieneViewport = $this->seguro(fn () => $this->tieneViewport($crawler));
        $tieneFavicon = $this->seguro(fn () => $this->tieneFavicon($crawler));
        $tieneOg = $this->seguro(fn () => $this->tieneOg($crawler));

        $jsonld = $this->seguro(fn () => $this->jsonld($crawler)) ?? ['tiene' => false, 'tipos' => []];
        $imagenes = $this->seguro(fn () => $this->imagenes($crawler)) ?? ['total' => null, 'sin_alt' => null];
        $enlaces = $this->seguro(fn () => $this->enlaces($crawler, $url)) ?? ['internos' => null, 'externos' => null];
        $redes = $this->seguro(fn () => $this->redesSociales($crawler));
        $telefonos = $this->seguro(fn () => $this->telefonos($htmlDecodificado, $crawler));
        $emails = $this->seguro(fn () => $this->emails($htmlDecodificado));
        $tieneFormulario = $this->seguro(fn () => $this->tieneFormulario($crawler));
        $tieneWhatsapp = $this->seguro(fn () => $this->tieneWhatsapp($crawler));
        $tieneReservas = $this->seguro(fn () => $this->tieneReservas($crawler, $textoNormalizado));
        $tieneCarrito = $this->seguro(fn () => $this->tieneCarrito($crawler, $textoNormalizado, $generador));
        $legales = $this->seguro(fn () => $this->enlacesLegales($crawler)) ?? [
            'aviso_legal' => null, 'privacidad' => null, 'cookies' => null,
        ];
        $anioCopyright = $this->seguro(fn () => $this->anioCopyright($textoPlano.' '.$htmlDecodificado));

        $ruta = parse_url($url, PHP_URL_PATH);
        $ruta = is_string($ruta) ? $ruta : null;

        return new MetadatosPagina(
            url: $url,
            ruta: $ruta,
            httpStatus: $httpStatus,
            contentType: $contentType,
            bytes: strlen($html),
            respuestaMs: $respuestaMs,
            redirigidaA: $redirigidaA,
            title: $title,
            titleLongitud: $title !== null ? mb_strlen($title) : null,
            metaDescription: $metaDescription,
            metaDescLongitud: $metaDescription !== null ? mb_strlen($metaDescription) : null,
            h1Texto: $h1['texto'] ?? null,
            h1Total: $h1['total'] ?? null,
            h2Total: $h2Total,
            idioma: $idioma,
            canonical: $canonical,
            generador: $generador,
            charset: $charset,
            tieneViewport: $tieneViewport,
            tieneFavicon: $tieneFavicon,
            tieneOg: $tieneOg,
            tieneJsonld: $jsonld['tiene'] ?? false,
            jsonldTipos: $jsonld['tipos'] ?? [],
            imagenesTotal: $imagenes['total'] ?? null,
            imagenesSinAlt: $imagenes['sin_alt'] ?? null,
            enlacesInternos: $enlaces['internos'] ?? null,
            enlacesExternos: $enlaces['externos'] ?? null,
            redesSociales: $redes,
            telefonos: $telefonos,
            emailsEncontrados: $emails,
            tieneFormulario: $tieneFormulario,
            tieneWhatsapp: $tieneWhatsapp,
            tieneReservas: $tieneReservas,
            tieneCarrito: $tieneCarrito,
            tieneAvisoLegal: $legales['aviso_legal'] ?? null,
            tienePrivacidad: $legales['privacidad'] ?? null,
            tieneCookies: $legales['cookies'] ?? null,
            anioCopyright: $anioCopyright,
            htmlHash: $this->hash($html),
            error: null,
            capturadaAt: now(),
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T|null
     */
    private function seguro(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return null;
        }
    }

    private function sinTildes(string $texto): string
    {
        return strtr(mb_strtolower($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private function title(Crawler $c): ?string
    {
        $nodo = $c->filter('title');
        if ($nodo->count() === 0) {
            return null;
        }

        $texto = trim($nodo->first()->text());
        if ($texto === '') {
            return null;
        }

        return mb_strlen($texto) > 500 ? mb_substr($texto, 0, 500) : $texto;
    }

    private function metaDescription(Crawler $c): ?string
    {
        $nodo = $c->filter('meta')->reduce(function (Crawler $node): bool {
            return strcasecmp((string) $node->attr('name'), 'description') === 0;
        });

        if ($nodo->count() === 0) {
            return null;
        }

        $content = trim((string) $nodo->first()->attr('content'));

        return $content !== '' ? $content : null;
    }

    /**
     * @return array{texto: ?string, total: int}
     */
    private function h1(Crawler $c): array
    {
        $nodos = $c->filter('h1');
        $total = $nodos->count();
        $texto = null;

        if ($total > 0) {
            $texto = trim($nodos->first()->text());
            $texto = $texto !== '' ? $texto : null;
        }

        return ['texto' => $texto, 'total' => $total];
    }

    private function h2Total(Crawler $c): int
    {
        return $c->filter('h2')->count();
    }

    private function idioma(Crawler $c): ?string
    {
        $nodo = $c->filter('html');
        if ($nodo->count() === 0) {
            return null;
        }

        $lang = trim((string) $nodo->first()->attr('lang'));
        if ($lang === '') {
            return null;
        }

        return mb_substr($lang, 0, 10);
    }

    private function canonical(Crawler $c): ?string
    {
        $nodo = $c->filter('link')->reduce(function (Crawler $node): bool {
            return strcasecmp((string) $node->attr('rel'), 'canonical') === 0;
        });

        if ($nodo->count() === 0) {
            return null;
        }

        $href = trim((string) $nodo->first()->attr('href'));

        return $href !== '' ? $href : null;
    }

    private function charset(Crawler $c): ?string
    {
        $nodo = $c->filter('meta[charset]');
        if ($nodo->count() > 0) {
            $charset = trim((string) $nodo->first()->attr('charset'));

            return $charset !== '' ? mb_substr($charset, 0, 30) : null;
        }

        $nodo = $c->filter('meta')->reduce(function (Crawler $node): bool {
            return strcasecmp((string) $node->attr('http-equiv'), 'Content-Type') === 0;
        });

        if ($nodo->count() === 0) {
            return null;
        }

        $content = (string) $nodo->first()->attr('content');
        if (preg_match('/charset\s*=\s*([^\s;]+)/i', $content, $m) === 1) {
            return mb_substr(trim($m[1]), 0, 30);
        }

        return null;
    }

    private function generador(Crawler $c, string $html): ?string
    {
        $nodo = $c->filter('meta')->reduce(function (Crawler $node): bool {
            return strcasecmp((string) $node->attr('name'), 'generator') === 0;
        });

        if ($nodo->count() > 0) {
            $content = strtolower((string) $nodo->first()->attr('content'));
            $conocidos = [
                'wix', 'wordpress', 'squarespace', 'joomla', 'prestashop',
                'shopify', 'drupal', 'webflow', 'elementor',
            ];

            foreach ($conocidos as $nombre) {
                if (str_contains($content, $nombre)) {
                    return $nombre;
                }
            }

            if (str_contains($content, 'wp-')) {
                return 'wordpress';
            }

            return 'otro';
        }

        $htmlLower = strtolower($html);

        if (str_contains($htmlLower, '/wp-content/')) {
            return 'wordpress';
        }
        if (str_contains($htmlLower, 'cdn.shopify.com')) {
            return 'shopify';
        }
        if (str_contains($htmlLower, 'static.parastorage.com')) {
            return 'wix';
        }
        if (str_contains($htmlLower, 'prestashop')) {
            return 'prestashop';
        }

        return null;
    }

    private function tieneViewport(Crawler $c): bool
    {
        $nodo = $c->filter('meta')->reduce(function (Crawler $node): bool {
            return strcasecmp((string) $node->attr('name'), 'viewport') === 0;
        });

        if ($nodo->count() === 0) {
            return false;
        }

        $content = strtolower((string) $nodo->first()->attr('content'));

        return str_contains($content, 'width=device-width');
    }

    private function tieneFavicon(Crawler $c): bool
    {
        $nodo = $c->filter('link')->reduce(function (Crawler $node): bool {
            return str_contains(strtolower((string) $node->attr('rel')), 'icon');
        });

        return $nodo->count() > 0;
    }

    private function tieneOg(Crawler $c): bool
    {
        $nodo = $c->filter('meta')->reduce(function (Crawler $node): bool {
            $property = strtolower((string) $node->attr('property'));

            return $property === 'og:title' || $property === 'og:image';
        });

        return $nodo->count() > 0;
    }

    /**
     * @return array{tiene: bool, tipos: list<string>}
     */
    private function jsonld(Crawler $c): array
    {
        $tipos = [];
        $nodos = $c->filter('script[type="application/ld+json"]');

        foreach ($nodos as $nodo) {
            $texto = trim($nodo->textContent ?? '');
            $datos = json_decode($texto, true);

            if (! is_array($datos)) {
                continue;
            }

            $this->recorrerJsonld($datos, $tipos);
        }

        $tipos = array_values(array_unique($tipos));

        return [
            'tiene' => $tipos !== [],
            'tipos' => $tipos,
        ];
    }

    /**
     * @param  array<mixed>  $nodo
     * @param  list<string>  $tipos
     */
    private function recorrerJsonld(array $nodo, array &$tipos): void
    {
        if (isset($nodo['@type'])) {
            foreach ((array) $nodo['@type'] as $tipo) {
                if (is_string($tipo) && $tipo !== '') {
                    $tipos[] = $tipo;
                }
            }
        }

        foreach ($nodo as $valor) {
            if (is_array($valor)) {
                $this->recorrerJsonld($valor, $tipos);
            }
        }
    }

    /**
     * @return array{total: int, sin_alt: int}
     */
    private function imagenes(Crawler $c): array
    {
        $nodos = $c->filter('img');
        $total = $nodos->count();
        $sinAlt = 0;

        foreach ($nodos as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            if (! $nodo->hasAttribute('alt') || trim($nodo->getAttribute('alt')) === '') {
                $sinAlt++;
            }
        }

        return ['total' => $total, 'sin_alt' => $sinAlt];
    }

    /**
     * @return array{internos: int, externos: int}
     */
    private function enlaces(Crawler $c, string $url): array
    {
        $hostPropio = strtolower((string) parse_url($url, PHP_URL_HOST));
        $internos = 0;
        $externos = 0;

        foreach ($c->filter('a') as $nodo) {
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
                $internos++;

                continue;
            }

            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            if ($host === $hostPropio) {
                $internos++;
            } else {
                $externos++;
            }
        }

        return ['internos' => $internos, 'externos' => $externos];
    }

    /**
     * @return array<string, string>
     */
    private function redesSociales(Crawler $c): array
    {
        $redes = [
            'instagram' => 'instagram.com',
            'facebook' => 'facebook.com',
            'twitter' => 'twitter.com',
            'x' => 'x.com',
            'linkedin' => 'linkedin.com',
            'tiktok' => 'tiktok.com',
            'youtube' => 'youtube.com',
        ];

        $encontradas = [];

        foreach ($c->filter('a') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $href = trim($nodo->getAttribute('href'));
            if ($href === '' || ! preg_match('#^https?://#i', $href)) {
                continue;
            }

            $host = strtolower((string) parse_url($href, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            $path = trim((string) parse_url($href, PHP_URL_PATH), '/');

            foreach ($redes as $clave => $dominio) {
                if (isset($encontradas[$clave])) {
                    continue;
                }

                if ($host !== $dominio && ! str_ends_with($host, '.'.$dominio)) {
                    continue;
                }

                if ($path === '') {
                    continue;
                }

                $encontradas[$clave] = $href;
            }
        }

        return $encontradas;
    }

    /**
     * @return list<string>
     */
    private function telefonos(string $htmlDecodificado, Crawler $c): array
    {
        $telefonos = [];

        foreach ($c->filter('a[href^="tel:"], a[href^="TEL:"]') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $href = $nodo->getAttribute('href');
            $numero = preg_replace('/^tel:/i', '', $href) ?? '';
            $numero = preg_replace('/[\s.\-]+/', '', $numero) ?? '';
            if ($numero !== '') {
                $telefonos[] = $numero;
            }
        }

        if (preg_match_all('/(?:\+?34[\s.-]?)?[679]\d{2}[\s.-]?\d{3}[\s.-]?\d{3}/', $htmlDecodificado, $m) > 0) {
            foreach ($m[0] as $crudo) {
                $limpio = preg_replace('/[\s.\-]+/', '', $crudo) ?? '';
                if ($limpio !== '') {
                    $telefonos[] = $limpio;
                }
            }
        }

        $unicos = [];
        foreach ($telefonos as $telefono) {
            if (! in_array($telefono, $unicos, true)) {
                $unicos[] = $telefono;
            }
            if (count($unicos) >= 5) {
                break;
            }
        }

        return $unicos;
    }

    /**
     * @return list<string>
     */
    private function emails(string $htmlDecodificado): array
    {
        $emails = [];

        if (preg_match_all('/mailto:([^"\'\s>?]+)/i', $htmlDecodificado, $m) > 0) {
            foreach ($m[1] as $crudo) {
                $email = strtolower(urldecode($crudo));
                $email = explode('?', $email, 2)[0];
                if ($email !== '') {
                    $emails[] = $email;
                }
            }
        }

        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $htmlDecodificado, $m) > 0) {
            foreach ($m[0] as $email) {
                $emails[] = strtolower($email);
            }
        }

        return array_values(array_unique($emails));
    }

    private function tieneFormulario(Crawler $c): bool
    {
        foreach ($c->filter('form') as $formDom) {
            $form = new Crawler($formDom);

            if ($form->filter('textarea')->count() > 0) {
                return true;
            }

            foreach ($form->filter('input') as $input) {
                if (! $input instanceof \DOMElement) {
                    continue;
                }

                $name = strtolower((string) $input->getAttribute('name'));
                $type = strtolower((string) $input->getAttribute('type'));

                if ($type === 'email' || str_contains($name, 'mail')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function tieneWhatsapp(Crawler $c): bool
    {
        foreach ($c->filter('a') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $href = strtolower($nodo->getAttribute('href'));
            if (str_contains($href, 'wa.me') || str_contains($href, 'api.whatsapp.com')) {
                return true;
            }
        }

        return false;
    }

    private function tieneReservas(Crawler $c, string $texto): bool
    {
        $patrones = [
            'reserva', 'reservar', 'booking', 'cita previa', 'pedir cita', 'book now',
            'thefork', 'covermanager', 'resurva', 'mesa 24/7', 'doctoralia', 'bookitit',
        ];

        foreach ($patrones as $patron) {
            if (str_contains($texto, $patron)) {
                return true;
            }
        }

        foreach ($c->filter('a') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $href = $this->sinTildes($nodo->getAttribute('href'));
            $linkTexto = $this->sinTildes($nodo->textContent ?? '');
            foreach ($patrones as $patron) {
                if (str_contains($href, $patron) || str_contains($linkTexto, $patron)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function tieneCarrito(Crawler $c, string $texto, ?string $generador): bool
    {
        if (in_array($generador, ['shopify', 'prestashop'], true)) {
            return true;
        }

        $patrones = [
            'carrito', 'cesta', 'add to cart', 'anadir al carrito',
            'checkout', 'finalizar compra', 'mi pedido',
        ];

        foreach ($patrones as $patron) {
            if (str_contains($texto, $patron)) {
                return true;
            }
        }

        foreach ($c->filter('a') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $href = $this->sinTildes($nodo->getAttribute('href'));
            $linkTexto = $this->sinTildes($nodo->textContent ?? '');
            foreach ($patrones as $patron) {
                if (str_contains($href, $patron) || str_contains($linkTexto, $patron)) {
                    return true;
                }
            }
        }

        foreach ($c->filter('[class]') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $clase = strtolower($nodo->getAttribute('class'));
            if (str_contains($clase, 'cart') || str_contains($clase, 'basket')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{aviso_legal: bool, privacidad: bool, cookies: bool}
     */
    private function enlacesLegales(Crawler $c): array
    {
        $resultado = [
            'aviso_legal' => false,
            'privacidad' => false,
            'cookies' => false,
        ];

        foreach ($c->filter('a') as $nodo) {
            if (! $nodo instanceof \DOMElement) {
                continue;
            }

            $haystack = $this->sinTildes(($nodo->textContent ?? '').' '.($nodo->getAttribute('href') ?? ''));

            if (
                str_contains($haystack, 'aviso legal')
                || str_contains($haystack, 'legal notice')
                || str_contains($haystack, 'aviso-legal')
            ) {
                $resultado['aviso_legal'] = true;
            }

            if (
                str_contains($haystack, 'privacidad')
                || str_contains($haystack, 'privacy')
                || str_contains($haystack, 'proteccion de datos')
            ) {
                $resultado['privacidad'] = true;
            }

            if (str_contains($haystack, 'cookies')) {
                $resultado['cookies'] = true;
            }
        }

        return $resultado;
    }

    private function anioCopyright(string $texto): ?int
    {
        $anios = [];
        $actual = (int) date('Y');

        if (preg_match_all('/(?:©|&copy;|copyright)[^0-9]{0,20}(\d{4})/iu', $texto, $m) > 0) {
            foreach ($m[1] as $anio) {
                $anios[] = (int) $anio;
            }
        }

        if (preg_match_all('/(\d{4})[^0-9]{0,20}(?:©|&copy;)/u', $texto, $m) > 0) {
            foreach ($m[1] as $anio) {
                $anios[] = (int) $anio;
            }
        }

        $validos = array_filter($anios, fn (int $a): bool => $a >= 1995 && $a <= $actual);

        return $validos === [] ? null : max($validos);
    }

    private function hash(string $html): string
    {
        return sha1(preg_replace('/\s+/', ' ', $html) ?? $html);
    }
}
