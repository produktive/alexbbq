<?php

namespace App\Console\Commands;

use App\Models\Cook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cook:sync-ended-at {--only-missing : Only update cooks without an ended_at timestamp}')]
#[Description('Set cook ended_at timestamps from each cook\'s last reading')]
class SyncCookEndedAt extends Command
{
    public function handle(): int
    {
        $updated = Cook::syncAllEndedAtFromReadings($this->option('only-missing'));

        $this->components->info("Updated {$updated} cook(s).");

        return self::SUCCESS;
    }
}
