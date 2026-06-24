<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Services\TemperatureAlertService;
use Illuminate\Console\Command;

class EvaluateCookAlerts extends Command
{
    protected $signature = 'cook:evaluate-alerts {reading : The ID of the reading to evaluate}';

    protected $description = 'Evaluate temperature alert thresholds for a reading';

    public function handle(TemperatureAlertService $alerts): int
    {
        $reading = Reading::query()->find($this->argument('reading'));

        if ($reading === null) {
            $this->error('Reading not found.');

            return self::FAILURE;
        }

        $alerts->evaluate($reading);

        return self::SUCCESS;
    }
}
