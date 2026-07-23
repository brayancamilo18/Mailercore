<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\Suppression;
use App\Services\Envio\Renderizador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Webklex\IMAP\Facades\Client;

class DiagnosticoEnvioCommand extends Command
{
    protected $signature = 'envio:diagnostico
                            {--dominio= : Dominio del remitente (por defecto el de MAIL_FROM_ADDRESS)}';

    protected $description = 'Diagnóstico previo al primer envío (DNS, SMTP, IMAP, plantillas)';

    /** @var list<array{comprobacion: string, estado: string, detalle: string}> */
    private array $filas = [];

    private bool $hayFallo = false;

    public function handle(Renderizador $renderizador): int
    {
        $dominio = $this->resolverDominio();

        if ($dominio === null || $dominio === '') {
            $this->error('No se pudo determinar el dominio. Pasa --dominio= o configura MAIL_FROM_ADDRESS.');

            return self::FAILURE;
        }

        $this->info("Diagnóstico de envío para: {$dominio}");
        $this->newLine();

        $this->comprobarSpf($dominio);
        $this->comprobarDkim($dominio);
        $this->comprobarDmarc($dominio);
        $this->comprobarMx($dominio);
        $this->comprobarSmtp();
        $this->comprobarImap();
        $this->comprobarUrlBaja();
        $this->comprobarPlantillas($renderizador);
        $this->comprobarLeadsPorSector();
        $this->comprobarConfigLegal();
        $this->comprobarAppDebug();

        $this->table(['Comprobación', 'Estado', 'Detalle'], array_map(
            fn (array $f): array => [$f['comprobacion'], $f['estado'], $f['detalle']],
            $this->filas
        ));

        $ok = count(array_filter($this->filas, fn (array $f): bool => $f['estado'] === 'OK'));
        $avisos = count(array_filter($this->filas, fn (array $f): bool => $f['estado'] === 'AVISO'));
        $fallos = count(array_filter($this->filas, fn (array $f): bool => $f['estado'] === 'FALLO'));

        $this->newLine();
        $this->line("Resumen: {$ok} OK · {$avisos} avisos · {$fallos} fallos");

        if ($this->hayFallo) {
            $this->error('Hay fallos que impiden enviar. No actives OUTREACH_ENVIO_ACTIVO todavía.');

            return self::FAILURE;
        }

        $this->info('Diagnóstico en verde. Puedes continuar con envio:prueba y luego activar el envío.');

        return self::SUCCESS;
    }

    private function resolverDominio(): ?string
    {
        $opcion = $this->option('dominio');
        if (is_string($opcion) && trim($opcion) !== '') {
            return strtolower(trim($opcion));
        }

        return Suppression::dominioDeEmail(config('mail.from.address'))
            ?? Suppression::dominioDeEmail(config('outreach.envio.remitente.email_baja'));
    }

    private function anadir(string $comprobacion, string $estado, string $detalle): void
    {
        if ($estado === 'FALLO') {
            $this->hayFallo = true;
        }

        $this->filas[] = compact('comprobacion', 'estado', 'detalle');
    }

    private function comprobarSpf(string $dominio): void
    {
        $txts = $this->registrosTxt($dominio);
        $spf = null;
        foreach ($txts as $txt) {
            if (str_starts_with(strtolower($txt), 'v=spf1')) {
                $spf = $txt;
                break;
            }
        }

        if ($spf === null) {
            $this->anadir('SPF', 'FALLO', 'No hay registro TXT v=spf1');

            return;
        }

        $estado = 'OK';
        $detalle = $spf;
        if (preg_match('/[?+]all\b/i', $spf)) {
            $estado = 'AVISO';
            $detalle .= ' (termina en ?all o +all)';
        }

        $this->anadir('SPF', $estado, mb_substr($detalle, 0, 120));
    }

    private function comprobarDkim(string $dominio): void
    {
        $selectores = ['default', 'hostinger', 'hs1', 's1', 'mail', 'google'];
        $encontrados = [];

        foreach ($selectores as $selector) {
            $host = "{$selector}._domainkey.{$dominio}";
            foreach ($this->registrosTxt($host) as $txt) {
                if (str_contains(strtolower($txt), 'v=dkim1') || str_contains($txt, 'p=')) {
                    $encontrados[] = $selector;
                    break;
                }
            }
        }

        if ($encontrados === []) {
            $this->anadir('DKIM', 'FALLO', 'Ningún selector habitual respondió');

            return;
        }

        $this->anadir('DKIM', 'OK', 'Selectores: '.implode(', ', $encontrados));
    }

