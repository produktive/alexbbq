<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

#[Signature('reverb:configure {--show : Display the credentials instead of modifying .env} {--force : Regenerate credentials even when already set} {--local : Apply php artisan serve Reverb defaults for empty or legacy .env values}')]
#[Description('Generate Reverb application credentials in .env when missing or empty')]
class ConfigureReverbCredentials extends Command
{
    /**
     * Defaults for local development with `php artisan serve` and `php artisan reverb:start`.
     *
     * @var array<string, string>
     */
    private const LOCAL_DEFAULTS = [
        'APP_URL' => 'http://127.0.0.1:8000',
        'BROADCAST_CONNECTION' => 'reverb',
        'REVERB_HOST' => '127.0.0.1',
        'REVERB_PORT' => '8080',
        'REVERB_SCHEME' => 'http',
    ];

    /**
     * Values copied from older .env.example files that break local broadcasting.
     *
     * @var array<string, list<string>>
     */
    private const LEGACY_LOCAL_VALUES = [
        'APP_URL' => ['http://localhost', 'http://localhost:8000'],
        'BROADCAST_CONNECTION' => ['null'],
        'REVERB_HOST' => ['localhost'],
        'REVERB_PORT' => ['8081', '443'],
        'REVERB_SCHEME' => ['https'],
    ];

    public function handle(): int
    {
        $credentials = [
            'REVERB_APP_ID' => (string) random_int(100_000, 999_999),
            'REVERB_APP_KEY' => Str::lower(Str::random(20)),
            'REVERB_APP_SECRET' => Str::lower(Str::random(20)),
        ];

        if ($this->option('show')) {
            foreach ($credentials as $key => $value) {
                $this->line("<comment>{$key}={$value}</comment>");
            }

            if ($this->option('local')) {
                foreach (self::LOCAL_DEFAULTS as $key => $value) {
                    $this->line("<comment>{$key}={$value}</comment>");
                }
            }

            return self::SUCCESS;
        }

        $envPath = $this->laravel->environmentFilePath();

        if (! is_file($envPath)) {
            $this->error('No .env file found.');

            return self::FAILURE;
        }

        $contents = file_get_contents($envPath);
        $toWrite = [];

        foreach ($credentials as $key => $value) {
            if ($this->shouldSkipKey($contents, $key)) {
                continue;
            }

            $toWrite[$key] = $value;
        }

        if ($this->option('local')) {
            foreach (self::LOCAL_DEFAULTS as $key => $value) {
                if ($this->shouldSkipLocalKey($contents, $key)) {
                    continue;
                }

                $toWrite[$key] = $value;
            }
        }

        if ($toWrite === []) {
            $this->components->info('Reverb configuration already up to date.');

            return self::SUCCESS;
        }

        Env::writeVariables(
            $toWrite,
            $envPath,
            overwrite: $this->option('force')
                || ($this->option('local') && array_intersect_key($toWrite, self::LOCAL_DEFAULTS) !== []),
        );

        if (array_intersect_key($toWrite, $credentials) !== []) {
            $this->components->info('Reverb credentials configured in .env.');
        }

        if ($this->option('local') && array_intersect_key($toWrite, self::LOCAL_DEFAULTS) !== []) {
            $this->components->info('Local Reverb connection defaults applied for php artisan serve.');
        }

        return self::SUCCESS;
    }

    protected function shouldSkipKey(string $contents, string $key): bool
    {
        if ($this->option('force')) {
            return false;
        }

        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return false;
        }

        return trim($matches[1], " \t\"'") !== '';
    }

    protected function shouldSkipLocalKey(string $contents, string $key): bool
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return false;
        }

        $current = trim($matches[1], " \t\"'");

        if ($current === '') {
            return false;
        }

        return ! in_array($current, self::LEGACY_LOCAL_VALUES[$key] ?? [], true);
    }
}
