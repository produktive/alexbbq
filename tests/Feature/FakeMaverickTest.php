<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Services\FakeMaverickSimulator;
use App\Services\MaverickService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

test('fake maverick simulator produces realistic temperatures', function () {
    $simulator = new FakeMaverickSimulator(intervalSeconds: 10, bbqTarget: 225);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $previous = null;
    $foodReadings = [];

    for ($i = 0; $i < 30; $i++) {
        $reading = $simulator->nextReading($cook, $previous);
        $foodReadings[] = $reading['probe_food'];

        expect($reading['probe_bbq'])->toBeGreaterThan(200)
            ->toBeLessThan(260)
            ->and($reading['probe_food'])->toBeGreaterThanOrEqual(0);

        Reading::withoutEvents(fn () => Reading::query()->create([
            'cook_id' => $cook->id,
            'time' => now()->addSeconds($i * 10),
            'probe_food' => $reading['probe_food'],
            'probe_bbq' => $reading['probe_bbq'],
        ]));

        $cook->unsetRelation('readings');

        $previous = new Reading([
            'probe_food' => $reading['probe_food'],
            'probe_bbq' => $reading['probe_bbq'],
        ]);
    }

    expect($foodReadings[0])->toBeLessThan(80)
        ->and(max($foodReadings))->toBeGreaterThan($foodReadings[0]);
});

test('maverick service resolves php binary for fake script', function () {
    config(['maverick.php_binary' => null]);

    $php = app(MaverickService::class)->phpBinary();

    expect($php)->not->toContain('fpm')
        ->and(is_executable($php))->toBeTrue();
});

test('maverick simulate picks up a cook created after the cache was primed', function () {
    expect(Cook::active())->toBeNull();

    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    expect(Cook::active())->toBeNull();

    Artisan::call('maverick:simulate', ['--once' => true]);

    expect(Reading::query()->where('cook_id', $cook->id)->exists())->toBeTrue();
});

test('fake maverick script start and stop work without sudo', function () {
    config([
        'maverick.script' => base_path('maverick-fake.sh'),
        'maverick.use_sudo' => false,
    ]);

    $script = base_path('maverick-fake.sh');

    expect(is_file($script))->toBeTrue();

    Process::path(base_path())->run('chmod +x '.escapeshellarg($script));

    $maverick = app(MaverickService::class);

    try {
        expect($maverick->isAvailable())->toBeTrue()
            ->and($maverick->start())->toBeTrue()
            ->and($maverick->isRunning())->toBeTrue();

        $maverick->stop();

        expect($maverick->isRunning())->toBeFalse();
    } finally {
        if ($maverick->isRunning()) {
            $maverick->stop();
        }
    }
});

test('maverick service stop finishes the active cook', function () {
    Process::fake([
        'sudo -n * stop' => Process::result(),
        'sudo -n * status' => Process::result(exitCode: 1),
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->subMinutes(5),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    expect(app(MaverickService::class)->stop())->toBeTrue();

    $cook->refresh();

    expect($cook->ended_at)->not->toBeNull();
});

test('maverick service stop returns false when daemon keeps running', function () {
    Process::fake([
        'sudo -n * stop' => Process::result(),
        'sudo -n * status' => Process::result(),
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    expect(app(MaverickService::class)->stop())->toBeFalse();

    $cook->refresh();

    expect($cook->ended_at)->toBeNull();
});

test('maverick service start returns false when process does not launch', function () {
    Process::fake([
        'sudo -n * start' => Process::result(),
        'sudo -n * status' => Process::result(exitCode: 1),
    ]);

    expect(app(MaverickService::class)->start())->toBeFalse();
});
