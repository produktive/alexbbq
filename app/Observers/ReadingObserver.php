<?php

namespace App\Observers;

use App\Models\Reading;
use App\Services\CookBroadcastService;

class ReadingObserver
{
    public function __construct(private CookBroadcastService $broadcasts) {}

    public function created(Reading $reading): void
    {
        $this->broadcasts->readingAdded($reading);
    }
}
