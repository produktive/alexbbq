<?php

namespace App\Console\Commands;

use App\Services\LiveCookBroadcast;
use App\Models\Reading;
use App\Services\TemperatureAlertService;
use Illuminate\Console\Command;

class EvaluateCookAlerts extends Command
{
    protected $signature = 'cook:evaluate-alerts {reading : The ID of the reading to evaluate}';

    protected $description = 'Evaluate temperature alert thresholds for a reading';

    public function handle(TemperatureAlertService $alerts): int
    {
        $reading = Reading::query()->with('cook')->find($this->argument('reading'));

        if ($reading === null) {
            $this->error('Reading not found.');

            return self::FAILURE;
        }

        $alerts->evaluate($reading);

        $isFirstReading = ! Reading::query()
            ->where('cook_id', $reading->cook_id)
            ->where('id', '<', $reading->id)
            ->exists();

        LiveCookBroadcast::reading(
            $reading->cook_id,
            $isFirstReading ? $reading->cook->getBeganAt()?->toIso8601String() : null,
        );

        $this->info('Evaluated alerts for reading #'.$reading->id.'.');

        return self::SUCCESS;
    }
}
