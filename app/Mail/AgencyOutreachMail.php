<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AgencyOutreachMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $agencyName,
        public string $unsubscribeEmail,
        public string $unsubscribeUrl = '',
    ) {
    }

    /**
     * Configura remitente global, reply-to y asunto.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [config('outreach.sending.reply_to')],
            subject: "Brazo técnico para {$this->agencyName} (webs y tiendas online)",
        );
    }

    /**
     * Plantilla markdown y datos legales del remitente.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.agency-outreach',
            with: [
                'agencyName' => $this->agencyName,
                'senderLegalName' => config('outreach.sending.sender_legal_name'),
                'senderAddress' => config('outreach.sending.sender_address'),
                'unsubscribeEmail' => $this->unsubscribeEmail,
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    /**
     * Cabeceras de baja obligatorias para cumplimiento legal.
     */
    public function headers(): Headers
    {
        $listUnsubscribe = '<mailto:'.$this->unsubscribeEmail.'?subject=BAJA>';
        $text = [];

        if ($this->unsubscribeUrl !== '') {
            $listUnsubscribe .= ', <'.$this->unsubscribeUrl.'>';
            // One-Click (RFC 8058) solo es válido con una URL HTTPS de baja.
            $text['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        $text = ['List-Unsubscribe' => $listUnsubscribe] + $text;

        return (new Headers)->text($text);
    }
}
