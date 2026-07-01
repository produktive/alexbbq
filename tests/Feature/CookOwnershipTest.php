<?php

use App\Models\Cook;
use App\Models\Smoker;
use App\Models\User;
use App\Services\MaverickService;
use App\Support\RichEditorDocument;
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

test('creating a cook sanitizes the description html', function () {
    $this->mock(MaverickService::class, function ($mock): void {
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('start')->andReturn(true);
    });

    $user = User::factory()->create();
    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $unsafeDescription = '<p>Brisket notes</p><script>alert(1)</script>';

    Livewire::actingAs($user)
        ->test('pages::cooks.new')
        ->set('data', [
            'smoker_id' => $smoker->id,
            'title' => 'Brisket',
            'description' => $unsafeDescription,
            'food' => [32, 203],
            'bbq' => [225, 275],
            'alert_interval_minutes' => 5,
        ])
        ->call('save')
        ->assertRedirect(route('home'));

    expect(Cook::query()->value('description'))
        ->toBe(RichEditorDocument::sanitizeHtml($unsafeDescription));
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

test('users cannot edit another users finished cook', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'user_id' => $owner->id,
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subDay(),
    ]);

    $this->actingAs($other)
        ->get(route('cooks.edit', $cook))
        ->assertForbidden();
});
