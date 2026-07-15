<?php

namespace App\Console\Commands;

use App\Services\HarvestControl;
use Illuminate\Console\Command;

class HarvestPauseCommand extends Command
{
    protected $signature = 'harvest:pause';

    protected $description = 'Pausa el recorrido automático de áreas (sin tocar .env)';

    public function handle(): int
    {
        HarvestControl::pause();
        $this->warn('Cosecha pausada. harvest:run no procesará áreas hasta harvest:resume.');

        return self::SUCCESS;
    }
}
