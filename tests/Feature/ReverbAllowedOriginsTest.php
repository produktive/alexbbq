<?php

test('local reverb origins include localhost alias for 127.0.0.1 app url', function () {
    $_ENV['APP_ENV'] = 'local';
    $_SERVER['APP_ENV'] = 'local';
    $_ENV['APP_URL'] = 'http://127.0.0.1:8000';
    $_SERVER['APP_URL'] = 'http://127.0.0.1:8000';
    $_ENV['REVERB_ALLOWED_ORIGINS'] = '';
    $_SERVER['REVERB_ALLOWED_ORIGINS'] = '';
    putenv('APP_ENV=local');
    putenv('APP_URL=http://127.0.0.1:8000');
    putenv('REVERB_ALLOWED_ORIGINS');

    config()->set('reverb', require config_path('reverb.php'));

    expect(config('reverb.apps.apps.0.allowed_origins'))
        ->toContain('http://127.0.0.1:8000', 'http://localhost:8000');
});
