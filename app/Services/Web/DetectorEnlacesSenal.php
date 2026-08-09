<?php

namespace App\Services\Web;

use Symfony\Component\DomCrawler\Crawler;

/**
 * ¿Anuncia la home reservas, carrito, formulario o WhatsApp en alguno de sus
 * enlaces internos? El HTML no se guarda, así que esta foto se toma durante el
 * rastreo y se persiste en paginas.enlaces_senal.
 *
 * Sirve para callar, no para afirmar: si la home enlaza a reservas y nadie llegó
 * a abrir esa página, la auditoría no puede decir que no hay reservas.
 */
class DetectorEnlacesSenal
{
    private LectorEnlaces $lector;

    public function __construct(?LectorEnlaces $lector = null)
    {
        $this->lector = $lector ?? new LectorEnlaces;
    }

    /**
     * Una entrada por señal configurada. null si no hay palabras clave o el HTML
     * no se pudo recorrer: es distinto de «no enlaza a nada».
     *
     * @return array<string, bool>|null
     */
    public function detectar(string $html, string $urlBase): ?array
    {
        $porSenal = $this->palabrasClave();

        if ($porSenal === []) {
            return null;
        }

        try {
            $enlaces = $this->lector->leer(new Crawler($html, $urlBase), $urlBase);
        } catch (\Throwable) {
            return null;
        }

        $textos = [];
        foreach ($enlaces as $enlace) {
            if (! $enlace->esInterno) {
                continue;
            }

            $textos[] = $this->lector->comparable($enlace->href);

            if ($enlace->texto !== '') {
                $textos[] = $this->lector->comparable($enlace->texto);
            }
        }

        $senales = [];
        foreach ($porSenal as $senal => $palabras) {
            $senales[$senal] = $this->coincide($textos, $palabras);
        }

        return $senales;
    }

    /**
     * @param  list<string>  $textos
     * @param  list<string>  $palabras
     */
    private function coincide(array $textos, array $palabras): bool
    {
        foreach ($textos as $texto) {
            if ($texto === '') {
                continue;
            }

            foreach ($palabras as $palabra) {
                if (str_contains($texto, $palabra)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, list<string>>
     */
    private function palabrasClave(): array
    {
        $config = config('outreach.scraper.palabras_clave_senal', []);

        if (! is_array($config)) {
            return [];
        }

        $limpias = [];
        foreach ($config as $senal => $palabras) {
            if (! is_string($senal) || ! is_array($palabras)) {
                continue;
            }

            $lista = [];
            foreach ($palabras as $palabra) {
                if (! is_string($palabra)) {
                    continue;
                }

                $comparable = $this->lector->comparable($palabra);
                if ($comparable !== '' && ! in_array($comparable, $lista, true)) {
                    $lista[] = $comparable;
                }
            }

            if ($lista !== []) {
                $limpias[$senal] = $lista;
            }
        }

        return $limpias;
    }
}
