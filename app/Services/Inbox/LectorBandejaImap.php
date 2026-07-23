<?php

namespace App\Services\Inbox;

use App\DTO\ItemBandeja;
use App\DTO\MensajeEntrante;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Message;

class LectorBandejaImap implements LectorBandeja
{
    /** @var array<string, Message> */
    private array $mensajesPorId = [];

    public function leerNoLeidos(int $limite): array
    {
        // Client::account('default') ya carga config/imap.php; no hace falta setConfig.
        $cuenta = config('imap.accounts.default');
        $cliente = Client::account('default');
        $cliente->connect();

        $carpeta = $cliente->getFolder((string) ($cuenta['carpeta'] ?? 'INBOX'));
        $mensajes = $carpeta->query()->unseen()->leaveUnread()->limit($limite)->get();

        $items = [];
        $this->mensajesPorId = [];

        foreach ($mensajes as $mensaje) {
            $id = (string) $mensaje->getUid();
            $this->mensajesPorId[$id] = $mensaje;
            $items[] = new ItemBandeja($id, $this->aEntrante($mensaje));
        }

        return $items;
    }

    public function marcarVisto(string $id): void
    {
        $mensaje = $this->mensajesPorId[$id] ?? null;
        if ($mensaje === null) {
            return;
        }

        $mensaje->setFlag('Seen');
    }

    private function aEntrante(Message $mensaje): MensajeEntrante
    {
        $from = $mensaje->getFrom()->first();
        $desdeEmail = is_object($from) ? (string) ($from->mail ?? '') : '';
        $desdeNombre = is_object($from) ? (string) ($from->personal ?? '') : '';

        $texto = (string) ($mensaje->getTextBody() ?? '');
        if (trim($texto) === '') {
            $html = (string) ($mensaje->getHTMLBody() ?? '');
            $texto = $this->htmlATexto($html);
        }

        $raw = (string) $mensaje->getHeader()->raw;
        $cabeceras = $this->extraerCabeceras($raw, [
            'content-type',
            'x-failed-recipients',
            'auto-submitted',
            'in-reply-to',
            'references',
            'return-path',
        ]);

        $messageId = $mensaje->getMessageId();
        $messageId = is_string($messageId) && $messageId !== '' ? $messageId : null;

        $asunto = (string) $mensaje->getSubject();
        $recibidoAt = $mensaje->getDate()?->toDate() ?? now();

        $rawHash = sha1($messageId ?: ($asunto.$desdeEmail.$recibidoAt->format('c')));

        return new MensajeEntrante(
            desdeEmail: $desdeEmail,
            desdeNombre: $desdeNombre,
            asunto: $asunto,
            cuerpo: $texto,
            cabeceras: $cabeceras,
            messageId: $messageId,
            inReplyTo: $cabeceras['in-reply-to'] ?? null,
            references: $cabeceras['references'] ?? null,
            recibidoAt: $recibidoAt,
            rawHash: $rawHash,
        );
    }

    private function htmlATexto(string $html): string
    {
        $html = preg_replace('/<\/p>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        return trim(strip_tags($html));
    }

    /**
     * @param  list<string>  $nombres
     * @return array<string, string>
     */
    private function extraerCabeceras(string $raw, array $nombres): array
    {
        $resultado = [];

        foreach ($nombres as $nombre) {
            if (preg_match('/^'.preg_quote($nombre, '/').':\s*(.+)$/mi', $raw, $m)) {
                $resultado[strtolower($nombre)] = trim($m[1]);
            }
        }

        return $resultado;
    }
}
