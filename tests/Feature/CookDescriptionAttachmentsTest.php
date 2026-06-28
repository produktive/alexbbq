<?php

use App\Models\Cook;
use App\Models\Smoker;
use App\Models\User;
use App\Support\CookDescriptionAttachments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(Cook::DESCRIPTION_ATTACHMENTS_DISK);

    $this->attachments = app(CookDescriptionAttachments::class);
    $this->user = User::factory()->create();
    $this->smoker = Smoker::query()->create(['name' => 'Backyard']);
});

function cookDescriptionImage(string $filename): string
{
    return Cook::DESCRIPTION_ATTACHMENTS_DIRECTORY.'/'.$filename;
}

function cookDescriptionHtml(string ...$filenames): string
{
    $images = collect($filenames)
        ->map(fn (string $filename): string => sprintf(
            '<img data-id="%s" src="/storage/%s" alt="Test">',
            cookDescriptionImage($filename),
            cookDescriptionImage($filename),
        ))
        ->implode('');

    return '<p>Notes</p>'.$images;
}

test('extractPaths reads data-id and storage src paths', function () {
    $path = cookDescriptionImage('photo.webp');

    $paths = $this->attachments->extractPaths(
        '<img data-id="'.$path.'" src="/storage/'.$path.'" alt="Brisket">',
    );

    expect($paths)->toBe([$path]);
});

test('extractPaths ignores paths outside the cook descriptions directory', function () {
    $paths = $this->attachments->extractPaths(
        '<img data-id="other/secret.jpg" src="/storage/other/secret.jpg">',
    );

    expect($paths)->toBe([]);
});

test('updating a cook deletes description images that were removed', function () {
    $kept = cookDescriptionImage('kept.webp');
    $removed = cookDescriptionImage('removed.webp');

    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->put($kept, 'kept');
    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->put($removed, 'removed');

    $cook = Cook::query()->create([
        'user_id' => $this->user->id,
        'smoker_id' => $this->smoker->id,
        'title' => 'Brisket',
        'description' => cookDescriptionHtml('kept.webp', 'removed.webp'),
        'ended_at' => now(),
    ]);

    $cook->update([
        'description' => cookDescriptionHtml('kept.webp'),
    ]);

    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->assertExists($kept);
    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->assertMissing($removed);
});

test('deleting a cook deletes all referenced description images', function () {
    $first = cookDescriptionImage('first.webp');
    $second = cookDescriptionImage('second.webp');

    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->put($first, 'first');
    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->put($second, 'second');

    $cook = Cook::query()->create([
        'user_id' => $this->user->id,
        'smoker_id' => $this->smoker->id,
        'title' => 'Brisket',
        'description' => cookDescriptionHtml('first.webp', 'second.webp'),
        'ended_at' => now(),
    ]);

    $cook->delete();

    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->assertMissing($first);
    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->assertMissing($second);
});

test('prune command deletes files that are not referenced by any cook', function () {
    $referenced = cookDescriptionImage('referenced.webp');
    $orphan = cookDescriptionImage('orphan.webp');

    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->put($referenced, 'referenced');
    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->put($orphan, 'orphan');

    Cook::query()->create([
        'user_id' => $this->user->id,
        'smoker_id' => $this->smoker->id,
        'title' => 'Brisket',
        'description' => cookDescriptionHtml('referenced.webp'),
        'ended_at' => now(),
    ]);

    Artisan::call('app:prune-cook-description-images');

    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->assertExists($referenced);
    Storage::disk(Cook::DESCRIPTION_ATTACHMENTS_DISK)->assertMissing($orphan);
});
