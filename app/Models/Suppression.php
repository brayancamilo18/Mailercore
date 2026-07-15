<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    /** No usamos updated_at; solo se registra la fecha de alta. */
    public const UPDATED_AT = null;

    /** Motivos admitidos para una supresión. */
    public const REASONS = [
        'baja' => 'Baja',
        'rebote' => 'Rebote',
        'manual' => 'Manual',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'domain',
        'reason',
    ];

    /**
     * Indica si el email (o su dominio) está en la lista de exclusión.
     */
    public static function has(string $email): bool
    {
        $email = self::normalizeEmail($email);

        if ($email === '') {
            return false;
        }

        if (self::query()->where('email', $email)->exists()) {
            return true;
        }

        $domain = self::domainFromEmail($email);

        if ($domain === null) {
            return false;
        }

        return self::query()->where('domain', $domain)->exists();
    }

    /**
     * Normaliza un email: minúsculas y sin espacios.
     */
    public static function normalizeEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /**
     * Extrae el dominio de un email (sin el @).
     */
    public static function domainFromEmail(string $email): ?string
    {
        $email = self::normalizeEmail($email);
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2 || $parts[1] === '') {
            return null;
        }

        return strtolower($parts[1]);
    }

    /**
     * Extrae el dominio registrable aproximado de una URL (sin www.).
     */
    public static function domainFromWebsite(?string $website): ?string
    {
        if ($website === null || trim($website) === '') {
            return null;
        }

        $url = trim($website);

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : null;
    }
}
