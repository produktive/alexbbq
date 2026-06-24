<?php

namespace App\Services;

use App\Models\Cook;
use Illuminate\Support\Facades\Process;

class MaverickService
{
    public function isAvailable(): bool
    {
        if (! is_file($this->binaryPath())) {
            return false;
        }

        if ($this->usesSudo()) {
            return is_file($this->scriptPath());
        }

        return is_executable($this->binaryPath());
    }

    public function isRunning(): bool
    {
        if ($this->usesSudo()) {
            return Process::run($this->sudoCommand('status'))->successful();
        }

        return Process::run('pgrep -x maverick')->successful();
    }

    public function start(): bool
    {
        if ($this->usesSudo()) {
            return Process::path(base_path())
                ->run($this->sudoCommand('start'))
                ->successful();
        }

        Process::path(base_path())->start($this->binaryPath());

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
        if ($this->usesSudo()) {
            Process::run($this->sudoCommand('stop'));
        } else {
            Process::run('pkill -x maverick');
        }

        return $this->finishActiveCook();
    }

    public function finishActiveCook(): bool
    {
        $cook = Cook::active();

        if ($cook === null) {
            return true;
        }

        if (! $cook->syncEndedAtFromReadings()) {
            $cook->ended_at = now();
            $cook->saveQuietly();
        }

        return true;
    }

    public function usesSudo(): bool
    {
        return (bool) config('maverick.use_sudo', false);
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
