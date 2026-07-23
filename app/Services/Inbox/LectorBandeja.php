<?php

namespace App\Services\Inbox;

use App\DTO\ItemBandeja;

interface LectorBandeja
{
    /**
     * Conecta y devuelve los mensajes no leídos (sin marcarlos como leídos).
     *
     * @return list<ItemBandeja>
     *
     * @throws \Throwable
     */
    public function leerNoLeidos(int $limite): array;

    /** Marca el mensaje como visto en el servidor. */
    public function marcarVisto(string $id): void;
}
