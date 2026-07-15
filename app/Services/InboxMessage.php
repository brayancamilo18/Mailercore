<?php

namespace App\Services;

/**
 * Representación desacoplada de un correo de la bandeja (IMAP o fixtures de test).
 */
readonly class InboxMessage
{
    /**
     * @param  array<string, string>  $headers  Cabeceras relevantes en minúsculas.
     */
    public function __construct(
        public string $fromAddress,
        public string $subject,
        public string $body,
        public array $headers = [],
        public string $fromName = '',
    ) {
    }

    /**
     * Texto combinado asunto + cuerpo para búsquedas simples.
     */
    public function textoCompleto(): string
    {
        return $this->subject."\n".$this->body;
    }
}
