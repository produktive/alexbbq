<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Models\User;
use App\Models\UserSettings;
use App\Notifications\TemperatureAlertNotification;
use App\Services\TemperatureAlertService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

test('alert settings can be saved from the alerts page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('alerts'))
        ->assertSuccessful()
        ->assertSee('Alert Frequency');

    Livewire::test('pages::alerts')
        ->set('data.food', [165, 195])
        ->set('data.bbq', [225, 275])
        ->set('data.alert_interval_minutes', 10)
        ->call('save')
        ->assertHasNoErrors();

    $settings = UserSettings::forUser($user->fresh());

    expect($settings->food_min)->toBe(165)
        ->and($settings->food_max)->toBe(195)
        ->and($settings->alert_interval_minutes)->toBe(10);
});

test('temperature alert is sent when bbq probe is below minimum', function () {
    Notification::fake();

    $user = User::factory()->create();
    UserSettings::forUser($user)->update([
        'bbq_min' => 230,
        'bbq_max' => 275,
        'alert_interval_minutes' => 1,
    ]);

    $user->pushSubscriptions()->create([
        'endpoint' => 'https://example.com/push/bbq-low',
        'public_key' => 'test-public-key',
        'auth_token' => 'test-auth-token',
        'content_encoding' => 'aesgcm',
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $reading = Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 73,
        'probe_bbq' => 71,
    ]));

    app(TemperatureAlertService::class)->evaluate($reading);

    Notification::assertSentTo($user, TemperatureAlertNotification::class, function (TemperatureAlertNotification $notification) {
        return str_contains($notification->body, 'BBQ probe is 71°F');
    });
});

test('temperature alert is sent when food probe is out of range', function () {
    Notification::fake();

    $user = User::factory()->create();
    $settings = UserSettings::forUser($user);
    $settings->update([
        'food_min' => 165,
        'food_max' => 195,
        'alert_interval_minutes' => 5,
    ]);

    $user->pushSubscriptions()->create([
        'endpoint' => 'https://example.com/push/1',
        'public_key' => 'test-public-key',
        'auth_token' => 'test-auth-token',
        'content_encoding' => 'aesgcm',
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Cook',
        'ended_at' => null,
    ]);

    $reading = Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 200,
        'probe_bbq' => 250,
    ]));

    app(TemperatureAlertService::class)->evaluate($reading);

    Notification::assertSentTo($user, TemperatureAlertNotification::class, function (TemperatureAlertNotification $notification) {
        return str_contains($notification->body, 'Food probe is 200°F');
    });
});

test('temperature alerts respect the configured interval', function () {
    Notification::fake();

    $user = User::factory()->create();
    $settings = UserSettings::forUser($user);
    $settings->update([
        'food_min' => 165,
        'food_max' => 195,
        'alert_interval_minutes' => 5,
        'last_food_alert_at' => now()->subMinutes(2),
    ]);

    $user->pushSubscriptions()->create([
        'endpoint' => 'https://example.com/push/2',
        'public_key' => 'test-public-key',
        'auth_token' => 'test-auth-token',
        'content_encoding' => 'aesgcm',
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Cook',
        'ended_at' => null,
    ]);

    $reading = Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 200,
        'probe_bbq' => 250,
    ]));

    app(TemperatureAlertService::class)->evaluate($reading);

    Notification::assertNothingSent();
});

test('temperature alert cooldown resets when probe returns to range', function () {
    Notification::fake();

    $user = User::factory()->create();
    $settings = UserSettings::forUser($user);
    $settings->update([
        'food_min' => 165,
        'food_max' => 195,
        'alert_interval_minutes' => 5,
        'last_food_alert_at' => now()->subMinutes(10),
    ]);

    $user->pushSubscriptions()->create([
        'endpoint' => 'https://example.com/push/3',
        'public_key' => 'test-public-key',
        'auth_token' => 'test-auth-token',
        'content_encoding' => 'aesgcm',
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Cook',
        'ended_at' => null,
    ]);

    $inRangeReading = Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 180,
        'probe_bbq' => 250,
    ]));

    app(TemperatureAlertService::class)->evaluate($inRangeReading);

    expect($settings->fresh()->last_food_alert_at)->toBeNull();

    $outOfRangeReading = Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->addMinute(),
        'probe_food' => 200,
        'probe_bbq' => 250,
    ]));

    app(TemperatureAlertService::class)->evaluate($outOfRangeReading);

    Notification::assertSentTo($user, TemperatureAlertNotification::class);
});

