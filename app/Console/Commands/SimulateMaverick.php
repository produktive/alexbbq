<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Models\Reading;
use App\Services\FakeMaverickSimulator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SimulateMaverick extends Command
{
    protected $signature = 'maverick:simulate {--once : Process one loop iteration and exit}';

    protected $description = 'Simulate Maverick probe readings for local development';

    private bool $shouldStop = false;

    public function handle(FakeMaverickSimulator $simulator): int
    {
        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->shouldStop = true;
        });

        $this->writePidFile();
        $interval = max(1, (int) config('maverick.fake.interval', 12));

        $this->info("Fake maverick running (interval: {$interval}s). Press Ctrl+C to stop.");

        while (! $this->shouldStop) {
            Cook::flushRequestCache();

            $cook = Cook::active();

            if ($cook === null) {
                if ($this->option('once')) {
                    break;
                }

                sleep(1);

                continue;
            }

            $previous = $cook->readings()->orderByDesc('time')->first();
            $temperatures = $simulator->nextReading($cook, $previous);

            $reading = Reading::withoutEvents(fn () => Reading::query()->create([
                'cook_id' => $cook->id,
                'time' => now(),
                'probe_food' => $temperatures['probe_food'],
                'probe_bbq' => $temperatures['probe_bbq'],
            ]));

            try {
                $exitCode = Artisan::call('cook:evaluate-alerts', ['reading' => $reading->id]);

                if ($exitCode !== self::SUCCESS) {
                    $this->warn(trim(Artisan::output()) ?: 'cook:evaluate-alerts failed.');
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error('Failed to evaluate alerts for reading #'.$reading->id.': '.$e->getMessage());
            }

            $this->line(sprintf(
                'Reading #%d: food %d°F, bbq %d°F',
                $reading->id,
                $temperatures['probe_food'],
                $temperatures['probe_bbq'],
            ));

            if ($this->option('once')) {
                break;
            }

            for ($elapsed = 0; $elapsed < $interval && ! $this->shouldStop; $elapsed++) {
                sleep(1);
            }
        }

        $this->removePidFile();

        return self::SUCCESS;
    }

    private function writePidFile(): void
    {
        $pidFile = (string) config('maverick.fake.pid_file');

        file_put_contents($pidFile, (string) getmypid());
    }

    private function removePidFile(): void
    {
        $pidFile = (string) config('maverick.fake.pid_file');

        if (is_file($pidFile) && trim((string) file_get_contents($pidFile)) === (string) getmypid()) {
            unlink($pidFile);
        }
    }
}