    private function comprobarDmarc(string $dominio): void
    {
        $txts = $this->registrosTxt('_dmarc.'.$dominio);
        $dmarc = null;
        foreach ($txts as $txt) {
            if (str_starts_with(strtolower($txt), 'v=dmarc1')) {
                $dmarc = $txt;
                break;
            }
        }

        if ($dmarc === null) {
            $this->anadir('DMARC', 'FALLO', 'No hay registro TXT en _dmarc.'.$dominio);

            return;
        }

        $politica = 'desconocida';
        if (preg_match('/\bp=(none|quarantine|reject)\b/i', $dmarc, $m)) {
            $politica = strtolower($m[1]);
        }

        $this->anadir('DMARC', 'OK', "política p={$politica}");
    }

    private function comprobarMx(string $dominio): void
    {
        $mx = @dns_get_record($dominio, DNS_MX) ?: [];
        if ($mx === []) {
            $this->anadir('MX', 'FALLO', 'Sin registros MX');

            return;
        }

        usort($mx, fn (array $a, array $b): int => ($a['pri'] ?? 0) <=> ($b['pri'] ?? 0));
        $primero = $mx[0]['target'] ?? '?';
        $this->anadir('MX', 'OK', count($mx).' registros (prioridad baja: '.$primero.')');
    }

    private function comprobarSmtp(): void
    {
        if (config('mail.default') !== 'smtp') {
            $this->anadir('SMTP', 'FALLO', 'MAIL_MAILER no es smtp (actual: '.config('mail.default').')');

            return;
        }

        $cfg = config('mail.mailers.smtp');
        $host = (string) ($cfg['host'] ?? '');
        $port = (int) ($cfg['port'] ?? 0);

        if ($host === '' || $port <= 0) {
            $this->anadir('SMTP', 'FALLO', 'Host/puerto SMTP incompletos');

            return;
        }

        try {
            $tls = ($cfg['scheme'] ?? null) === 'smtps' || $port === 465;
            $transport = new EsmtpTransport($host, $port, $tls);
            if (! empty($cfg['username'])) {
                $transport->setUsername((string) $cfg['username']);
                $transport->setPassword((string) ($cfg['password'] ?? ''));
            }
            $transport->start();
            $transport->stop();
            $this->anadir('SMTP', 'OK', "EHLO+AUTH en {$host}:{$port}");
        } catch (\Throwable $e) {
            $this->anadir('SMTP', 'FALLO', mb_substr($e->getMessage(), 0, 160));
        }
    }

    private function comprobarImap(): void
    {
        try {
            $cuenta = config('imap.accounts.default');
            $cliente = Client::account('default');
            $cliente->connect();
            $carpetas = $cliente->getFolders(false);
            $n = $carpetas->count();
            $cliente->disconnect();
            $this->anadir('IMAP', 'OK', "{$n} carpetas en ".($cuenta['host'] ?? '?'));
        } catch (\Throwable $e) {
            $this->anadir('IMAP', 'FALLO', mb_substr($e->getMessage(), 0, 160));
        }
    }

    private function comprobarUrlBaja(): void
    {
        $url = config('outreach.envio.remitente.url_baja');
        if (empty($url)) {
            $this->anadir('URL de baja', 'AVISO', 'OUTREACH_URL_BAJA no configurada (solo mailto)');

            return;
        }

        try {
            $respuesta = Http::timeout(10)->get($url);
            if ($respuesta->successful()) {
                $this->anadir('URL de baja', 'OK', "HTTP {$respuesta->status()}");
            } else {
                $this->anadir('URL de baja', 'FALLO', "HTTP {$respuesta->status()}");
            }
        } catch (\Throwable $e) {
            $this->anadir('URL de baja', 'FALLO', mb_substr($e->getMessage(), 0, 160));
        }
    }

