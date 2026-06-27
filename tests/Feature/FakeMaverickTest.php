<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Services\FakeMaverickSimulator;
use App\Services\MaverickService;
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
