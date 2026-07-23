<?php

namespace App\Console\Commands;

use App\Models\EventoInbox;
use App\Models\Suppression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EstadoBandejaCommand extends Command
{
    protected $signature = 'outreach:bandeja-estado
                            {--dias=30}';

    protected $description = 'Muestra el estado del procesamiento de la bandeja';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));

        $this->info('Últimos 20 eventos');
        $eventos = EventoInbox::query()
            ->orderByDesc('recibido_at')
            ->limit(20)
            ->get()
            ->map(fn (EventoInbox $e): array => [
                optional($e->recibido_at)?->format('Y-m-d H:i') ?? '—',
                $e->tipo,
                Suppression::dominioDeEmail($e->email) ?? '—',
                mb_substr((string) $e->extracto, 0, 60),
            ])
            ->all();

        $this->table(['Fecha', 'Tipo', 'Dominio', 'Extracto'], $eventos);

        $this->newLine();
        $this->info("Contadores últimos {$dias} días");

        $conteos = EventoInbox::query()
            ->where('recibido_at', '>=', now()->subDays($dias))
            ->select('tipo', DB::raw('count(*) as total'))
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $filas = [];
        foreach (EventoInbox::TIPOS as $tipo) {
            $filas[] = [$tipo, (int) ($conteos[$tipo] ?? 0)];
        }
        $this->table(['Tipo', 'Total'], $filas);

        $ultimo = EventoInbox::query()->orderByDesc('recibido_at')->value('recibido_at');
        $fallos = (int) Cache::get('bandeja:fallos_seguidos', 0);

        $this->newLine();
        $this->line('Último evento procesado: '.($ultimo ? $ultimo->format('Y-m-d H:i:s') : 'ninguno'));
        $this->line('bandeja:fallos_seguidos = '.$fallos);

        return self::SUCCESS;
    }
}
