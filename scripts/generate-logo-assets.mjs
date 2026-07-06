import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourceArg = process.argv[2];
const defaultSource = path.join(root, 'resources/logo/logo-source.png');
const source = sourceArg ? path.resolve(sourceArg) : defaultSource;

const backgrounds = {
    dark: { r: 29, g: 41, b: 61, hex: '#1d293d' },
    light: { r: 255, g: 255, b: 255, hex: '#ffffff' },
};

function withAlpha(color) {
    const background = { ...color, alpha: 1 };
    delete background.hex;

    return background;
}

function isFlamePixel(r, g, b) {
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const saturation = max - min;

    if (max < 55) {
        return false;
    }

    if (r > 130 && r > b + 18) {
        return true;
    }

    if (saturation > 45 && r > 90 && g > 35) {
        return true;
    }

    return false;
}

function extractFlameBuffer({ data, info }) {
    const pixels = Buffer.from(data);

    for (let i = 0; i < pixels.length; i += info.channels) {
        const r = pixels[i];
        const g = pixels[i + 1];
        const b = pixels[i + 2];

        if (! isFlamePixel(r, g, b)) {
            pixels[i + 3] = 0;
        } else if (info.channels === 4) {
            pixels[i + 3] = 255;
        }
    }

    return pixels;
}

function flameBounds({ data, info }) {
    let minX = info.width;
    let minY = info.height;
    let maxX = 0;
    let maxY = 0;

    for (let y = 0; y < info.height; y += 1) {
        for (let x = 0; x < info.width; x += 1) {
            const i = (y * info.width + x) * info.channels;
            const alpha = info.channels === 4 ? data[i + 3] : 255;

            if (alpha === 0) {
                continue;
            }

            minX = Math.min(minX, x);
            minY = Math.min(minY, y);
            maxX = Math.max(maxX, x);
            maxY = Math.max(maxY, y);
        }
    }

    const padX = Math.round((maxX - minX) * 0.06);
    const padY = Math.round((maxY - minY) * 0.06);

    return {
        left: Math.max(0, minX - padX),
        top: Math.max(0, minY - padY),
        width: Math.min(info.width, maxX - minX + 1 + padX * 2),
        height: Math.min(info.height, maxY - minY + 1 + padY * 2),
    };
}

async function buildFlameAsset() {
    const { data, info } = await sharp(source).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    const flame = extractFlameBuffer({ data, info });
    const bounds = flameBounds({ data: flame, info });

    const squareSize = Math.max(info.width, info.height);
    const squared = await sharp(flame, {
        raw: { width: info.width, height: info.height, channels: info.channels },
    })
        .extend({
            top: Math.floor((squareSize - info.height) / 2),
            bottom: Math.ceil((squareSize - info.height) / 2),
            left: Math.floor((squareSize - info.width) / 2),
            right: Math.ceil((squareSize - info.width) / 2),
            background: { r: 0, g: 0, b: 0, alpha: 0 },
        })
        .png()
        .toBuffer();

    const { data: squareData, info: squareInfo } = await sharp(squared).raw().toBuffer({ resolveWithObject: true });
    const offsetLeft = Math.floor((squareSize - info.width) / 2);
    const offsetTop = Math.floor((squareSize - info.height) / 2);

    const cropped = await sharp(squareData, {
        raw: { width: squareInfo.width, height: squareInfo.height, channels: squareInfo.channels },
    })
        .extract({
            left: bounds.left + offsetLeft,
            top: bounds.top + offsetTop,
            width: bounds.width,
            height: bounds.height,
        })
        .png()
        .toBuffer();

    return sharp(cropped)
        .resize(432, 432, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
        .png()
        .toBuffer();
}

async function composeIcon(flameBuffer, background) {
    const backgroundAlpha = withAlpha(background);

    return sharp({
        create: {
            width: 512,
            height: 512,
            channels: 4,
            background: backgroundAlpha,
        },
    })
        .composite([{ input: flameBuffer, gravity: 'center' }])
        .png()
        .toBuffer();
}

async function composeTransparentIcon(flameBuffer) {
    return sharp({
        create: {
            width: 512,
            height: 512,
            channels: 4,
            background: { r: 0, g: 0, b: 0, alpha: 0 },
        },
    })
        .composite([{ input: flameBuffer, gravity: 'center' }])
        .png()
        .toBuffer();
}

async function writeTransparentFaviconSvg(flameBuffer, targetPath) {
    const flameBase64 = flameBuffer.toString('base64');

    const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="Alex.bbq">
  <image href="data:image/png;base64,${flameBase64}" x="40" y="40" width="432" height="432"/>
</svg>`;

    fs.writeFileSync(targetPath, svg);
}

async function writeThemeSvg(flameBuffer, targetPath, backgroundHex) {
    const flameBase64 = flameBuffer.toString('base64');

    const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="Alex.bbq">
  <rect width="512" height="512" fill="${backgroundHex}"/>
  <image href="data:image/png;base64,${flameBase64}" x="40" y="40" width="432" height="432"/>
</svg>`;

    fs.writeFileSync(targetPath, svg);
}

async function writeFaviconIco(pngBuffer) {
    const sizes = [16, 32, 48];
    const pngs = await Promise.all(
        sizes.map((size) => sharp(pngBuffer).resize(size, size).png().toBuffer()),
    );

    const entry = (size, png) => {
        const entryBuffer = Buffer.alloc(16);
        entryBuffer.writeUInt8(size === 256 ? 0 : size, 0);
        entryBuffer.writeUInt8(size === 256 ? 0 : size, 1);
        entryBuffer.writeUInt8(0, 2);
        entryBuffer.writeUInt8(0, 3);
        entryBuffer.writeUInt16LE(1, 4);
        entryBuffer.writeUInt16LE(32, 6);
        entryBuffer.writeUInt32LE(png.length, 8);
        entryBuffer.writeUInt32LE(0, 12);

        return { entry: entryBuffer, png };
    };

    const entries = pngs.map((png, index) => entry(sizes[index], png));
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);
    header.writeUInt16LE(1, 2);
    header.writeUInt16LE(entries.length, 4);

    let offset = 6 + entries.length * 16;
    for (const item of entries) {
        item.entry.writeUInt32LE(offset, 12);
        offset += item.png.length;
    }

    return Buffer.concat([
        header,
        ...entries.flatMap((item) => [item.entry, item.png]),
    ]);
}

