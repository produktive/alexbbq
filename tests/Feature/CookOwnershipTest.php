<?php

use App\Models\Cook;
use App\Models\Smoker;
use App\Models\User;
use App\Services\MaverickService;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

test('starting a cook assigns the authenticated user', function () {
    $this->mock(MaverickService::class, function ($mock): void {
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('start')->andReturn(true);
    });

    $user = User::factory()->create();
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Livewire::actingAs($user)
        ->test('pages::cooks.new')
        ->set('data', [
            'smoker_id' => $smoker->id,
            'title' => 'Brisket',
            'description' => null,
            'food' => [32, 203],
            'bbq' => [225, 275],
            'alert_interval_minutes' => 5,
        ])
        ->call('save')
        ->assertRedirect(route('home'));

    expect(Cook::query()->value('user_id'))->toBe($user->id);
});

test('database prevents more than one active cook', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    expect(fn () => Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Second Live Cook',
        'ended_at' => null,
    ]))->toThrow(QueryException::class);
});

test('finished cooks do not count toward the active cook limit', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Finished Brisket',
        'ended_at' => now()->subDay(),
    ]);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    expect(Cook::query()->active()->count())->toBe(1);
});
