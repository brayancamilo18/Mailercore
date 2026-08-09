<?php

namespace App\Console\Commands;

use App\DTO\ResultadoDiagnosticoRastreo;
use App\Enums\Certeza;
use App\Enums\PropositoRastreo;
use App\Models\Lead;
use App\Models\Pagina;
use App\Services\Auditoria\MotorAuditoria;
use App\Services\Web\RastreadorSitio;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class RastreoDiagnosticoCommand extends Command
{
    protected $signature = 'rastreo:diagnostico
                            {lead : ID del lead o dominio/website}
                            {--proposito=completo : email|auditoria|completo}';

    protected $description = 'Rastrea un lead en simulación (sin persistir) e imprime la traza completa';

    /** @var array<string, string> */
    private const SENALES = [
        'tiene_reservas' => 'Reservas',
        'tiene_carrito' => 'Carrito',
        'tiene_formulario' => 'Formulario',
        'tiene_whatsapp' => 'WhatsApp',
        'tiene_aviso_legal' => 'Aviso legal',
        'tiene_privacidad' => 'Privacidad',
        'tiene_cookies' => 'Cookies',
    ];

    public function handle(RastreadorSitio $rastreador, MotorAuditoria $motor): int
    {
        $proposito = PropositoRastreo::tryFrom(
            strtolower(trim((string) $this->option('proposito')))
        );

        if ($proposito === null) {
            $this->error('Propósito no válido. Valores: '.PropositoRastreo::valoresAceptados().'.');

            return self::FAILURE;
        }

        $lead = $this->resolverLead((string) $this->argument('lead'));

        if ($lead === null) {
            $this->error('No encontré ningún lead con ese ID o website.');

            return self::FAILURE;
        }

        if ($lead->website === null || trim($lead->website) === '') {
            $this->error("El lead #{$lead->id} no tiene website.");

            return self::FAILURE;
        }

        $this->imprimirCabecera($lead, $proposito);

        $antesPaginas = Pagina::query()->where('lead_id', $lead->id)->count();
        $antesRastreado = $lead->rastreado_at?->toIso8601String();

        $diagnostico = $rastreador->simular($lead, $proposito);
        $hallazgos = $motor->evaluar($lead, $diagnostico->paginas, $lead->auditoria);
        $diagnostico = new ResultadoDiagnosticoRastreo(
            resultado: $diagnostico->resultado,
            rutasFijas: $diagnostico->rutasFijas,
            rutasDescubiertas: $diagnostico->rutasDescubiertas,
            visitas: $diagnostico->visitas,
            paginas: $diagnostico->paginas,
            hallazgos: $hallazgos,
            rutasOmitidas: $diagnostico->rutasOmitidas,
        );

        $despuesPaginas = Pagina::query()->where('lead_id', $lead->id)->count();
        $lead->refresh();

        if ($despuesPaginas !== $antesPaginas || $lead->rastreado_at?->toIso8601String() !== $antesRastreado) {
            $this->error('AVISO: el modo simulación tocó la base de datos. Aborto la traza.');

            return self::FAILURE;
        }

        $this->imprimirPlan($diagnostico);
        $this->imprimirVisitas($diagnostico);
        $this->imprimirOmitidas($diagnostico);
        $this->imprimirSenales($diagnostico, $lead);
        $this->imprimirHallazgos($diagnostico);
        $this->imprimirResumen($diagnostico);

        return self::SUCCESS;
    }

    private function resolverLead(string $entrada): ?Lead
    {
        $entrada = trim($entrada);

        if ($entrada === '') {
            return null;
        }

        if (ctype_digit($entrada)) {
            return Lead::query()->find((int) $entrada);
        }

        $normalizada = preg_replace('#^https?://#i', '', $entrada) ?? $entrada;
        $normalizada = rtrim($normalizada, '/');
        $dominio = strtolower(explode('/', $normalizada)[0]);

        return Lead::query()
            ->where('website_dominio', $dominio)
            ->orWhere('website', 'like', '%'.$dominio.'%')
            ->orderBy('id')
            ->first();
    }

    private function imprimirCabecera(Lead $lead, PropositoRastreo $proposito): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>═══ Rastreo diagnóstico (simulación) ═══</>');
        $this->line(sprintf(
            '  Lead     <fg=white;options=bold>#%d</>  %s',
            $lead->id,
            $lead->nombre ?? '(sin nombre)'
        ));
        $this->line(sprintf('  Website  <fg=white>%s</>', $lead->website));
        $this->line(sprintf(
            '  Sector   <fg=white>%s</>   Propósito <fg=yellow;options=bold>%s</>',
            $lead->sector ?? '—',
            $proposito->value
        ));
        $this->line('  <fg=gray>No se escribe nada en base de datos.</>');
        $this->newLine();
    }

    private function imprimirPlan(ResultadoDiagnosticoRastreo $d): void
    {
        $this->line('<fg=cyan;options=bold>▸ Rutas planificadas</>');

        $fijas = $d->rutasFijas === [] ? ['<fg=gray>(ninguna)</>'] : array_map(
            fn (string $r): string => '<fg=blue>'.$r.'</>',
            $d->rutasFijas
        );
        $descubiertas = $d->rutasDescubiertas === [] ? ['<fg=gray>(ninguna)</>'] : array_map(
            fn (string $r): string => '<fg=magenta>'.$r.'</>',
            $d->rutasDescubiertas
        );

        $this->line('  Fijas        '.implode('  ', $fijas));
        $this->line('  Descubiertas '.implode('  ', $descubiertas));
        $this->newLine();
    }

    private function imprimirVisitas(ResultadoDiagnosticoRastreo $d): void
    {
        $this->line('<fg=cyan;options=bold>▸ Visitas</>');

        if ($d->visitas === []) {
            $this->line('  <fg=gray>Ninguna ruta se llegó a pedir.</>');
            $this->newLine();

            return;
        }

        $filas = [];
        foreach ($d->visitas as $v) {
            $filas[] = [
                $this->colorOrigen($v['origen']).' '.$v['ruta'],
                $this->colorStatus($v['http_status']),
                $v['ms'] === null ? '—' : (string) $v['ms'],
                $v['bytes'] === null ? '—' : $this->formatoBytes($v['bytes']),
                $this->colorTruncada($v['truncada']),
                $this->colorFallo($v['fallo_tipo']),
            ];
        }

        $this->tablaCompacta(
            ['Ruta', 'HTTP', 'ms', 'Bytes', 'Trunc.', 'Fallo'],
            $filas
        );
        $this->newLine();
    }

    private function imprimirOmitidas(ResultadoDiagnosticoRastreo $d): void
    {
        $this->line('<fg=cyan;options=bold>▸ Rutas omitidas</>');

        if ($d->rutasOmitidas === []) {
            $this->line('  <fg=green>Ninguna</>');
            $this->newLine();

            return;
        }

        foreach ($d->rutasOmitidas as $omitida) {
            $partes = explode(': ', $omitida, 2);
            $ruta = $partes[0];
            $motivo = $partes[1] ?? '';
            $this->line(sprintf('  <fg=yellow>%-28s</> <fg=gray>%s</>', $ruta, $motivo));
        }

        $this->newLine();
    }

    private function imprimirSenales(ResultadoDiagnosticoRastreo $d, Lead $lead): void
    {
        $this->line('<fg=cyan;options=bold>▸ Señales (tri-estado)</>');

        $filas = [];
        foreach (self::SENALES as $campo => $etiqueta) {
            $valor = Pagina::señal($d->paginas, $campo, $lead->sector);
            $motivo = $valor === null
                ? Pagina::motivoSenal($d->paginas, $campo, $lead->sector)
                : null;

            $filas[] = [
                $etiqueta,
                $this->formatoSenal($valor),
                $motivo !== null ? '<fg=gray>'.$motivo.'</>' : '',
            ];
        }

        $this->tablaCompacta(['Señal', 'Valor', 'Motivo'], $filas);
        $this->newLine();
    }

    private function imprimirHallazgos(ResultadoDiagnosticoRastreo $d): void
    {
        $this->line('<fg=cyan;options=bold>▸ Hallazgos que generaría la auditoría</>');

        if ($d->hallazgos === []) {
            $this->line('  <fg=green>Ninguno</>');
            $this->newLine();

            return;
        }

        $filas = [];
        $peso = 0;
        foreach ($d->hallazgos as $i => $h) {
            $peso += $h->peso;
            $filas[] = [
                $i === 0 ? '★' : ($i === 1 ? '☆' : ' '),
                $h->codigo,
                $this->colorCerteza($h->certeza),
                (string) $h->peso,
                $h->detalle,
            ];
        }

        $this->tablaCompacta(['', 'Código', 'Certeza', 'Peso', 'Detalle'], $filas);

        $tope = (int) config('outreach.auditoria.peso_maximo');
        $puntuacion = min($tope, $peso);
        $this->line(sprintf(
            '  Puntuación: <fg=white;options=bold>%d</>/%d  (%d hallazgos)',
            $puntuacion,
            $tope,
            count($d->hallazgos)
        ));
        $this->line('  <fg=gray>★ principal · ☆ secundario · los INDICIO no se escriben en el correo</>');
        $this->newLine();
    }

    private function imprimirResumen(ResultadoDiagnosticoRastreo $d): void
    {
        $r = $d->resultado;
        $estado = $r->fallido()
            ? '<fg=red;options=bold>FALLIDO</> ('.$r->falloTipo.')'
            : '<fg=green;options=bold>OK</>';

        $this->line('<fg=cyan;options=bold>▸ Resumen</>');
        $this->line(sprintf(
            '  Estado %s   visitadas %d   guardables %d   omitidas %d   errores %d',
            $estado,
            $r->paginasVisitadas,
            $r->paginasGuardadas,
            count($d->rutasOmitidas),
            count($r->errores)
        ));

        if ($r->errores !== []) {
            foreach ($r->errores as $error) {
                $this->line('  <fg=red>·</> '.$error);
            }
        }

        $this->newLine();
    }

    /**
     * @param  list<string>  $cabeceras
     * @param  list<list<string>>  $filas
     */
    private function tablaCompacta(array $cabeceras, array $filas): void
    {
        $tabla = new Table($this->output);
        $tabla->setHeaders($cabeceras);
        $tabla->setRows($filas);
        $tabla->setStyle('compact');
        $tabla->render();
    }

    private function formatoSenal(?bool $valor): string
    {
        return match ($valor) {
            true => '<fg=green;options=bold>sí</>',
            false => '<fg=red;options=bold>no</>',
            null => '<fg=yellow>no observado</>',
        };
    }

    private function colorCerteza(Certeza $certeza): string
    {
        return match ($certeza) {
            Certeza::HECHO => '<fg=green;options=bold>hecho</>',
            Certeza::OBSERVADO => '<fg=cyan;options=bold>observado</>',
            Certeza::INDICIO => '<fg=yellow>indicio</>',
        };
    }

    private function colorOrigen(string $origen): string
    {
        return $origen === 'descubierta'
            ? '<fg=magenta>◇</>'
            : '<fg=blue>●</>';
    }

    private function colorStatus(?int $status): string
    {
        if ($status === null) {
            return '<fg=gray>—</>';
        }

        return match (true) {
            $status >= 200 && $status < 300 => '<fg=green>'.$status.'</>',
            $status >= 300 && $status < 400 => '<fg=yellow>'.$status.'</>',
            default => '<fg=red>'.$status.'</>',
        };
    }

    private function colorTruncada(?bool $truncada): string
    {
        return match ($truncada) {
            true => '<fg=yellow>sí</>',
            false => '<fg=gray>no</>',
            null => '<fg=gray>—</>',
        };
    }

    private function colorFallo(?string $fallo): string
    {
        if ($fallo === null || $fallo === '') {
            return '<fg=gray>—</>';
        }

        return '<fg=red>'.$fallo.'</>';
    }

    private function formatoBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
