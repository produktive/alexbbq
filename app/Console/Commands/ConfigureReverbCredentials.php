<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

#[Signature('reverb:configure {--show : Display the credentials instead of modifying .env} {--force : Regenerate credentials even when already set}')]
#[Description('Generate Reverb application credentials in .env when missing or empty')]
class ConfigureReverbCredentials extends Command
{
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

        if ($toWrite === []) {
            $this->components->info('Reverb credentials already configured.');

            return self::SUCCESS;
        }

        Env::writeVariables($toWrite, $envPath, overwrite: $this->option('force'));

        $this->components->info('Reverb credentials configured in .env.');

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
}
