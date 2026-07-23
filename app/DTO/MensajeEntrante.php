<?php

namespace App\DTO;

readonly class MensajeEntrante
{
    /**
     * @param  array<string, string>  $cabeceras
     */
    public function __construct(
        public string $desdeEmail,
        public string $desdeNombre,
        public string $asunto,
        public string $cuerpo,
        public array $cabeceras,
        public ?string $messageId,
        public ?string $inReplyTo,
        public ?string $references,
        public \DateTimeInterface $recibidoAt,
        public string $rawHash,
    ) {}

    public function textoCompleto(): string
    {
        return $this->asunto."\n".$this->cuerpo;
    }
}
