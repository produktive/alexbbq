<?php

namespace App\Console\Commands;

use App\Services\CloudflareDdnsResult;
use App\Services\CloudflareDdnsService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Keep a Cloudflare A record in sync with this server's public IPv4 address.
 *
 * Scheduled every five minutes in routes/console.php. On production, add one
 * cron entry (see Laravel scheduling docs):
 *   * * * * * cd /path/to/alexbbq && php artisan schedule:run >> /dev/null 2>&1
 *
 * Manual verification on the Pi:
 *   php artisan cloudflare:update-dns --dry-run
 *   php artisan cloudflare:update-dns
 *   php artisan schedule:list
 */
#[Signature('cloudflare:update-dns {--dry-run : Show what would change without calling Cloudflare} {--force : Update even if the stored IP already matches}')]
#[Description('Update Cloudflare DNS when the public IPv4 address changes')]
class UpdateCloudflareDns extends Command
{
    public function handle(CloudflareDdnsService $ddns): int
    {
        try {
            $result = $ddns->update(
                dryRun: (bool) $this->option('dry-run'),
                force: (bool) $this->option('force'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderResult($result);

        return self::SUCCESS;
    }

    private function renderResult(CloudflareDdnsResult $result): void
    {
        match ($result->status) {
            CloudflareDdnsResult::STATUS_UNCHANGED => $this->line("Public IP unchanged at {$result->ip}."),
            CloudflareDdnsResult::STATUS_SYNCED => $this->line("DNS already points to {$result->ip}; local state synced for {$result->recordName}."),
            CloudflareDdnsResult::STATUS_WOULD_UPDATE => $this->line(sprintf(
                'Would update %s from %s to %s.',
                $result->recordName,
                $result->previousIp ?? '(unknown)',
                $result->ip,
            )),
            CloudflareDdnsResult::STATUS_UPDATED => $this->info(sprintf(
                'Updated %s from %s to %s.',
                $result->recordName,
                $result->previousIp ?? '(unknown)',
                $result->ip,
            )),
        };
    }
}
