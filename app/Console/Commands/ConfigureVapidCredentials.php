<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Minishlink\WebPush\VAPID;

#[Signature('webpush:configure {--show : Display the credentials instead of modifying .env} {--force : Regenerate credentials even when already set}')]
#[Description('Generate VAPID keys in .env when missing or empty')]
class ConfigureVapidCredentials extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $credentials = [
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
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

        if (! $this->option('force') && $this->credentialsConfigured($contents)) {
            $this->components->info('VAPID configuration already up to date.');

            return self::SUCCESS;
        }

        Env::writeVariables($credentials, $envPath, overwrite: true);

        $this->components->info('VAPID credentials configured in .env.');

        return self::SUCCESS;
    }

    protected function credentialsConfigured(string $contents): bool
    {
        foreach (['VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY'] as $key) {
            if ($this->envValue($contents, $key) === '') {
                return false;
            }
        }

        return true;
    }

    protected function envValue(string $contents, string $key): string
    {
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return '';
        }

        return trim($matches[1], " \t\"'");
    }
}
