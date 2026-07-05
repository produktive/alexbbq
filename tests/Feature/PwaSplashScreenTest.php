<?php

test('ios splash screens include iphone 16 portrait image and media query', function () {
    $screens = json_decode(
        file_get_contents(resource_path('data/pwa-splash-screens.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $iphone16 = collect($screens)->first(
        fn (array $screen) => $screen['file'] === 'apple-splash-1179-2556.png',
    );

    expect($iphone16)->not->toBeNull()
        ->and($iphone16['media'])->toBe('(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)')
        ->and($iphone16['media'])->not->toContain('screen and')
        ->and(public_path('pwa-splash/v2/apple-splash-1179-2556.png'))->toBeFile();
});

test('home page includes generated ios splash screen tags', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('/pwa-splash/v2/apple-splash-1179-2556.png', false)
        ->assertSee('(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)', false);
});
