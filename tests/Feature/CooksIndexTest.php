<?php

use App\Models\Cook;
use App\Models\Smoker;

beforeEach(function () {
    $this->smoker = Smoker::query()->create(['name' => 'Backyard']);
});

function finishedCook(array $attributes = []): Cook
{
    return Cook::query()->create([
        'smoker_id' => test()->smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subDay(),
        ...$attributes,
    ]);
}

function cooksIndexHtml(): string
{
    return test()->get(route('cooks'))->assertOk()->getContent();
}

test('cooks index shows title once when there is no description', function () {
    finishedCook(['description' => null]);

    $html = cooksIndexHtml();

    expect(substr_count($html, 'Brisket'))->toBe(1);
});

test('cooks index shows title above description text without duplicating it', function () {
    finishedCook(['description' => '<p>Smoked for 12 hours</p>']);

    $html = cooksIndexHtml();

    expect($html)
        ->toContain('Smoked for 12 hours')
        ->and(substr_count($html, 'Brisket'))->toBe(1);
});

test('cooks index shows title with photo indicator once when description is image-only', function () {
    $path = Cook::DESCRIPTION_ATTACHMENTS_DIRECTORY.'/photo.webp';

    finishedCook([
        'description' => '<img data-id="'.$path.'" src="/storage/'.$path.'" alt="Brisket">',
    ]);

    $html = cooksIndexHtml();

    expect(substr_count($html, 'Brisket'))->toBe(1)
        ->and($html)->toContain('fi-icon');
});