if (! fs.existsSync(source)) {
    console.error(`Logo source not found: ${source}`);
    process.exit(1);
}

fs.mkdirSync(path.join(root, 'resources/logo'), { recursive: true });
fs.copyFileSync(source, path.join(root, 'resources/logo/logo-source.png'));

const flame = await buildFlameAsset();
const darkIcon = await composeIcon(flame, backgrounds.dark);
const lightIcon = await composeIcon(flame, backgrounds.light);

await sharp(flame).toFile(path.join(root, 'public/logo-flame.png'));
await sharp(darkIcon).toFile(path.join(root, 'public/pwa-icon-512.png'));
await sharp(lightIcon).toFile(path.join(root, 'public/app-icon-light.png'));
await sharp(darkIcon).resize(180, 180).png().toFile(path.join(root, 'public/apple-touch-icon.png'));
await sharp(darkIcon).resize(192, 192).png().toFile(path.join(root, 'public/pwa-icon-192.png'));
await sharp(darkIcon).resize(512, 512).png().toFile(path.join(root, 'public/logo.png'));

const maskableSize = 512;
const safeZoneSize = Math.round(maskableSize * 0.8);
const maskableIcon = await sharp(darkIcon).resize(safeZoneSize, safeZoneSize).png().toBuffer();

await sharp({
    create: {
        width: maskableSize,
        height: maskableSize,
        channels: 4,
        background: withAlpha(backgrounds.dark),
    },
})
    .composite([{ input: maskableIcon, gravity: 'center' }])
    .png()
    .toFile(path.join(root, 'public/pwa-icon-512-maskable.png'));

await writeTransparentFaviconSvg(flame, path.join(root, 'public/favicon.svg'));
await writeTransparentFaviconSvg(flame, path.join(root, 'public/favicon-light.svg'));
await writeTransparentFaviconSvg(flame, path.join(root, 'public/favicon-dark.svg'));
await writeThemeSvg(flame, path.join(root, 'public/pwa-icon.svg'), backgrounds.dark.hex);
await writeThemeSvg(flame, path.join(root, 'resources/svg/logo-icon.svg'), backgrounds.dark.hex);
await writeThemeSvg(flame, path.join(root, 'resources/svg/flame-mark.svg'), backgrounds.dark.hex);

const favicon = await composeTransparentIcon(flame);

fs.writeFileSync(path.join(root, 'public/favicon.ico'), await writeFaviconIco(favicon));

console.log(`Generated logo assets from ${source}`);
console.log(`  dark background: ${backgrounds.dark.hex}`);
console.log(`  light background: ${backgrounds.light.hex}`);
