<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Services\CookBroadcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cook:broadcast-started {cook : The ID of the cook that started}')]
#[Description('Broadcast a websocket update when a live cook begins')]
class BroadcastCookStarted extends Command
{
    public function handle(CookBroadcastService $broadcasts): int
    {
        $cook = Cook::query()->find($this->argument('cook'));

        if ($cook === null) {
            $this->components->error("Cook [{$this->argument('cook')}] not found.");

            return self::FAILURE;
        }

        if (! $cook->isActive()) {
            $this->components->error("Cook [{$cook->id}] is not active.");

            return self::FAILURE;
        }

        $broadcasts->cookStarted($cook);

        return self::SUCCESS;
    }
}