    private function comprobarPlantillas(Renderizador $renderizador): void
    {
        $errores = [];

        foreach (array_keys(config('sectores', [])) as $sector) {
            foreach ([1, 2] as $paso) {
                try {
                    $lead = $this->leadEjemplo($sector);
                    $resultado = $renderizador->renderizar($lead, $paso);
                    if ($resultado === null) {
                        $errores[] = "{$sector}-{$paso}: null";
                    }
                } catch (\Throwable $e) {
                    $errores[] = "{$sector}-{$paso}: ".$e->getMessage();
                }
            }
        }

        if ($errores === []) {
            $this->anadir('Plantillas (14)', 'OK', 'Todas renderizan');
        } else {
            $this->anadir('Plantillas (14)', 'FALLO', mb_substr(implode(' · ', $errores), 0, 200));
        }
    }

    private function comprobarLeadsPorSector(): void
    {
        try {
            $faltan = [];
            foreach (array_keys(config('sectores', [])) as $sector) {
                $hay = Lead::query()
                    ->where('sector', $sector)
                    ->whereHas('auditoria', fn ($q) => $q->whereNotNull('hallazgo_principal'))
                    ->exists();

                if (! $hay) {
                    $faltan[] = $sector;
                }
            }

            if ($faltan === []) {
                $this->anadir('Leads auditados/sector', 'OK', 'Al menos 1 por sector');
            } else {
                $this->anadir('Leads auditados/sector', 'FALLO', 'Faltan: '.implode(', ', $faltan));
            }
        } catch (\Throwable $e) {
            $this->anadir('Leads auditados/sector', 'FALLO', mb_substr($e->getMessage(), 0, 160));
        }
    }

    private function comprobarConfigLegal(): void
    {
        $rem = config('outreach.envio.remitente');
        $vacios = [];
        foreach (['nombre_legal' => 'OUTREACH_NOMBRE_LEGAL', 'direccion' => 'OUTREACH_DIRECCION_LEGAL', 'email_baja' => 'OUTREACH_EMAIL_BAJA'] as $clave => $env) {
            if (empty($rem[$clave])) {
                $vacios[] = $env;
            }
        }

        if ($vacios === []) {
            $this->anadir('Config legal', 'OK', 'nombre, dirección y email de baja');
        } else {
            $this->anadir('Config legal', 'FALLO', 'Vacío: '.implode(', ', $vacios));
        }
    }

    private function comprobarAppDebug(): void
    {
        if (config('app.env') === 'production' && config('app.debug')) {
            $this->anadir('APP_DEBUG', 'FALLO', 'APP_DEBUG=true en production');

            return;
        }

        $this->anadir('APP_DEBUG', 'OK', 'env='.config('app.env').' debug='.(config('app.debug') ? 'true' : 'false'));
    }

    private function leadEjemplo(string $sector): Lead
    {
        $lead = new Lead([
            'nombre' => 'Negocio de Prueba',
            'website' => 'https://ejemplo-diagnostico.test',
            'website_dominio' => 'ejemplo-diagnostico.test',
            'sector' => $sector,
            'estado' => 'auditado',
        ]);

        $auditoria = new Auditoria([
            'puntuacion' => 40,
            'hallazgo_codigo' => 'sin_viewport',
            'hallazgo_principal' => 'La web no declara viewport móvil',
            'hallazgo_secundario_codigo' => 'sin_viewport',
            'hallazgo_secundario' => 'La web no declara viewport móvil',
            'hallazgos' => [[
                'codigo' => 'sin_viewport',
                'peso' => 25,
                'titulo' => 'Sin viewport',
                'detalle' => 'La web no declara viewport móvil',
                'datos' => [],
            ]],
        ]);

        $lead->setRelation('auditoria', $auditoria);

        return $lead;
    }

    /** @return list<string> */
    private function registrosTxt(string $host): array
    {
        $registros = @dns_get_record($host, DNS_TXT) ?: [];
        $textos = [];

        foreach ($registros as $registro) {
            if (isset($registro['txt']) && is_string($registro['txt'])) {
                $textos[] = $registro['txt'];
            } elseif (isset($registro['entries']) && is_array($registro['entries'])) {
                $textos[] = implode('', $registro['entries']);
            }
        }

        return $textos;
    }
}
