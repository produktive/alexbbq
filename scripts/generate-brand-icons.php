<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$source = $root.'/resources/brand/app-icon-source.png';

if (! is_file($source)) {
    fwrite(STDERR, "Missing source icon: {$source}\n");
    exit(1);
}

$image = imagecreatefrompng($source);

if ($image === false) {
    fwrite(STDERR, "Unable to read source icon.\n");
    exit(1);
}

imagealphablending($image, true);
imagesavealpha($image, true);

$sizes = [
    'public/apple-touch-icon.png' => 180,
    'public/icons/icon-192.png' => 192,
    'public/icons/icon-512.png' => 512,
    'public/icons/icon-32.png' => 32,
    'public/icons/icon-16.png' => 16,
];

foreach ($sizes as $path => $size) {
    $target = $root.'/'.$path;
    $dir = dirname($target);

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $resized = resizeSquare($image, $size);
    imagepng($resized, $target, 9);

    echo "Wrote {$path}\n";
}

$svgSource = resizeSquare($image, 256);
ob_start();
imagepng($svgSource);
$pngData = ob_get_clean();

$base64 = base64_encode($pngData);
$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" role="img" aria-label="Maverick BBQ">
  <image width="256" height="256" href="data:image/png;base64,{$base64}"/>
</svg>
SVG;

file_put_contents($root.'/public/favicon.svg', $svg);
echo "Wrote public/favicon.svg\n";

file_put_contents($root.'/public/favicon.ico', buildIco([
    $root.'/public/icons/icon-16.png',
    $root.'/public/icons/icon-32.png',
]));
echo "Wrote public/favicon.ico\n";

function resizeSquare(GdImage $image, int $size): GdImage
{
    $resized = imagecreatetruecolor($size, $size);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefilledrectangle($resized, 0, 0, $size, $size, $transparent);

    imagecopyresampled(
        $resized,
        $image,
        0,
        0,
        0,
        0,
        $size,
        $size,
        imagesx($image),
        imagesy($image),
    );

    return $resized;
}

/**
 * @param  list<string>  $pngPaths
 */
function buildIco(array $pngPaths): string
{
    $images = [];

    foreach ($pngPaths as $path) {
        $data = file_get_contents($path);

        if ($data === false) {
            continue;
        }

        $images[] = $data;
    }

    $count = count($images);
    $header = pack('vvv', 0, 1, $count);
    $directory = '';
    $payload = '';
    $offset = 6 + ($count * 16);

    foreach ($images as $png) {
        $size = getimagesizefromstring($png);
        $width = $size[0] ?? 0;
        $height = $size[1] ?? 0;
        $bytes = strlen($png);

        $directory .= pack(
            'CCCCvvVV',
            $width >= 256 ? 0 : $width,
            $height >= 256 ? 0 : $height,
            0,
            0,
            1,
            32,
            $bytes,
            $offset,
        );

        $payload .= $png;
        $offset += $bytes;
    }

    return $header.$directory.$payload;
}
