<?php

use Illuminate\Support\Facades\Artisan;

test('reverb configure fills empty credentials in env file', function () {
    $directory = sys_get_temp_dir().'/alexbbq-reverb-'.uniqid();
    mkdir($directory);

    $envPath = $directory.'/.env';
    file_put_contents($envPath, implode(PHP_EOL, [
        'APP_KEY=base64:'.base64_encode(str_repeat('a', 32)),
        'REVERB_APP_ID=',
        'REVERB_APP_KEY=',
        'REVERB_APP_SECRET=',
    ]).PHP_EOL);

    app()->useEnvironmentPath($directory);

    Artisan::call('reverb:configure');

    $contents = file_get_contents($envPath);

    expect($contents)
        ->toMatch('/^REVERB_APP_ID=\d{6,}$/m')
        ->toMatch('/^REVERB_APP_KEY=[a-z0-9]{20}$/m')
        ->toMatch('/^REVERB_APP_SECRET=[a-z0-9]{20}$/m');
});

test('reverb configure leaves existing credentials unchanged', function () {
    $directory = sys_get_temp_dir().'/alexbbq-reverb-'.uniqid();
    mkdir($directory);

    $envPath = $directory.'/.env';
    file_put_contents($envPath, implode(PHP_EOL, [
        'APP_KEY=base64:'.base64_encode(str_repeat('a', 32)),
        'REVERB_APP_ID=123456',
        'REVERB_APP_KEY=existingkeyexistingkey',
        'REVERB_APP_SECRET=existingsecretexisting',
    ]).PHP_EOL);

    app()->useEnvironmentPath($directory);

    Artisan::call('reverb:configure');

    expect(file_get_contents($envPath))
        ->toContain('REVERB_APP_ID=123456')
        ->toContain('REVERB_APP_KEY=existingkeyexistingkey')
        ->toContain('REVERB_APP_SECRET=existingsecretexisting');
});
