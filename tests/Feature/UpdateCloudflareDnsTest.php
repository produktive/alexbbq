<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $stateFile = storage_path('framework/testing/cloudflare-ddns.json');

    if (is_file($stateFile)) {
        unlink($stateFile);
    }

    if (! is_dir(dirname($stateFile))) {
        mkdir(dirname($stateFile), 0777, true);
    }

    config([
        'cloudflare.api_token' => 'test-token',
        'cloudflare.zone_id' => 'zone123',
        'cloudflare.dns_record' => 'bbq.fiskkarta.com',
        'cloudflare.ip_check_url' => 'https://api.ipify.org',
        'cloudflare.state_file' => $stateFile,
    ]);
});

function fakeCloudflareDdnsHttp(string $publicIp, string $dnsIp = '198.51.100.10', string $recordId = 'record123'): void
{
    Http::fake(function ($request) use ($publicIp, $dnsIp, $recordId) {
        if ($request->url() === 'https://api.ipify.org') {
            return Http::response($publicIp);
        }

        if ($request->method() === 'GET' && str_contains($request->url(), '/dns_records')) {
            return Http::response([
                'success' => true,
                'result' => [[
                    'id' => $recordId,
                    'type' => 'A',
                    'name' => 'bbq.fiskkarta.com',
                    'content' => $dnsIp,
                    'proxied' => true,
                ]],
            ]);
        }

        if ($request->method() === 'PATCH' && str_contains($request->url(), '/dns_records/'.$recordId)) {
            return Http::response([
                'success' => true,
                'result' => [
                    'id' => $recordId,
                    'content' => json_decode($request->body(), true)['content'] ?? null,
                ],
            ]);
        }

        return null;
    });
}

test('cloudflare update dns skips cloudflare when stored ip is unchanged', function () {
    file_put_contents(config('cloudflare.state_file'), json_encode([
        'ip' => '203.0.113.10',
        'updated_at' => '2026-06-24T12:00:00+00:00',
    ]));

    Http::fake([
        'https://api.ipify.org' => Http::response('203.0.113.10'),
    ]);

    $this->artisan('cloudflare:update-dns')
        ->expectsOutputToContain('Public IP unchanged at 203.0.113.10.')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

test('cloudflare update dns patches record when ip changes', function () {
    file_put_contents(config('cloudflare.state_file'), json_encode([
        'ip' => '198.51.100.10',
        'updated_at' => '2026-06-24T12:00:00+00:00',
    ]));

    fakeCloudflareDdnsHttp(publicIp: '203.0.113.10', dnsIp: '198.51.100.10');

    $this->artisan('cloudflare:update-dns')
        ->expectsOutputToContain('Updated bbq.fiskkarta.com from 198.51.100.10 to 203.0.113.10.')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && $request->url() === 'https://api.cloudflare.com/client/v4/zones/zone123/dns_records/record123'
            && $request->data() === ['content' => '203.0.113.10'];
    });

    expect(json_decode(file_get_contents(config('cloudflare.state_file')), true))
        ->ip->toBe('203.0.113.10');
});

test('cloudflare update dns syncs state without patch when dns already matches on first run', function () {
    fakeCloudflareDdnsHttp(publicIp: '203.0.113.10', dnsIp: '203.0.113.10');

    $this->artisan('cloudflare:update-dns')
        ->expectsOutputToContain('DNS already points to 203.0.113.10')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() !== 'PATCH';
    });

    expect(json_decode(file_get_contents(config('cloudflare.state_file')), true))
        ->ip->toBe('203.0.113.10');
});

test('cloudflare update dns dry run does not patch or write state', function () {
    fakeCloudflareDdnsHttp(publicIp: '203.0.113.10', dnsIp: '198.51.100.10');

    $this->artisan('cloudflare:update-dns --dry-run')
        ->expectsOutputToContain('Would update bbq.fiskkarta.com from 198.51.100.10 to 203.0.113.10.')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() !== 'PATCH';
    });

    expect(config('cloudflare.state_file'))->not->toBeFile();
});

test('cloudflare update dns fails when api token is missing', function () {
    config(['cloudflare.api_token' => null]);

    Http::fake([
        'https://api.ipify.org' => Http::response('203.0.113.10'),
    ]);

    $this->artisan('cloudflare:update-dns')
        ->expectsOutputToContain('CLOUDFLARE_API_TOKEN is not configured.')
        ->assertFailed();
});

test('cloudflare update dns fails when public ip lookup is invalid', function () {
    Http::fake([
        'https://api.ipify.org' => Http::response('not-an-ip'),
    ]);

    $this->artisan('cloudflare:update-dns')
        ->expectsOutputToContain('invalid IPv4 address')
        ->assertFailed();
});

test('cloudflare update dns force option updates even when stored ip matches', function () {
    file_put_contents(config('cloudflare.state_file'), json_encode([
        'ip' => '203.0.113.10',
        'updated_at' => '2026-06-24T12:00:00+00:00',
    ]));

    fakeCloudflareDdnsHttp(publicIp: '203.0.113.10', dnsIp: '203.0.113.10');

    $this->artisan('cloudflare:update-dns --force')
        ->expectsOutputToContain('Updated bbq.fiskkarta.com')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH';
    });
});
