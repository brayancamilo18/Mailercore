<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SectoresStatusCommand extends Command
{
    protected $signature = 'leads:sectores-status';

    protected $description = 'Muestra el estado de leads por sector';

    public function handle(): int
    {
        $totalGlobal = Lead::query()->count();

        $filas = Lead::query()
            ->select([
                'sector',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when exists (select 1 from lead_emails e where e.lead_id = leads.id and e.tipo = 'rol') then 1 else 0 end) as con_email_rol"),
                DB::raw('sum(case when rastreado_at is not null then 1 else 0 end) as rastreados'),
                DB::raw("sum(case when estado = 'auditado' then 1 else 0 end) as auditados"),
                DB::raw('sum(case when contactado_at is not null then 1 else 0 end) as contactados'),
            ])
            ->groupBy('sector')
            ->orderByDesc('total')
            ->get();

        $tabla = [];
        $suma = 0;

        foreach ($filas as $fila) {
            $total = (int) $fila->total;
            $suma += $total;
            $pct = $totalGlobal > 0 ? round(($total / $totalGlobal) * 100, 1) : 0;
            $tabla[] = [
                $fila->sector ?? 'sin clasificar',
                $total,
                (int) $fila->con_email_rol,
                (int) $fila->rastreados,
                (int) $fila->auditados,
                (int) $fila->contactados,
                $pct.'%',
            ];
        }

        $tabla[] = [
            'TOTAL',
            $suma,
            '',
            '',
            '',
            '',
            $totalGlobal > 0 ? '100%' : '0%',
        ];

        $this->table(
            ['Sector', 'Total', 'Con email rol', 'Rastreados', 'Auditados', 'Contactados', '%'],
            $tabla
        );

        return self::SUCCESS;
    }
}
