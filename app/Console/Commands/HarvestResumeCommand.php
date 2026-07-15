<?php

namespace App\Console\Commands;

use App\Services\HarvestControl;
use Illuminate\Console\Command;

class HarvestResumeCommand extends Command
{
    protected $signature = 'harvest:resume';

    protected $description = 'Reanuda el recorrido automático de áreas';

    public function handle(): int
    {
        HarvestControl::resume();
        $this->info('Cosecha reanudada. El schedule / harvest:run volverá a tomar áreas pendientes.');

        return self::SUCCESS;
    }
}
