<?php

namespace App\Console\Commands;

use App\Models\Suppression;
use Illuminate\Console\Command;

class SuppressEmailCommand extends Command
{
    protected $signature = 'outreach:suppress {email : Email a excluir de futuros contactos} {--reason=manual : Motivo (baja, rebote, manual)}';

    protected $description = 'Añade un email (y su dominio) a la lista de supresión';

    public function handle(): int
    {
        $email = Suppression::normalizeEmail($this->argument('email'));
        $reason = (string) $this->option('reason');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El email no es válido.');

            return self::FAILURE;
        }

        if (! array_key_exists($reason, Suppression::REASONS)) {
            $this->error('Motivo no válido. Usa: '.implode(', ', array_keys(Suppression::REASONS)));

            return self::FAILURE;
        }

        $domain = Suppression::domainFromEmail($email);

        Suppression::query()->updateOrCreate(
            ['email' => $email],
            [
                'domain' => $domain,
                'reason' => $reason,
                'created_at' => now(),
            ]
        );

        $this->info("Supresión registrada: {$email} (dominio: {$domain}, motivo: {$reason}).");

        return self::SUCCESS;
    }
}
