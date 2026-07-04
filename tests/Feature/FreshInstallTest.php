<?php

use Illuminate\Support\Facades\Artisan;

test('composer setup flow produces working local reverb configuration', function () {
    $directory = sys_get_temp_dir().'/alexbbq-fresh-install-'.uniqid();
    mkdir($directory);

    $envExample = file_get_contents(base_path('.env.example'));
    $envPath = $directory.'/.env';

    file_put_contents($envPath, $envExample);

    app()->useEnvironmentPath($directory);

    Artisan::call('reverb:configure', ['--local' => true]);

    $contents = file_get_contents($envPath);

    expect($contents)
        ->toMatch('/^APP_URL=http:\/\/127\.0\.0\.1:8000$/m')
        ->toMatch('/^BROADCAST_CONNECTION=reverb$/m')
        ->toMatch('/^REVERB_HOST=127\.0\.0\.1$/m')
        ->toMatch('/^REVERB_PORT=8080$/m')
        ->toMatch('/^REVERB_SCHEME=http$/m')
        ->toMatch('/^REVERB_APP_ID=\d{6,}$/m')
        ->toMatch('/^REVERB_APP_KEY=[a-z0-9]{20}$/m')
        ->toMatch('/^REVERB_APP_SECRET=[a-z0-9]{20}$/m');

    $_ENV['APP_ENV'] = 'local';
    $_SERVER['APP_ENV'] = 'local';
    $_ENV['APP_URL'] = 'http://127.0.0.1:8000';
    $_SERVER['APP_URL'] = 'http://127.0.0.1:8000';
    $_ENV['BROADCAST_CONNECTION'] = 'reverb';
    $_SERVER['BROADCAST_CONNECTION'] = 'reverb';
    $_ENV['REVERB_HOST'] = '127.0.0.1';
    $_SERVER['REVERB_HOST'] = '127.0.0.1';
    $_ENV['REVERB_PORT'] = '8080';
    $_SERVER['REVERB_PORT'] = '8080';
    $_ENV['REVERB_SCHEME'] = 'http';
    $_SERVER['REVERB_SCHEME'] = 'http';
    $_ENV['REVERB_ALLOWED_ORIGINS'] = '';
    $_SERVER['REVERB_ALLOWED_ORIGINS'] = '';

    preg_match('/^REVERB_APP_KEY=(.+)$/m', $contents, $matches);
    $_ENV['REVERB_APP_KEY'] = trim($matches[1]);
    $_SERVER['REVERB_APP_KEY'] = trim($matches[1]);

    config()->set('broadcasting', require config_path('broadcasting.php'));
    config()->set('reverb', require config_path('reverb.php'));

    expect(config('broadcasting.default'))->toBe('reverb')
        ->and(config('broadcasting.connections.reverb.client.host'))->toBe('127.0.0.1')
        ->and(config('broadcasting.connections.reverb.client.port'))->toBe(8080)
        ->and(config('broadcasting.connections.reverb.client.scheme'))->toBe('http')
        ->and(config('broadcasting.connections.reverb.key'))->toMatch('/^[a-z0-9]{20}$/')
        ->and(config('reverb.apps.apps.0.allowed_origins'))
        ->toContain('127.0.0.1', 'localhost');
});
