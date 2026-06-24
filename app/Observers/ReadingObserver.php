<?php

namespace App\Observers;

use App\Models\Reading;
use App\Services\TemperatureAlertService;

class ReadingObserver
{
    public function __construct(private TemperatureAlertService $alerts) {}

    public function created(Reading $reading): void
    {
        $this->alerts->evaluate($reading);
    }
}
