<?php

namespace App\DTO;

readonly class MetadatosPagina
{
    /**
     * @param  list<string>|null  $jsonldTipos
     * @param  array<string, string>|null  $redesSociales
     * @param  list<string>|null  $telefonos
     * @param  list<string>|null  $emailsEncontrados
     */
    public function __construct(
        public string $url,
        public ?string $ruta,
        public ?int $httpStatus,
        public ?string $contentType,
        public ?int $bytes,
        public ?int $respuestaMs,
        public ?string $redirigidaA,
        public ?string $title,
        public ?int $titleLongitud,
        public ?string $metaDescription,
        public ?int $metaDescLongitud,
        public ?string $h1Texto,
        public ?int $h1Total,
        public ?int $h2Total,
        public ?string $idioma,
        public ?string $canonical,
        public ?string $generador,
        public ?string $charset,
        public ?bool $tieneViewport,
        public ?bool $tieneFavicon,
        public ?bool $tieneOg,
        public ?bool $tieneJsonld,
        public ?array $jsonldTipos,
        public ?int $imagenesTotal,
        public ?int $imagenesSinAlt,
        public ?int $enlacesInternos,
        public ?int $enlacesExternos,
        public ?array $redesSociales,
        public ?array $telefonos,
        public ?array $emailsEncontrados,
        public ?bool $tieneFormulario,
        public ?bool $tieneWhatsapp,
        public ?bool $tieneReservas,
        public ?bool $tieneCarrito,
        public ?bool $tieneAvisoLegal,
        public ?bool $tienePrivacidad,
        public ?bool $tieneCookies,
        public ?int $anioCopyright,
        public ?string $htmlHash,
        public ?string $error,
        public \DateTimeInterface $capturadaAt,
    ) {}

    /**
     * @return array<string, mixed> con las claves EXACTAS de la tabla paginas
     */
    public function aArrayBd(): array
    {
        return [
            'url' => $this->url,
            'ruta' => $this->ruta,
            'http_status' => $this->httpStatus,
            'content_type' => $this->contentType,
            'bytes' => $this->bytes,
            'respuesta_ms' => $this->respuestaMs,
            'redirigida_a' => $this->redirigidaA,
            'title' => $this->title,
            'title_longitud' => $this->titleLongitud,
            'meta_description' => $this->metaDescription,
            'meta_desc_longitud' => $this->metaDescLongitud,
            'h1_texto' => $this->h1Texto,
            'h1_total' => $this->h1Total,
            'h2_total' => $this->h2Total,
            'idioma' => $this->idioma,
            'canonical' => $this->canonical,
            'generador' => $this->generador,
            'charset' => $this->charset,
            'tiene_viewport' => $this->tieneViewport,
            'tiene_favicon' => $this->tieneFavicon,
            'tiene_og' => $this->tieneOg,
            'tiene_jsonld' => $this->tieneJsonld,
            'jsonld_tipos' => $this->jsonldTipos,
            'imagenes_total' => $this->imagenesTotal,
            'imagenes_sin_alt' => $this->imagenesSinAlt,
            'enlaces_internos' => $this->enlacesInternos,
            'enlaces_externos' => $this->enlacesExternos,
            'redes_sociales' => $this->redesSociales,
            'telefonos' => $this->telefonos,
            'emails_encontrados' => $this->emailsEncontrados,
            'tiene_formulario' => $this->tieneFormulario,
            'tiene_whatsapp' => $this->tieneWhatsapp,
            'tiene_reservas' => $this->tieneReservas,
            'tiene_carrito' => $this->tieneCarrito,
            'tiene_aviso_legal' => $this->tieneAvisoLegal,
            'tiene_privacidad' => $this->tienePrivacidad,
            'tiene_cookies' => $this->tieneCookies,
            'anio_copyright' => $this->anioCopyright,
            'html_hash' => $this->htmlHash,
            'error' => $this->error,
            'capturada_at' => $this->capturadaAt,
        ];
    }
}
