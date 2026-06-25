<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CloudflareDdnsService
{
    private const API_BASE = 'https://api.cloudflare.com/client/v4';

    public function update(bool $dryRun = false, bool $force = false): CloudflareDdnsResult
    {
        $this->ensureConfigured();

        $currentIp = $this->fetchPublicIpv4();
        $storedState = $this->readState();

        if (! $force && ($storedState['ip'] ?? null) === $currentIp) {
            return CloudflareDdnsResult::unchanged($currentIp);
        }

        $record = $this->findDnsRecord();

        if (! $force && ($record['content'] ?? null) === $currentIp) {
            if (! $dryRun) {
                $this->writeState($currentIp);
            }

            return CloudflareDdnsResult::synced($currentIp, $record['name'] ?? config('cloudflare.dns_record'));
        }

        if ($dryRun) {
            return CloudflareDdnsResult::wouldUpdate(
                ip: $currentIp,
                recordName: $record['name'] ?? config('cloudflare.dns_record'),
                previousIp: $record['content'] ?? null,
            );
        }

        $this->patchDnsRecord($record['id'], $currentIp);
        $this->writeState($currentIp);

        Log::info('Cloudflare DNS record updated.', [
            'record' => $record['name'] ?? config('cloudflare.dns_record'),
            'ip' => $currentIp,
            'previous_ip' => $record['content'] ?? null,
        ]);

        return CloudflareDdnsResult::updated(
            ip: $currentIp,
            recordName: $record['name'] ?? config('cloudflare.dns_record'),
            previousIp: $record['content'] ?? null,
        );
    }

    private function ensureConfigured(): void
    {
        if (blank(config('cloudflare.api_token'))) {
            throw new RuntimeException('CLOUDFLARE_API_TOKEN is not configured.');
        }

        if (blank(config('cloudflare.zone_id'))) {
            throw new RuntimeException('CLOUDFLARE_ZONE_ID is not configured.');
        }

        if (blank(config('cloudflare.dns_record'))) {
            throw new RuntimeException('CLOUDFLARE_DNS_RECORD is not configured.');
        }
    }

    private function fetchPublicIpv4(): string
    {
        $url = (string) config('cloudflare.ip_check_url');

        try {
            $response = Http::timeout(10)
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->get($url)
                ->throw()
                ->body();
        } catch (RequestException $exception) {
            throw new RuntimeException("Unable to fetch public IP from [{$url}].", previous: $exception);
        }

        $ip = trim($response);

        if ($ip === '' && str_contains($response, 'ip=')) {
            preg_match('/^ip=(.+)$/m', $response, $matches);
            $ip = trim($matches[1] ?? '');
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException("Public IP lookup returned an invalid IPv4 address from [{$url}].");
        }

        return $ip;
    }

    /**
     * @return array{ip?: string, updated_at?: string}
     */
    private function readState(): array
    {
        $path = (string) config('cloudflare.state_file');

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $state = json_decode($contents, true);

        return is_array($state) ? $state : [];
    }

    private function writeState(string $ip): void
    {
        $path = (string) config('cloudflare.state_file');

        $payload = json_encode([
            'ip' => $ip,
            'updated_at' => Carbon::now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        if (file_put_contents($path, $payload.PHP_EOL) === false) {
            throw new RuntimeException("Unable to write DDNS state file at [{$path}].");
        }
    }

    /**
     * @return array{id: string, name: string, content: string, proxied?: bool}
     */
    private function findDnsRecord(): array
    {
        $zoneId = (string) config('cloudflare.zone_id');
        $recordName = (string) config('cloudflare.dns_record');

        try {
            $response = $this->client()
                ->get(self::API_BASE."/zones/{$zoneId}/dns_records", [
                    'type' => 'A',
                    'name' => $recordName,
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('Unable to fetch Cloudflare DNS records.', previous: $exception);
        }

        if (! ($response['success'] ?? false)) {
            throw new RuntimeException($this->formatCloudflareErrors($response['errors'] ?? []));
        }

        $record = $response['result'][0] ?? null;

        if (! is_array($record) || blank($record['id'] ?? null)) {
            throw new RuntimeException("No A record found for [{$recordName}].");
        }

        return $record;
    }

    private function patchDnsRecord(string $recordId, string $ip): void
    {
        $zoneId = (string) config('cloudflare.zone_id');

        try {
            $response = $this->client()
                ->patch(self::API_BASE."/zones/{$zoneId}/dns_records/{$recordId}", [
                    'content' => $ip,
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException('Unable to update Cloudflare DNS record.', previous: $exception);
        }

        if (! ($response['success'] ?? false)) {
            throw new RuntimeException($this->formatCloudflareErrors($response['errors'] ?? []));
        }
    }

    private function client()
    {
        return Http::timeout(15)
            ->acceptJson()
            ->withToken((string) config('cloudflare.api_token'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    private function formatCloudflareErrors(array $errors): string
    {
        if ($errors === []) {
            return 'Cloudflare API request failed.';
        }

        return collect($errors)
            ->map(fn (array $error): string => (string) ($error['message'] ?? 'Unknown Cloudflare API error'))
            ->implode(' ');
    }
}
