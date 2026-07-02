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
        ->background_color->toBe('#1f1f1f')
        ->theme_color->toBe('#1f1f1f')
        ->and($response->json('icons'))->toHaveCount(3);
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

test('web app manifest icons include cache busting versions', function () {
    $response = $this->get(route('manifest'));

    foreach ($response->json('icons') as $icon) {
        expect($icon['src'])->toContain('?v=');
    }
});

test('web app manifest colors can be overridden', function () {
    config([
        'pwa.background_color' => '#001133',
        'pwa.theme_color' => '#001133',
    ]);

    $response = $this->get(route('manifest'));

    expect($response->json())
        ->background_color->toBe('#001133')
        ->theme_color->toBe('#001133');
});
