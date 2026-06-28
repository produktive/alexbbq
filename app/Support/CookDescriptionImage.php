<?php

namespace App\Support;

use App\Models\Cook;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CookDescriptionImage
{
    public function store(
        TemporaryUploadedFile $file,
        string $directory,
        string $disk,
        string $visibility,
    ): string {
        if ($file->getSize() > Cook::DESCRIPTION_ATTACHMENT_MAX_BYTES) {
            throw ValidationException::withMessages([
                'file' => 'This image must be 2 MB or smaller.',
            ]);
        }

        $mime = $file->getMimeType() ?? '';

        if ($mime !== '' && ! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                'file' => 'Only image uploads are allowed.',
            ]);
        }

        $extension = match (true) {
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            default => $file->guessExtension() ?: 'jpg',
        };

        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs(trim($directory, '/'), $filename, $disk);

        if ($visibility === 'public') {
            Storage::disk($disk)->setVisibility($path, 'public');
        }

        return $path;
    }
}
