<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Services\CookBroadcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cook:broadcast-ended {cook : The ID of the cook that ended}')]
#[Description('Broadcast a websocket update when a live cook finishes')]
class BroadcastCookEnded extends Command
{
    public function handle(CookBroadcastService $broadcasts): int
    {
        $cook = Cook::query()->find($this->argument('cook'));

        if ($cook === null) {
            $this->components->error("Cook [{$this->argument('cook')}] not found.");

            return self::FAILURE;
        }

        if ($cook->isActive()) {
            $this->components->error("Cook [{$cook->id}] is still active.");

            return self::FAILURE;
        }

        $broadcasts->cookEnded($cook);

        return self::SUCCESS;
    }
}
