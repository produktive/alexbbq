<?php

namespace App\Services;

use App\Events\CookReadingAdded;
use App\Events\LiveCookChanged;
use App\Models\Cook;
use App\Models\Reading;

class CookBroadcastService
{
    public function readingAdded(Reading $reading): void
    {
        $cook = $reading->relationLoaded('cook')
            ? $reading->cook
            : $reading->cook()->first();

        if ($cook === null) {
            return;
        }

        CookReadingAdded::dispatch($cook);
    }

    public function cookStarted(Cook $cook): void
    {
        LiveCookChanged::dispatch('started', $cook);
    }

    public function cookEnded(Cook $cook): void
    {
        LiveCookChanged::dispatch('ended', $cook);
    }
}
