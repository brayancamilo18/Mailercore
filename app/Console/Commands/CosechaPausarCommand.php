<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CosechaPausarCommand extends Command
{
    protected $signature = 'cosecha:pausar';

    protected $description = 'Pausa la cosecha automática';

    public function handle(): int
    {
        Cache::forever('cosecha:activa', false);
        $this->info('Cosecha pausada.');

        return self::SUCCESS;
    }
}
