import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const iconPath = path.join(root, 'public/pwa-icon-512.png');
const outputDir = path.join(root, 'public/pwa-splash');

const background = { r: 31, g: 31, b: 31, alpha: 1 };
const textColor = '#fafafa';

const splashSizes = [
    {
        file: 'iphone-se-portrait.png',
        width: 750,
        height: 1334,
        icon: 180,
        media: '(device-width: 375px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
    },
    {
        file: 'iphone-x-portrait.png',
        width: 1125,
        height: 2436,
        icon: 260,
        media: '(device-width: 375px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'iphone-xr-portrait.png',
        width: 828,
        height: 1792,
        icon: 200,
        media: '(device-width: 414px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
    },
    {
        file: 'iphone-xs-max-portrait.png',
        width: 1242,
        height: 2688,
        icon: 290,
        media: '(device-width: 414px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'iphone-14-portrait.png',
        width: 1170,
        height: 2532,
        icon: 280,
        media: '(device-width: 390px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'iphone-16-portrait.png',
        width: 1179,
        height: 2556,
        icon: 280,
        media: '(device-width: 393px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'iphone-14-plus-portrait.png',
        width: 1284,
        height: 2778,
        icon: 300,
        media: '(device-width: 428px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'iphone-14-pro-max-portrait.png',
        width: 1290,
        height: 2796,
        icon: 300,
        media: '(device-width: 430px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'iphone-16-pro-portrait.png',
        width: 1206,
        height: 2622,
        icon: 290,
        media: '(device-width: 402px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'iphone-16-pro-max-portrait.png',
        width: 1320,
        height: 2868,
        icon: 310,
        media: '(device-width: 440px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)',
    },
    {
        file: 'ipad-pro-12-portrait.png',
        width: 2048,
        height: 2732,
        icon: 360,
        media: '(device-width: 1024px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)',
    },
];

function escapeXml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&apos;');
}

async function readAppName() {
    try {
        const env = await readFile(path.join(root, '.env'), 'utf8');
        const match = env.match(/^APP_NAME=(.+)$/m);

        if (match) {
            return match[1].trim().replace(/^["']|["']$/g, '');
        }
    } catch {
        //
    }

    return 'Alex.bbq';
}

function textSvg(width, label) {
    const fontSize = Math.max(28, Math.round(width * 0.045));
    const height = Math.round(fontSize * 1.6);

    return Buffer.from(`<svg width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg">
  <text
    x="50%"
    y="50%"
    text-anchor="middle"
    dominant-baseline="middle"
    font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    font-size="${fontSize}"
    font-weight="600"
    fill="${textColor}"
  >${escapeXml(label)}</text>
</svg>`);
}

async function renderIcon(size) {
    return sharp(iconPath).resize(size, size).png().toBuffer();
}

const appName = await readAppName();

await mkdir(outputDir, { recursive: true });

for (const size of splashSizes) {
    const icon = await renderIcon(size.icon);

    const text = await sharp(textSvg(size.width, appName))
        .png()
        .toBuffer();

    const textMeta = await sharp(text).metadata();
    const textHeight = textMeta.height ?? 0;
    const gap = Math.round(size.width * 0.035);
    const stackHeight = size.icon + gap + textHeight;
    const top = Math.round((size.height - stackHeight) / 2);
    const iconLeft = Math.round((size.width - size.icon) / 2);

    await sharp({
        create: {
            width: size.width,
            height: size.height,
            channels: 4,
            background,
        },
    })
        .composite([
            { input: icon, top, left: iconLeft },
            { input: text, top: top + size.icon + gap, left: 0 },
        ])
        .png()
        .toFile(path.join(outputDir, size.file));

    console.log(`Wrote ${size.file}`);
}

console.log(`Splash label: ${appName}`);
