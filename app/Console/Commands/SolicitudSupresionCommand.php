<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Suppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SolicitudSupresionCommand extends Command
{
    protected $signature = 'datos:supresion
                            {email : Email del interesado}
                            {--motivo=Solicitud del interesado}';

    protected $description = 'Atiende una solicitud de supresión RGPD (borra lead y deja solo el hash)';

    public function handle(): int
    {
        $email = Suppression::normalizarEmail((string) $this->argument('email'));
        $motivo = (string) $this->option('motivo');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email inválido.');

            return self::FAILURE;
        }

        $leadIds = LeadEmail::query()
            ->where('email', $email)
            ->pluck('lead_id')
            ->unique()
            ->values()
            ->all();

        $borrados = 0;

        DB::transaction(function () use ($email, $motivo, $leadIds, &$borrados): void {
            if ($leadIds !== []) {
                $borrados = Lead::query()->whereIn('id', $leadIds)->delete();
            }

            Suppression::registrarSupresionRgpd($email, $motivo);
        });

        $hash = hash('sha256', $email);
        $ahora = now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s T');

        $this->newLine();
        $this->info('=== Justificante de supresión RGPD ===');
        $this->line("Fecha/hora: {$ahora}");
        $this->line('Email (no conservado en claro): '.$email);
        $this->line("email_hash: {$hash}");
        $this->line('Motivo en suppressions: supresion_rgpd');
        $this->line("Detalle: {$motivo}");
        $this->line("Leads borrados (cascade): {$borrados}");
        $this->newLine();
        $this->comment('Queda solo la fila de exclusión por hash. El email en claro no se almacena.');

        return self::SUCCESS;
    }
}
