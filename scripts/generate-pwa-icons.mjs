import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'public/pwa-icon-512.png');
const background = { r: 31, g: 31, b: 31 };

async function normalizeBackground(inputPath) {
    const { data, info } = await sharp(inputPath).ensureAlpha().raw().toBuffer({ resolveWithObject: true });
    const pixels = Buffer.from(data);

    for (let i = 0; i < pixels.length; i += info.channels) {
        const r = pixels[i];
        const g = pixels[i + 1];
        const b = pixels[i + 2];
        const max = Math.max(r, g, b);
        const min = Math.min(r, g, b);
        const saturation = max - min;

        if (b > r + 20 && b > g + 10 && max > 60) {
            continue;
        }

        if (max < 120 && saturation < 30) {
            pixels[i] = background.r;
            pixels[i + 1] = background.g;
            pixels[i + 2] = background.b;

            if (info.channels === 4) {
                pixels[i + 3] = 255;
            }
        }
    }

    return sharp(pixels, {
        raw: { width: info.width, height: info.height, channels: info.channels },
    }).png().toBuffer();
}

const normalized = await normalizeBackground(source);

await sharp(normalized).toFile(source);
await sharp(normalized).resize(180, 180).png().toFile(path.join(root, 'public/apple-touch-icon.png'));
await sharp(normalized).resize(192, 192).png().toFile(path.join(root, 'public/pwa-icon-192.png'));

console.log('Generated PWA icons with #1f1f1f background from pwa-icon-512.png');
