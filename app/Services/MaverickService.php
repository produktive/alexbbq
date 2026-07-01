<?php

namespace App\Services;

use App\Models\Cook;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

class MaverickService
{
    private static ?bool $runningCache = null;

    public function isAvailable(): bool
    {
        return is_file($this->scriptPath());
    }

    public function isRunning(): bool
    {
        if (self::$runningCache === null) {
            self::$runningCache = $this->probeRunning();
        }

        return self::$runningCache;
    }

    public static function forgetRunningCache(): void
    {
        self::$runningCache = null;
    }

    public function start(): bool
    {
        if (! $this->runScript('start')->successful()) {
            return false;
        }

        self::forgetRunningCache();

        return $this->waitUntilRunning();
    }

    protected function waitUntilRunning(int $attempts = 10, int $intervalMicroseconds = 50_000): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            if ($this->probeRunning()) {
                self::$runningCache = true;

                return true;
            }

            usleep($intervalMicroseconds);
        }

        return false;
    }

    public function stop(): bool
    {
        if (! $this->runScript('stop')->successful()) {
            return false;
        }

        self::forgetRunningCache();

        if (! $this->waitUntilStopped()) {
            return false;
        }

        return $this->finishActiveCook();
    }

    protected function waitUntilStopped(int $attempts = 10, int $intervalMicroseconds = 50_000): bool
    {
        for ($i = 0; $i < $attempts; $i++) {
            if (! $this->probeRunning()) {
                self::$runningCache = false;

                return true;
            }

            usleep($intervalMicroseconds);
        }

        return false;
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

        Cook::flushRequestCache();

        LiveCookBroadcast::stopped($cookId);

        return true;
    }

    protected function probeRunning(): bool
    {
        return $this->runScript('status')->successful();
    }

    public function logPath(): string
    {
        return (string) config('maverick.log_file');
    }

    public function phpBinary(): string
    {
        $configured = config('maverick.php_binary');

        if ($this->isCliPhpBinary($configured)) {
            return $configured;
        }

        $siblingCli = $this->siblingCliPhpBinary();

        if ($siblingCli !== null) {
            return $siblingCli;
        }

        $herdPhp = $this->herdPhpBinary();

        if ($herdPhp !== null) {
            return $herdPhp;
        }

        if (defined('PHP_BINARY') && $this->isCliPhpBinary(PHP_BINARY)) {
            return PHP_BINARY;
        }

        return 'php';
    }

    protected function isCliPhpBinary(?string $path): bool
    {
        return filled($path)
            && is_executable($path)
            && ! str_contains(basename($path), 'fpm');
    }

    protected function siblingCliPhpBinary(): ?string
    {
        if (! defined('PHP_BINARY')) {
            return null;
        }

        $directory = dirname(PHP_BINARY);
        $basename = basename(PHP_BINARY);

        if (preg_match('/^php(\d+)-fpm$/', $basename, $matches) !== 1) {
            return null;
        }

        $candidate = $directory.'/php'.$matches[1];

        return $this->isCliPhpBinary($candidate) ? $candidate : null;
    }

    protected function herdPhpBinary(): ?string
    {
        $home = getenv('HOME') ?: null;

        if ($home === null) {
            return null;
        }

        $path = $home.'/Library/Application Support/Herd/bin/php';

        return is_executable($path) ? $path : null;
    }

    protected function runScript(string $action): ProcessResult
    {
        $process = Process::path(base_path())->env([
            'MAVERICK_PHP' => $this->phpBinary(),
        ]);

        return $process->run($this->sudoCommand($action));
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
        $command = sprintf(
            '%s %s',
            escapeshellarg($this->scriptPath()),
            $action,
        );

        if (! config('maverick.use_sudo')) {
            return $command;
        }

        return sprintf('sudo -n %s', $command);
    }
}
