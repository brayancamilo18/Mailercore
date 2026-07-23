<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Suppression extends Model
{
    public const UPDATED_AT = null;

    public const MOTIVOS = [
        'baja' => 'Baja solicitada',
        'rebote_duro' => 'Rebote permanente',
        'manual' => 'Manual',
        'queja' => 'Queja de spam',
        'supresion_rgpd' => 'Supresión RGPD',
    ];

    protected $fillable = ['email', 'email_hash', 'dominio', 'motivo', 'detalle'];

    /** Normaliza un email: minúsculas y sin espacios. */
    public static function normalizarEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    /** Extrae el dominio de un email, o null si no es válido. */
    public static function dominioDeEmail(?string $email): ?string
    {
        $email = self::normalizarEmail($email);
        $partes = explode('@', $email, 2);

        if (count($partes) !== 2 || $partes[1] === '') {
            return null;
        }

        return $partes[1];
    }

    /** Extrae el dominio de una URL, sin www. */
    public static function dominioDeWeb(?string $web): ?string
    {
        if ($web === null || trim($web) === '') {
            return null;
        }

        $url = trim($web);

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

    /** ¿El email o su dominio están excluidos? */
    public static function existe(?string $email): bool
    {
        $email = self::normalizarEmail($email);

        if ($email === '') {
            return false;
        }

        if (self::query()->where('email', $email)->exists()) {
            return true;
        }

        if (self::query()->where('email_hash', hash('sha256', $email))->exists()) {
            return true;
        }

        $dominio = self::dominioDeEmail($email);

        return $dominio !== null
            && self::query()->where('dominio', $dominio)->exists();
    }

    /** ¿El dominio está excluido por completo? */
    public static function dominioExcluido(?string $dominio): bool
    {
        if ($dominio === null || $dominio === '') {
            return false;
        }

        return self::query()->where('dominio', strtolower($dominio))->exists();
    }

    /** Registra una exclusión (idempotente por email). */
    public static function registrar(string $email, string $motivo, ?string $detalle = null): self
    {
        $email = self::normalizarEmail($email);

        return self::query()->updateOrCreate(
            ['email' => $email],
            [
                'email_hash' => hash('sha256', $email),
                'dominio' => self::dominioDeEmail($email),
                'motivo' => $motivo,
                'detalle' => $detalle,
                'created_at' => now(),
            ]
        );
    }

    /** Excluye un dominio entero (tras varios rebotes duros, por ejemplo). */
    public static function registrarDominio(string $dominio, string $motivo, ?string $detalle = null): self
    {
        $dominio = strtolower(trim($dominio));

        return self::query()->updateOrCreate(
            ['email' => null, 'dominio' => $dominio],
            ['motivo' => $motivo, 'detalle' => $detalle, 'created_at' => now()]
        );
    }

    /**
     * Supresión RGPD: solo conserva el hash del email (sin texto en claro).
     */
    public static function registrarSupresionRgpd(string $email, ?string $detalle = null): self
    {
        $email = self::normalizarEmail($email);
        $hash = hash('sha256', $email);

        self::query()->where('email', $email)->delete();
        self::query()->where('email_hash', $hash)->delete();

        return self::query()->create([
            'email' => null,
            'email_hash' => $hash,
            'dominio' => null,
            'motivo' => 'supresion_rgpd',
            'detalle' => $detalle,
            'created_at' => now(),
        ]);
    }
}
