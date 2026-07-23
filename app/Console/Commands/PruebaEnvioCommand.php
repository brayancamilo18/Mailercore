<?php

namespace App\Console\Commands;

use App\Mail\CorreoOutreach;
use App\Models\Auditoria;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Mensaje;
use App\Models\Suppression;
use App\Services\Envio\Renderizador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PruebaEnvioCommand extends Command
{
    protected $signature = 'envio:prueba
                            {email : Dirección de prueba (no puede ser un lead)}
                            {--plantilla=hosteleria : Clave de sector / plantilla}
                            {--paso=1 : 1 o 2}';

    protected $description = 'Envía un correo de prueba renderizado (Mailpit / mail-tester)';

    public function handle(Renderizador $renderizador): int
    {
        $email = Suppression::normalizarEmail((string) $this->argument('email'));
        $sector = (string) $this->option('plantilla');
        $paso = max(1, min(2, (int) $this->option('paso')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email inválido.');

            return self::FAILURE;
        }

        if (LeadEmail::query()->where('email', $email)->exists()) {
            $this->error('Ese email existe en lead_emails. Nunca se prueba con un lead real.');

            return self::FAILURE;
        }

        if (! isset(config('sectores')[$sector])) {
            $this->error("Sector/plantilla desconocido: {$sector}");

            return self::FAILURE;
        }

        $lead = new Lead([
            'nombre' => 'Negocio de Prueba',
            'website' => 'https://ejemplo-prueba.test',
            'website_dominio' => 'ejemplo-prueba.test',
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

        try {
            $render = $renderizador->renderizar($lead, $paso);
        } catch (\Throwable $e) {
            $this->error('Error al renderizar: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($render === null) {
            $this->error('El renderizador devolvió null (¿falta frase de hallazgo?).');

            return self::FAILURE;
        }

        $dominioRemitente = Suppression::dominioDeEmail(config('mail.from.address')) ?? 'local';
        $messageId = 'prueba-'.Str::uuid().'@'.$dominioRemitente;

        $mensaje = new Mensaje([
            'destinatario' => $email,
            'plantilla' => $lead->plantilla(),
            'paso' => $paso,
            'asunto' => $render['asunto'],
            'cuerpo_texto' => $render['texto'],
            'cuerpo_html' => $render['html'],
            'estado' => 'pendiente',
            'programado_para' => now(),
            'message_id' => $messageId,
        ]);

        $mailable = new CorreoOutreach($mensaje);
        $cabecerasTexto = $mailable->headers()->text;

        $this->info('=== Vista previa (NO enviado todavía) ===');
        $this->line('Para: '.$email);
        $this->line('Asunto: '.$render['asunto']);
        $this->newLine();
        $this->line('--- Texto plano ---');
        $this->line($render['texto']);
        $this->newLine();
        $this->line('--- HTML ---');
        $this->line($render['html']);
        $this->newLine();
        $this->line('--- Cabeceras ---');
        $this->line('From: '.config('mail.from.address').' ('.config('mail.from.name').')');
        $this->line('Reply-To: '.(config('outreach.envio.remitente.responder_a') ?? '—'));
        $this->line('Message-ID: <'.$messageId.'>');
        foreach ($cabecerasTexto as $nombre => $valor) {
            $this->line("{$nombre}: {$valor}");
        }
        $this->newLine();

        if (! $this->confirm('¿Enviar este correo de prueba?', false)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            $this->error('Fallo al enviar: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Enviado. Message-ID: <'.$messageId.'>');

        return self::SUCCESS;
    }
}
