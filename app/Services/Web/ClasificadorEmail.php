<?php

namespace App\Services\Web;

use Illuminate\Support\Facades\Log;

class ClasificadorEmail
{
    public const ROL = 'rol';

    public const PERSONAL = 'personal';

    public const INVALIDO = 'invalido';

    public const RUIDO = 'ruido';

    /** @var list<string>|null */
    private static ?array $prefijosRolCache = null;

    /** @var list<string>|null */
    private static ?array $nombresCache = null;

    /**
     * Clasifica un email. Solo los que devuelvan ROL se persisten.
     */
    public function clasificar(string $email, ?string $dominioWeb = null): string
    {
        $email = strtolower(trim($email));

        // 1. Sintaxis
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return self::INVALIDO;
        }

        [$local, $dominio] = explode('@', $email, 2);

        if ($local === '' || $dominio === '') {
            return self::INVALIDO;
        }

        // 2. Ruido: extensiones de asset
        foreach (config('outreach.clasificador_email.extensiones_asset') as $ext) {
            if (str_ends_with($email, $ext)) {
                return self::RUIDO;
            }
        }

        // 3. Ruido: dominios de librerías y plantillas
        foreach (config('outreach.clasificador_email.dominios_ruido') as $ruido) {
            if ($dominio === $ruido || str_ends_with($dominio, '.'.$ruido)) {
                return self::RUIDO;
            }
        }

        // 4. Ruido: local part que parece un hash o un id generado
        if (preg_match('/^[a-f0-9]{16,}$/', $local)) {
            return self::RUIDO;
        }
        if (strlen($local) >= 20 && ! str_contains($local, '.')) {
            return self::RUIDO;
        }
        if (str_starts_with($local, '.') || str_ends_with($local, '.')) {
            return self::RUIDO;
        }

        // 5. Proveedor gratuito: es un correo personal aunque el prefijo parezca de rol
        $gratuitos = config('outreach.clasificador_email.proveedores_gratuitos');
        if (in_array($dominio, $gratuitos, true)) {
            return self::PERSONAL;
        }

        $base = $this->normalizarLocal($local);

        // 6. Prefijo de rol conocido
        if (in_array($base, $this->prefijosRol(), true)) {
            return self::ROL;
        }

        // 7. Nombre propio suelto → persona
        if (in_array($base, $this->nombres(), true)) {
            return self::PERSONAL;
        }

        // 8. nombre.apellido / nombre_apellido / nombre-apellido → persona
        if (preg_match('/^[a-z]{2,}[._-][a-z]{2,}/', $base)) {
            $primera = preg_split('/[._-]/', $base)[0];
            if (! in_array($primera, $this->prefijosRol(), true)) {
                return self::PERSONAL;
            }
        }

        // 9. inicial + apellido → persona
        if (preg_match('/^[a-z]\.?[a-z]{3,}$/', $base)) {
            return self::PERSONAL;
        }

        // 10. Prefijo de rol con sufijo numérico o compuesto
        foreach ($this->prefijosRol() as $prefijo) {
            if (str_starts_with($base, $prefijo)) {
                return self::ROL;
            }
        }

        // 11. Por defecto, tratar como rol pero dejar rastro para revisarlo.
        Log::channel('outreach')->debug('Email sin clasificar claramente', [
            'local' => $base,
            'dominio' => $dominio,
        ]);

        return self::ROL;
    }

    /** Prioridad de envío: menor es mejor. */
    public function prioridad(string $email): int
    {
        $base = $this->normalizarLocal(explode('@', strtolower($email))[0] ?? '');

        $grupos = [
            0 => ['info', 'contacto', 'contact', 'hola', 'hello', 'general', 'correo', 'mail'],
            1 => ['reservas', 'reservations', 'booking', 'citas', 'cita', 'pedidos',
                'pedido', 'tienda', 'shop', 'comercial', 'ventas', 'sales', 'agenda'],
            2 => ['administracion', 'atencion', 'atencioncliente', 'clientes',
                'soporte', 'support', 'ayuda', 'recepcion', 'reception', 'consultas'],
        ];

        foreach ($grupos as $nivel => $lista) {
            foreach ($lista as $prefijo) {
                if ($base === $prefijo || str_starts_with($base, $prefijo)) {
                    return $nivel;
                }
            }
        }

        return in_array($base, $this->prefijosRol(), true) ? 3 : 9;
    }

    /** Devuelve el prefijo normalizado para guardarlo en lead_emails.prefijo. */
    public function prefijo(string $email): string
    {
        return $this->normalizarLocal(explode('@', strtolower($email))[0] ?? '');
    }

    /** Quita tildes, números finales y espacios. */
    private function normalizarLocal(string $local): string
    {
        $local = strtr($local, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        ]);
        $local = preg_replace('/[0-9]+$/', '', $local) ?? $local;

        return trim($local);
    }

    /**
     * @return list<string>
     */
    private function prefijosRol(): array
    {
        return self::$prefijosRolCache ??= require resource_path('data/prefijos_rol.php');
    }

    /**
     * @return list<string>
     */
    private function nombres(): array
    {
        return self::$nombresCache ??= require resource_path('data/nombres_es.php');
    }
}
