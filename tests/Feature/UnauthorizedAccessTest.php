<?php

use App\Models\Cook;
use App\Models\Smoker;
use App\Models\User;
use App\Services\MaverickService;
use Livewire\Livewire;

test('guests are redirected away from protected cook and smoker routes', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);
    $cook = Cook::query()->create([
        'user_id' => User::factory()->create()->id,
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subDay(),
    ]);

    $this->get(route('cooks.new'))->assertRedirect(route('login'));
    $this->get(route('smokers'))->assertRedirect(route('login'));
    $this->get(route('cooks.edit', $cook))->assertRedirect(route('login'));
});

test('guests cannot start a live cook through the new cook component', function () {
    $this->mock(MaverickService::class, function ($mock): void {
        $mock->shouldReceive('start')->never();
    });

    Livewire::test('pages::cooks.new')
        ->assertForbidden();

    expect(Cook::query()->count())->toBe(0);
});

test('guests cannot stop a live cook through the cook button component', function () {
    $this->mock(MaverickService::class, function ($mock): void {
        $mock->shouldReceive('stop')->never();
    });

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'user_id' => User::factory()->create()->id,
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Livewire::test('cook-button')
        ->assertForbidden();
});

test('guests cannot add smokers through the smokers page component', function () {
    Livewire::test('pages::smokers')
        ->assertForbidden();

    expect(Smoker::query()->count())->toBe(0);
});

test('guests cannot archive or permanently delete smokers through the smokers page component', function () {
    Smoker::query()->create(['name' => 'Backyard']);

    Livewire::test('pages::smokers')
        ->assertForbidden();

    expect(Smoker::query()->count())->toBe(1);
});

test('guests cannot edit or delete another users finished cook', function () {
    $owner = User::factory()->create();
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'user_id' => $owner->id,
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subDay(),
    ]);

    Livewire::test('pages::cooks.edit', ['cook' => $cook])
        ->assertForbidden();

    Livewire::test('pages::cooks.index')
        ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => (string) $cook->getKey()]);

    expect(Cook::query()->find($cook->id))->not->toBeNull();
});

test('guests cannot modify chart readings on another users cook view page', function () {
    $owner = User::factory()->create();
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'user_id' => $owner->id,
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subDay(),
    ]);

    $reading = $cook->readings()->create([
        'time' => now()->subHour(),
        'probe_food' => 203,
        'probe_bbq' => 250,
    ]);

    Livewire::test('pages::cooks.view', ['cook' => $cook])
        ->call('mountAction', 'deletePoint', ['id' => $reading->id])
        ->call('callMountedAction');

    expect($reading->fresh())->not->toBeNull();
});
