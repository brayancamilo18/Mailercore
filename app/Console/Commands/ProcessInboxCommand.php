<?php

namespace App\Console\Commands;

use App\Services\InboxMessage;
use App\Services\InboxProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;
use Webklex\PHPIMAP\Message;

class ProcessInboxCommand extends Command
{
    protected $signature = 'outreach:process-inbox
                            {--dry-run : Clasifica sin marcar como leído ni persistir cambios}';

    protected $description = 'Lee correos no leídos por IMAP y procesa rebotes, bajas y respuestas';

    public function handle(InboxProcessor $processor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $folderName = (string) env('IMAP_FOLDER', 'INBOX');

        $counts = [
            'procesados' => 0,
            'rebote' => 0,
            'baja' => 0,
            'respondido' => 0,
            'ignorado' => 0,
            'errores' => 0,
        ];

        try {
            $client = Client::account('default');
            $client->connect();
            $folder = $client->getFolder($folderName);

            if ($folder === null) {
                $this->error("No se encontró la carpeta IMAP: {$folderName}");

                return self::FAILURE;
            }

            // leaveUnread: marcamos Seen solo tras procesar con éxito.
            $messages = $folder->query()->unseen()->leaveUnread()->get();
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar al buzón IMAP: '.$e->getMessage());
            Log::error('outreach:process-inbox conexión fallida', ['error' => $e->getMessage()]);
            report($e);

            return self::FAILURE;
        }

        foreach ($messages as $message) {
            /** @var Message $message */
            try {
                $inboxMessage = $this->mapMessage($message);

                if ($dryRun) {
                    $this->line("DRY: {$inboxMessage->fromAddress} — {$inboxMessage->subject}");
                    $counts['procesados']++;

                    continue;
                }

                $resultado = $processor->process($inboxMessage);
                $counts[$resultado] = ($counts[$resultado] ?? 0) + 1;
                $counts['procesados']++;

                $message->setFlag('Seen');

                $this->line("✓ [{$resultado}] {$inboxMessage->fromAddress} — {$inboxMessage->subject}");
            } catch (\Throwable $e) {
                $counts['errores']++;
                $this->error('Error procesando mensaje: '.$e->getMessage());
                Log::warning('outreach:process-inbox mensaje fallido', [
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        }

        try {
            $client->disconnect();
        } catch (\Throwable) {
            // Ignorar fallos al cerrar.
        }

        $resumen = sprintf(
            'Inbox: %d procesados (%d rebote, %d baja, %d respondido, %d ignorado, %d errores).',
            $counts['procesados'],
            $counts['rebote'],
            $counts['baja'],
            $counts['respondido'],
            $counts['ignorado'],
            $counts['errores'],
        );

        $this->info($resumen);
        Log::info('outreach:process-inbox '.$resumen, $counts);

        return self::SUCCESS;
    }

    /**
     * Convierte un Message de Webklex en el DTO interno.
     */
    private function mapMessage(Message $message): InboxMessage
    {
        $fromAddress = '';
        $fromName = '';

        $from = $message->getFrom();

        if ($from !== null && $from->count() > 0) {
            $first = $from->first();
            $fromAddress = (string) ($first->mail ?? '');
            $fromName = (string) ($first->personal ?? '');
        }

        $subject = (string) $message->getSubject();
        $body = (string) ($message->getTextBody() ?: $message->getHTMLBody() ?: '');

        $headers = [];

        foreach (['x-failed-recipients', 'content-type', 'auto-submitted'] as $name) {
            try {
                $header = $message->getHeader()->get($name);

                if ($header !== null) {
                    $headers[$name] = is_array($header)
                        ? implode(', ', $header)
                        : (string) $header;
                }
            } catch (\Throwable) {
                // Cabecera ausente.
            }
        }

        try {
            $raw = (string) $message->getHeader()->raw;

            if ($raw !== '' && preg_match('/^X-Failed-Recipients:\s*(.+)$/mi', $raw, $m)) {
                $headers['x-failed-recipients'] = trim($m[1]);
            }

            if ($raw !== '' && preg_match('/^Content-Type:\s*(.+)$/mi', $raw, $m)) {
                $headers['content-type'] = trim($m[1]);
            }
        } catch (\Throwable) {
            // Sin raw.
        }

        return new InboxMessage(
            fromAddress: $fromAddress,
            subject: $subject,
            body: strip_tags($body),
            headers: $headers,
            fromName: $fromName,
        );
    }
}
