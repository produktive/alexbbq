<?php

test('web app manifest uses the configured application name', function () {
    config(['app.name' => 'Alex.bbq']);

    $response = $this->get(route('manifest'));

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/manifest+json');

    expect($response->json())
        ->name->toBe('Alex.bbq')
        ->short_name->toBe('Alex.bbq')
        ->display->toBe('standalone')
        ->and($response->json('icons'))->toHaveCount(4);

    expect($response->json('background_color'))->toBe([
        ['color' => '#ffffff', 'media' => '(prefers-color-scheme: light)'],
        ['color' => '#18181b', 'media' => '(prefers-color-scheme: dark)'],
    ]);

    expect($response->json('theme_color'))->toBe([
        ['color' => '#18181b', 'media' => '(prefers-color-scheme: light)'],
        ['color' => '#18181b', 'media' => '(prefers-color-scheme: dark)'],
    ]);
});

test('web app manifest short name can be overridden', function () {
    config([
        'app.name' => 'Alex.bbq',
        'pwa.short_name' => 'BBQ',
    ]);

    $this->get(route('manifest'))
        ->assertSuccessful()
        ->assertJsonPath('short_name', 'BBQ');
});

test('web app manifest dark colors can be overridden', function () {
    config([
        'pwa.dark_background_color' => '#0a0a0a',
        'pwa.dark_theme_color' => '#262626',
    ]);

    $response = $this->get(route('manifest'));

    expect($response->json('background_color'))->toBe([
        ['color' => '#ffffff', 'media' => '(prefers-color-scheme: light)'],
        ['color' => '#0a0a0a', 'media' => '(prefers-color-scheme: dark)'],
    ]);

    expect($response->json('theme_color'))->toBe([
        ['color' => '#18181b', 'media' => '(prefers-color-scheme: light)'],
        ['color' => '#262626', 'media' => '(prefers-color-scheme: dark)'],
    ]);
});
