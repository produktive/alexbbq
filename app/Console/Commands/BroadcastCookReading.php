<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Services\CookBroadcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cook:broadcast-reading {reading : The ID of the reading to broadcast}')]
#[Description('Broadcast a websocket update after maverick inserts a reading')]
class BroadcastCookReading extends Command
{
    public function handle(CookBroadcastService $broadcasts): int
    {
        $reading = Reading::query()->find($this->argument('reading'));

        if ($reading === null) {
            $this->components->error("Reading [{$this->argument('reading')}] not found.");

            return self::FAILURE;
        }

        $broadcasts->readingAdded($reading);

        return self::SUCCESS;
    }
}
