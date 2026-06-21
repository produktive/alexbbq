<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Models\Reading;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:normalize-legacy-reading-times {--from=America/New_York : Timezone the legacy timestamps were recorded in} {--skip-cooks : Only normalize readings.time} {--dry-run : Show what would change without updating rows} {--force : Update rows without prompting}')]
#[Description('Convert legacy local cook and reading timestamps to UTC storage')]
class NormalizeLegacyReadingTimes extends Command
{
    public function handle(): int
    {
        $timezone = (string) $this->option('from');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force') && ! $this->confirm("Convert legacy timestamps from {$timezone} to UTC? This should only be run once.")) {
            $this->warn('No changes made.');

            return self::SUCCESS;
        }

        $cookCount = $this->option('skip-cooks') ? 0 : $this->normalizeCooks($timezone, $dryRun);
        $readingCount = $this->normalizeReadings($timezone, $dryRun);

        $this->info($dryRun
            ? "Would normalize {$cookCount} cook timestamps and {$readingCount} reading timestamps."
            : "Normalized {$cookCount} cook timestamps and {$readingCount} reading timestamps.");

        return self::SUCCESS;
    }

    private function normalizeCooks(string $timezone, bool $dryRun): int
    {
        $count = 0;
        $examples = [];

        Cook::query()
            ->orderBy('id')
            ->chunkById(500, function ($cooks) use ($timezone, $dryRun, &$count, &$examples) {
                foreach ($cooks as $cook) {
                    $original = $cook->getRawOriginal('created_at');
                    $utc = $this->localTimeToUtc($original, $timezone);

                    if (count($examples) < 5) {
                        $examples[] = [$cook->id, $original, $utc];
                    }

                    if (! $dryRun) {
                        DB::table('cooks')
                            ->whereKey($cook->id)
                            ->update(['created_at' => $utc]);
                    }

                    $count++;
                }
            });

        if ($examples !== []) {
            $this->line('Cook created_at examples:');
            $this->table(['ID', 'Before', 'After UTC'], $examples);
        }

        return $count;
    }

    private function normalizeReadings(string $timezone, bool $dryRun): int
    {
        $count = 0;
        $examples = [];

        Reading::query()
            ->orderBy('id')
            ->chunkById(500, function ($readings) use ($timezone, $dryRun, &$count, &$examples) {
                foreach ($readings as $reading) {
                    $original = $reading->getRawOriginal('time');
                    $utc = $this->localTimeToUtc($original, $timezone);

                    if (count($examples) < 5) {
                        $examples[] = [$reading->id, $original, $utc];
                    }

                    if (! $dryRun) {
                        DB::table('readings')
                            ->whereKey($reading->id)
                            ->update(['time' => $utc]);
                    }

                    $count++;
                }
            });

        if ($examples !== []) {
            $this->line('Reading time examples:');
            $this->table(['ID', 'Before', 'After UTC'], $examples);
        }

        return $count;
    }

    private function localTimeToUtc(string $time, string $timezone): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $time, $timezone)
            ->utc()
            ->format('Y-m-d H:i:s');
    }
}
