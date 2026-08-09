<?php

namespace App\Enums;

/**
 * Cuánto se puede defender un hallazgo delante del dueño de la web.
 *
 * La auditoría mezcla tres cosas muy distintas: lo que hemos medido, lo que
 * hemos buscado por todo el sitio sin encontrarlo, y lo que solo hemos mirado
 * en una página. Antes las tres acababan igual en el correo, y afirmar «no
 * tienes reservas» porque no visitamos /reservas es la forma más rápida de
 * perder al lead.
 */
enum Certeza: string
{
    /** Medido en la propia respuesta: TLS, código HTTP, milisegundos, PSI. */
    case HECHO = 'hecho';

    /** Ausencia buscada en un rastreo con cobertura de auditoría y no encontrada. */
    case OBSERVADO = 'observado';

    /** Señal de una sola página: sugiere, pero no demuestra. */
    case INDICIO = 'indicio';

    /**
     * ¿Se puede escribir en un correo como afirmación? Los indicios no: se
     * quedan para puntuar y priorizar internamente.
     */
    public function sePuedeAfirmar(): bool
    {
        return $this !== self::INDICIO;
    }
}
