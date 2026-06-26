<?php

namespace App\Services;

use App\Events\LiveCookUpdated;
use App\Models\Cook;
use Illuminate\Support\Facades\Process;

class MaverickService
{
    public function isAvailable(): bool
    {
        return is_file($this->scriptPath());
    }

    public function isRunning(): bool
    {
        return Process::run($this->sudoCommand('status'))->successful();
    }

    public function start(): bool
    {
        if (! Process::path(base_path())->run($this->sudoCommand('start'))->successful()) {
            return false;
        }

        return $this->waitUntilRunning();
    }

    protected function waitUntilRunning(int $attempts = 10, int $intervalMicroseconds = 50_000): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            if ($this->isRunning()) {
                return true;
            }

            usleep($intervalMicroseconds);
        }

        return false;
    }

    public function stop(): bool
    {
        Process::run($this->sudoCommand('stop'));

        return $this->finishActiveCook();
    }

    public function finishActiveCook(): bool
    {
        $cook = Cook::active();

        if ($cook === null) {
            return true;
        }

        $cookId = $cook->id;

        if (! $cook->syncEndedAtFromReadings()) {
            $cook->ended_at = now();
            $cook->saveQuietly();
        }

        broadcast(LiveCookUpdated::stopped($cookId));

        return true;
    }

    public function binaryPath(): string
    {
        return (string) config('maverick.binary');
    }

    public function scriptPath(): string
    {
        return (string) config('maverick.script');
    }

    protected function sudoCommand(string $action): string
    {
        return sprintf(
            'sudo -n %s %s',
            escapeshellarg($this->scriptPath()),
            $action,
        );
    }
}
