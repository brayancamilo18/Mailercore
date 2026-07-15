<?php

namespace App\Console\Commands;

use App\Mail\AgencyOutreachMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSendCommand extends Command
{
    protected $signature = 'agencies:test-send
                            {email : Dirección de prueba (usa la tuya; no un lead real)}';

    protected $description = 'Envía un único AgencyOutreachMail de prueba (plantilla + List-Unsubscribe)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('El email no es válido.');

            return self::FAILURE;
        }

        $cfg = config('outreach.sending');

        $mailable = new AgencyOutreachMail(
            agencyName: 'Agencia de Prueba',
            unsubscribeEmail: (string) ($cfg['unsubscribe_email'] ?? $email),
            unsubscribeUrl: (string) ($cfg['unsubscribe_url'] ?? ''),
        );

        // Valida render de la plantilla antes de enviar.
        $html = $mailable->render();

        if (! str_contains(strtolower($html), 'baja') && ! str_contains(strtolower($html), 'unsubscribe')) {
            $this->warn('Aviso: el cuerpo renderizado no parece incluir opción de baja visible.');
        }

        $headers = $mailable->headers();
        $listUnsub = $headers->text['List-Unsubscribe'] ?? null;

        if ($listUnsub === null || $listUnsub === '') {
            $this->error('Falta la cabecera List-Unsubscribe en AgencyOutreachMail.');

            return self::FAILURE;
        }

        $this->line("Cabecera List-Unsubscribe: {$listUnsub}");
        $this->line('Remitente / host SMTP: '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));

        Mail::to($email)->send($mailable);

        $this->info("Correo de prueba enviado a {$email}. Revisa Mailpit (http://localhost:8025) o tu bandeja real.");

        return self::SUCCESS;
    }
}
