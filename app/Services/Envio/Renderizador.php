<?php

namespace App\Services\Envio;

use App\Excepciones\PlantillaInvalida;
use App\Models\Lead;
use App\Services\Auditoria\RedactorHallazgo;

class Renderizador
{
    public function __construct(private RedactorHallazgo $redactor) {}

    /**
     * @return array{asunto: string, texto: string, html: string}|null
     *
     * @throws PlantillaInvalida si el resultado rompe las reglas antispam
     */
    public function renderizar(Lead $lead, int $paso = 1): ?array
    {
        $plantilla = $lead->plantilla();          // de config/sectores.php
        $auditoria = $lead->auditoria;

        if ($plantilla === null || $auditoria === null) {
            return null;
        }

        $frases = $this->redactor->redactar($lead, $auditoria, secundario: $paso === 2);

        if ($frases === null) {
            return null;
        }

        $datos = [
            'nombre' => $lead->nombre,
            'dominio' => $lead->website_dominio ?? '',
            'apertura' => $frases['apertura'],
            'remitenteNombre' => config('outreach.envio.remitente.nombre_legal'),
            'remitenteDireccion' => config('outreach.envio.remitente.direccion'),
            'emailBaja' => config('outreach.envio.remitente.email_baja'),
            'urlBaja' => config('outreach.envio.remitente.url_baja'),
        ];

        $vista = "{$plantilla}-{$paso}";

        $texto = trim(view("emails.texto.{$vista}", $datos)->render());
        $html = trim(view("emails.html.{$vista}", $datos)->render());

        $asunto = $paso === 1
            ? $frases['asunto']
            : 'Re: '.$frases['asunto'];

        $this->validar($asunto, $texto, $html, $paso);

        return ['asunto' => $asunto, 'texto' => $texto, 'html' => $html];
    }

    /** @throws PlantillaInvalida */
    private function validar(string $asunto, string $texto, string $html, int $paso): void
    {
        $cfg = config('outreach.envio');

        // Longitud del asunto
        if (mb_strlen($asunto) > $cfg['max_caracteres_asunto']) {
            throw new PlantillaInvalida('Asunto de '.mb_strlen($asunto).' caracteres');
        }

        // Número de palabras, sin el pie legal (todo lo que va tras la línea ---)
        $cuerpo = explode("\n---", $texto)[0];
        $palabras = str_word_count(strip_tags($cuerpo), 0, 'áéíóúñüÁÉÍÓÚÑÜ');
        $maximo = $paso === 1 ? $cfg['max_palabras_cuerpo'] : $cfg['max_palabras_seguimiento'];

        if ($palabras > $maximo) {
            throw new PlantillaInvalida("Cuerpo de {$palabras} palabras (máximo {$maximo})");
        }

        // Enlaces
        if (substr_count($html, '<a ') > $cfg['max_enlaces']) {
            throw new PlantillaInvalida('Más de un enlace en el HTML');
        }

        // Imágenes
        if (str_contains($html, '<img')) {
            throw new PlantillaInvalida('El correo no puede llevar imágenes');
        }

        // Palabras prohibidas
        $minusculas = mb_strtolower($asunto.' '.$cuerpo);
        foreach ($cfg['palabras_prohibidas'] as $prohibida) {
            if (str_contains($minusculas, mb_strtolower($prohibida))) {
                throw new PlantillaInvalida("Palabra prohibida: {$prohibida}");
            }
        }

        // Marcadores sin sustituir
        if (str_contains($texto, '{') || str_contains($html, '{')) {
            throw new PlantillaInvalida('Quedan marcadores sin sustituir');
        }
    }
}
