<?php

use Illuminate\Support\Facades\Artisan;

test('webpush configure fills empty credentials in env file', function () {
    $directory = sys_get_temp_dir().'/alexbbq-vapid-'.uniqid();
    mkdir($directory);

    $envPath = $directory.'/.env';
    file_put_contents($envPath, implode(PHP_EOL, [
        'APP_KEY=base64:'.base64_encode(str_repeat('a', 32)),
        'VAPID_PUBLIC_KEY=',
        'VAPID_PRIVATE_KEY=',
    ]).PHP_EOL);

    app()->useEnvironmentPath($directory);

    Artisan::call('webpush:configure');

    $contents = file_get_contents($envPath);

    expect($contents)
        ->toMatch('/^VAPID_PUBLIC_KEY=["\']?[A-Za-z0-9_-]+["\']?$/m')
        ->toMatch('/^VAPID_PRIVATE_KEY=["\']?[A-Za-z0-9_-]+["\']?$/m');
});

test('webpush configure leaves existing credentials unchanged', function () {
    $directory = sys_get_temp_dir().'/alexbbq-vapid-'.uniqid();
    mkdir($directory);

    $envPath = $directory.'/.env';
    file_put_contents($envPath, implode(PHP_EOL, [
        'APP_KEY=base64:'.base64_encode(str_repeat('a', 32)),
        'VAPID_PUBLIC_KEY=existing-public-key',
        'VAPID_PRIVATE_KEY=existing-private-key',
    ]).PHP_EOL);

    app()->useEnvironmentPath($directory);

    Artisan::call('webpush:configure');

    expect(file_get_contents($envPath))
        ->toContain('VAPID_PUBLIC_KEY=existing-public-key')
        ->toContain('VAPID_PRIVATE_KEY=existing-private-key');
});

test('webpush configure force replaces existing credentials', function () {
    $directory = sys_get_temp_dir().'/alexbbq-vapid-'.uniqid();
    mkdir($directory);

    $envPath = $directory.'/.env';
    file_put_contents($envPath, implode(PHP_EOL, [
        'APP_KEY=base64:'.base64_encode(str_repeat('a', 32)),
        'VAPID_PUBLIC_KEY=existing-public-key',
        'VAPID_PRIVATE_KEY=existing-private-key',
    ]).PHP_EOL);

    app()->useEnvironmentPath($directory);

    Artisan::call('webpush:configure', ['--force' => true]);

    $contents = file_get_contents($envPath);

    expect($contents)
        ->not->toContain('VAPID_PUBLIC_KEY=existing-public-key')
        ->not->toContain('VAPID_PRIVATE_KEY=existing-private-key')
        ->toMatch('/^VAPID_PUBLIC_KEY=["\']?[A-Za-z0-9_-]+["\']?$/m')
        ->toMatch('/^VAPID_PRIVATE_KEY=["\']?[A-Za-z0-9_-]+["\']?$/m');
});
