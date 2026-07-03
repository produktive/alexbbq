<?php

namespace App\Services;

use App\Events\LiveCookUpdated;
use App\Models\Cook;
use Illuminate\Support\Facades\Log;

class LiveCookBroadcast
{
    public static function started(Cook $cook, bool $maverickRunning = true): void
    {
        self::send(LiveCookUpdated::started($cook, $maverickRunning));
    }

    public static function stopped(int $cookId): void
    {
        self::send(LiveCookUpdated::stopped($cookId));
    }

    public static function reading(int $cookId, ?string $beganAt = null): void
    {
        self::send(LiveCookUpdated::reading($cookId, $beganAt));
    }

    private static function send(LiveCookUpdated $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable $e) {
            Log::warning('Live cook broadcast failed.', [
                'type' => $event->type,
                'cook_id' => $event->cookId,
                'message' => $e->getMessage(),
            ]);

            if (app()->environment('local')) {
                report($e);
            }
        }
    }
}
