<?php

namespace App\Console\Commands;

use App\Models\LeadEmail;
use App\Models\Suppression;
use App\Services\Envio\PlanificadorDiario;
use App\Services\Verificacion\VerificadorEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VerificarEmailsCommand extends Command
{
    protected $signature = 'emails:verificar
                            {--limite=200}
                            {--solo-cola : Solo los que el planificador elegiría mañana}
                            {--reverificar-dias=30}';

    protected $description = 'Verifica la validez de los emails de leads';

    public function handle(VerificadorEmail $verificador, PlanificadorDiario $planificador): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $emails = $this->seleccionarEmails($planificador, $limite);

        $validos = 0;
        $riesgo = 0;
        $invalidos = 0;
        $catchAll = 0;
        $dominiosAntes = Suppression::query()->whereNotNull('dominio')->whereNull('email')->count();

        $barra = $this->output->createProgressBar($emails->count());
        $barra->start();

        foreach ($emails as $email) {
            $resultado = $verificador->verificar($email);
            $email->refresh();

            match ($resultado) {
                'valido' => $validos++,
                'riesgo' => $riesgo++,
                default => $invalidos++,
            };

            if ($email->es_catch_all === true) {
                $catchAll++;
            }

            $barra->advance();
        }

        $barra->finish();
        $this->newLine(2);

        $dominiosDespues = Suppression::query()->whereNotNull('dominio')->whereNull('email')->count();

        $this->table(
            ['Métrica', 'Total'],
            [
                ['Verificados', $emails->count()],
                ['Válidos', $validos],
                ['Riesgo', $riesgo],
                ['Inválidos', $invalidos],
                ['Catch-all detectados', $catchAll],
                ['Dominios suprimidos (nuevos)', max(0, $dominiosDespues - $dominiosAntes)],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, LeadEmail>
     */
    private function seleccionarEmails(PlanificadorDiario $planificador, int $limite)
    {
        if ($this->option('solo-cola')) {
            config(['outreach.envio.activo' => true]);

            $plan = $planificador->planificar(Carbon::tomorrow(), true);
            $ids = collect($plan['plan'] ?? [])
                ->pluck('lead_email_id')
                ->filter()
                ->unique()
                ->take($limite)
                ->values();

            return LeadEmail::query()->whereIn('id', $ids)->get();
        }

        $dias = max(1, (int) $this->option('reverificar-dias'));

        return LeadEmail::query()
            ->where(function ($q) use ($dias): void {
                $q->whereNull('verificado_at')
                    ->orWhere('verificado_at', '<', now()->subDays($dias));
            })
            ->leftJoin('auditorias', 'auditorias.lead_id', '=', 'lead_emails.lead_id')
            ->select('lead_emails.*')
            ->orderByDesc('auditorias.puntuacion')
            ->limit($limite)
            ->get();
    }
}
