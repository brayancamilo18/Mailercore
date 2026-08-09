<?php

namespace App\Enums;

/**
 * Para qué se rastrea un sitio. Decide si el bucle de rutas puede cortarse en
 * cuanto hay correos suficientes y, más adelante, qué rutas se visitan.
 */
enum PropositoRastreo: string
{
    /** Solo se buscan correos: en cuanto hay suficientes, se deja de rastrear. */
    case EMAIL = 'email';

    /** Se necesitan señales de todo el sitio: se visitan todas las rutas. */
    case AUDITORIA = 'auditoria';

    /** Correos y señales: se visitan todas las rutas. */
    case COMPLETO = 'completo';

    /** ¿Puede el bucle cortarse al reunir correos de rol suficientes? */
    public function permiteCorteAnticipadoPorCorreos(): bool
    {
        return $this === self::EMAIL;
    }

    /**
     * ¿Este rastreo recorre el sitio lo bastante como para que no encontrar una
     * señal signifique que no está?
     */
    public function cubreAuditoria(): bool
    {
        return $this !== self::EMAIL;
    }

    /** ¿Se buscan rutas nuevas entre los enlaces de la home? */
    public function permiteDescubrirRutas(): bool
    {
        return $this !== self::EMAIL;
    }

    /** Valores aceptados, para la ayuda y la validación del comando. */
    public static function valoresAceptados(): string
    {
        return implode('|', array_column(self::cases(), 'value'));
    }
}
