<?php

namespace App\Mail;

use App\Models\Mensaje;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CorreoOutreach extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Mensaje $mensaje) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mensaje->asunto,
            replyTo: array_filter([config('outreach.envio.remitente.responder_a')]),
        );
    }

    public function content(): Content
    {
        // Usa el texto YA guardado en la fila, no re-renderiza.
        // Así lo que se envía es exactamente lo que se revisó en el panel.
        return new Content(
            htmlString: $this->mensaje->cuerpo_html,
            text: 'emails.texto_plano',
            with: ['cuerpo' => $this->mensaje->cuerpo_texto],
        );
    }

    public function headers(): Headers
    {
        $emailBaja = config('outreach.envio.remitente.email_baja');
        $urlBaja = config('outreach.envio.remitente.url_baja');

        $listUnsubscribe = '<mailto:'.$emailBaja.'?subject=BAJA>';
        $texto = [];

        if (! empty($urlBaja)) {
            $listUnsubscribe .= ', <'.$urlBaja.'>';
            $texto['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new Headers(
            messageId: $this->mensaje->message_id,
            text: ['List-Unsubscribe' => $listUnsubscribe] + $texto,
        );
    }
}
