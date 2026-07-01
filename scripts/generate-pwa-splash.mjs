import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const iconPath = path.join(root, 'public/pwa-icon-512.png');
const outputDir = path.join(root, 'public/pwa-splash');

const splashSizes = [
    {
        file: 'iphone-se-portrait.png',
        width: 750,
        height: 1334,
        icon: 180,
    },
    {
        file: 'iphone-14-portrait.png',
        width: 1170,
        height: 2532,
        icon: 280,
    },
    {
        file: 'iphone-14-pro-max-portrait.png',
        width: 1290,
        height: 2796,
        icon: 300,
    },
    {
        file: 'ipad-pro-12-portrait.png',
        width: 2048,
        height: 2732,
        icon: 360,
    },
];

await mkdir(outputDir, { recursive: true });

for (const size of splashSizes) {
    const icon = await sharp(iconPath)
        .resize(size.icon, size.icon)
        .png()
        .toBuffer();

    await sharp({
        create: {
            width: size.width,
            height: size.height,
            channels: 4,
            background: { r: 255, g: 255, b: 255, alpha: 1 },
        },
    })
        .composite([{ input: icon, gravity: 'center' }])
        .png()
        .toFile(path.join(outputDir, size.file));

    console.log(`Wrote ${size.file}`);
}
