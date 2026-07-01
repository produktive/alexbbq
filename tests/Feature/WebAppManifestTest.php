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
        ->background_color->toBe('#ffffff')
        ->theme_color->toBe('#18181b')
        ->and($response->json('icons'))->toHaveCount(4);
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
