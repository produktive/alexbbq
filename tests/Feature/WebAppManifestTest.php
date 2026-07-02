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

test('web app manifest includes a maskable icon', function () {
    $response = $this->get(route('manifest'));

    expect(collect($response->json('icons'))->firstWhere('purpose', 'maskable'))
        ->not->toBeNull()
        ->src->toContain('pwa-icon-512-maskable.png')
        ->and(collect($response->json('icons'))->firstWhere('purpose', 'maskable')['src'])
        ->toContain('?v=');
});

test('web app manifest icons include cache busting versions', function () {
    $response = $this->get(route('manifest'));

    foreach ($response->json('icons') as $icon) {
        expect($icon['src'])->toContain('?v=');
    }
});

test('web app manifest includes shortcuts for primary navigation', function () {
    $response = $this->get(route('manifest'));

    expect($response->json('shortcuts'))->toHaveCount(3)
        ->and($response->json('shortcuts.0.name'))->toBe('Home')
        ->and($response->json('shortcuts.0.url'))->toBe('/')
        ->and($response->json('shortcuts.1.name'))->toBe('Cooks')
        ->and($response->json('shortcuts.1.url'))->toBe('/cooks')
        ->and($response->json('shortcuts.2.name'))->toBe('Stats')
        ->and($response->json('shortcuts.2.url'))->toBe('/stats');

    foreach ($response->json('shortcuts') as $shortcut) {
        expect($shortcut['icons'][0]['src'])->toContain('?v=');
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

test('web app manifest includes polish metadata', function () {
    config([
        'app.locale' => 'en',
        'pwa.lang' => null,
        'pwa.dir' => 'ltr',
        'pwa.orientation' => 'any',
        'pwa.prefer_related_applications' => false,
        'pwa.categories' => ['utilities', 'food'],
    ]);

    $response = $this->get(route('manifest'));

    expect($response->json())
        ->lang->toBe('en')
        ->dir->toBe('ltr')
        ->orientation->toBe('any')
        ->prefer_related_applications->toBeFalse()
        ->and($response->json('categories'))->toBe(['utilities', 'food']);
});
