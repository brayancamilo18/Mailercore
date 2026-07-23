<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CosechaReanudarCommand extends Command
{
    protected $signature = 'cosecha:reanudar';

    protected $description = 'Reanuda la cosecha automática';

    public function handle(): int
    {
        Cache::forever('cosecha:activa', true);
        $this->info('Cosecha reanudada.');

        return self::SUCCESS;
    }
}
