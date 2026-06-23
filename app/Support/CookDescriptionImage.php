<?php

namespace App\Support;

use App\Models\Cook;
use GdImage;
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
        $image = $this->loadImage($file->getRealPath(), $file->getMimeType() ?? '');

        if (! $image instanceof GdImage) {
            return $this->storeRawIfSmallEnough($file, $directory, $disk, $visibility);
        }

        try {
            $image = $this->resizeDown(
                $image,
                Cook::DESCRIPTION_ATTACHMENT_MAX_WIDTH,
                Cook::DESCRIPTION_ATTACHMENT_MAX_HEIGHT,
            );

            ['data' => $data, 'extension' => $extension] = $this->encodeUnderBudget(
                $image,
                Cook::DESCRIPTION_ATTACHMENT_MAX_BYTES,
            );
        } finally {
            imagedestroy($image);
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk($disk)->put($path, $data, ['visibility' => $visibility]);

        return $path;
    }

    private function storeRawIfSmallEnough(
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

        $path = $file->store($directory, $disk);

        if ($visibility === 'public') {
            Storage::disk($disk)->setVisibility($path, 'public');
        }

        return $path;
    }

    private function loadImage(string $path, string $mime): ?GdImage
    {
        $image = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function resizeDown(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);

        if ($scale >= 1) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled(
            $resized,
            $image,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $width,
            $height,
        );

        imagedestroy($image);

        return $resized;
    }

    /**
     * @return array{data: string, extension: string}
     */
    private function encodeUnderBudget(GdImage $image, int $maxBytes): array
    {
        ob_start();
        imagepng($image, null, 6);
        $png = ob_get_clean();

        if ($png !== false && strlen($png) <= $maxBytes) {
            return ['data' => $png, 'extension' => 'png'];
        }

        for ($quality = 85; $quality >= 45; $quality -= 5) {
            ob_start();
            imagejpeg($image, null, $quality);
            $jpeg = ob_get_clean();

            if ($jpeg !== false && strlen($jpeg) <= $maxBytes) {
                return ['data' => $jpeg, 'extension' => 'jpg'];
            }
        }

        throw ValidationException::withMessages([
            'file' => 'This image could not be reduced below 2 MB. Try a smaller photo.',
        ]);
    }
}