test('start cook page defaults smoker to last used smoker', function () {
    Process::fake([
        'pgrep -x maverick' => Process::result(exitCode: 1),
    ]);

    $user = User::factory()->create();
    $older = Smoker::query()->create(['name' => 'Old']);
    Smoker::query()->create(['name' => 'New']);

    Cook::query()->create([
        'smoker_id' => $older->id,
        'title' => 'Past Cook',
        'ended_at' => now()->subDay(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::cooks.new')
        ->assertSet('data.smoker_id', $older->id);
});

test('start cook page defaults smoker to newest smoker when no cooks exist', function () {
    Process::fake([
        'pgrep -x maverick' => Process::result(exitCode: 1),
    ]);

    $user = User::factory()->create();
    Smoker::query()->create(['name' => 'Old']);
    $newer = Smoker::query()->create(['name' => 'New']);

    Livewire::actingAs($user)
        ->test('pages::cooks.new')
        ->assertSet('data.smoker_id', $newer->id);
});

test('start cook page loads saved alert settings including frequency', function () {
    Process::fake([
        'pgrep -x maverick' => Process::result(exitCode: 1),
    ]);

    $user = User::factory()->create();
    UserSettings::forUser($user)->update([
        'food_min' => 150,
        'food_max' => 190,
        'bbq_min' => 220,
        'bbq_max' => 280,
        'alert_interval_minutes' => 10,
    ]);

    Livewire::actingAs($user)
        ->test('pages::cooks.new')
        ->assertSet('data.food', [150, 190])
        ->assertSet('data.bbq', [220, 280])
        ->assertSet('data.alert_interval_minutes', 10);
});

test('start cook page saves alert settings when recording starts', function () {
    Process::fake([
        'pgrep -x maverick' => Process::result(exitCode: 1),
    ]);

    $binary = base_path('maverick');
    file_put_contents($binary, '');
    chmod($binary, 0755);

    try {
        $user = User::factory()->create();
        $smoker = Smoker::query()->create(['name' => 'Backyard']);

        Livewire::actingAs($user)
            ->test('pages::cooks.new')
            ->set('data.smoker_id', $smoker->id)
            ->set('data.title', 'Brisket')
            ->set('data.description', '<p>Low and slow</p>')
            ->set('data.food', [160, 200])
            ->set('data.bbq', [230, 270])
            ->set('data.alert_interval_minutes', 1)
            ->call('save')
            ->assertRedirect(route('home'));

        $settings = UserSettings::forUser($user->fresh());
        $cook = Cook::query()->where('title', 'Brisket')->first();

        expect($settings->food_min)->toBe(160)
            ->and($settings->food_max)->toBe(200)
            ->and($settings->bbq_min)->toBe(230)
            ->and($settings->bbq_max)->toBe(270)
            ->and($settings->alert_interval_minutes)->toBe(1)
            ->and($cook)->not->toBeNull()
            ->and($cook->smoker_id)->toBe($smoker->id)
            ->and($cook->description)->toBe('<p>Low and slow</p>')
            ->and($cook->ended_at)->toBeNull();
    } finally {
        @unlink($binary);
    }
});

test('evaluate cook alerts command evaluates a reading', function () {
    Notification::fake();

    $user = User::factory()->create();
    UserSettings::forUser($user)->update([
        'food_min' => 165,
        'food_max' => 195,
    ]);

    $user->pushSubscriptions()->create([
        'endpoint' => 'https://example.com/push/eval',
        'public_key' => 'test-public-key',
        'auth_token' => 'test-auth-token',
        'content_encoding' => 'aesgcm',
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $reading = Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 200,
        'probe_bbq' => 250,
    ]));

    $this->artisan('cook:evaluate-alerts', ['reading' => $reading->id])
        ->assertSuccessful();

    Notification::assertSentTo($user, TemperatureAlertNotification::class);
});

test('push subscription endpoint can be stored for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://example.com/push/4',
        'keys' => [
            'auth' => 'auth-token',
            'p256dh' => 'public-key',
        ],
    ]);

    $response->assertNoContent();

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('storing a push subscription replaces previous subscriptions for the user', function () {
    $user = User::factory()->create();

    $user->pushSubscriptions()->create([
        'endpoint' => 'https://example.com/push/old',
        'public_key' => 'old-public-key',
        'auth_token' => 'old-auth-token',
        'content_encoding' => 'aes128gcm',
    ]);

    $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://example.com/push/new',
        'keys' => [
            'auth' => 'new-auth-token',
            'p256dh' => 'new-public-key',
        ],
        'contentEncoding' => 'aes128gcm',
    ])->assertNoContent();

    expect($user->pushSubscriptions()->pluck('endpoint')->all())
        ->toBe(['https://example.com/push/new']);
});
