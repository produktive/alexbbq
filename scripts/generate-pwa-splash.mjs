import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const iconPath = path.join(root, 'public/pwa-icon-512.png');
const outputDir = path.join(root, 'public/pwa-splash');
const manifestPath = path.join(root, 'resources/data/pwa-splash-screens.json');

// Keep aligned with config/pwa.php background_color and logo assets (#12081c).
const background = { r: 18, g: 8, b: 28, alpha: 1 };
const textColor = '#fafafa';

const splashSizes = [
    {
        file: 'iphone-se-portrait.png',
        width: 750,
        height: 1334,
        deviceWidth: 375,
        deviceHeight: 667,
        pixelRatio: 2,
        icon: 180,
    },
    {
        file: 'iphone-x-portrait.png',
        width: 1125,
        height: 2436,
        deviceWidth: 375,
        deviceHeight: 812,
        pixelRatio: 3,
        icon: 260,
    },
    {
        file: 'iphone-xr-portrait.png',
        width: 828,
        height: 1792,
        deviceWidth: 414,
        deviceHeight: 896,
        pixelRatio: 2,
        icon: 200,
    },
    {
        file: 'iphone-xs-max-portrait.png',
        width: 1242,
        height: 2688,
        deviceWidth: 414,
        deviceHeight: 896,
        pixelRatio: 3,
        icon: 290,
    },
    {
        file: 'iphone-14-portrait.png',
        width: 1170,
        height: 2532,
        deviceWidth: 390,
        deviceHeight: 844,
        pixelRatio: 3,
        icon: 280,
    },
    {
        file: 'iphone-16-portrait.png',
        width: 1179,
        height: 2556,
        deviceWidth: 393,
        deviceHeight: 852,
        pixelRatio: 3,
        icon: 280,
    },
    {
        file: 'iphone-14-plus-portrait.png',
        width: 1284,
        height: 2778,
        deviceWidth: 428,
        deviceHeight: 926,
        pixelRatio: 3,
        icon: 300,
    },
    {
        file: 'iphone-14-pro-max-portrait.png',
        width: 1290,
        height: 2796,
        deviceWidth: 430,
        deviceHeight: 932,
        pixelRatio: 3,
        icon: 300,
    },
    {
        file: 'iphone-16-pro-portrait.png',
        width: 1206,
        height: 2622,
        deviceWidth: 402,
        deviceHeight: 874,
        pixelRatio: 3,
        icon: 290,
    },
    {
        file: 'iphone-16-pro-max-portrait.png',
        width: 1320,
        height: 2868,
        deviceWidth: 440,
        deviceHeight: 956,
        pixelRatio: 3,
        icon: 310,
    },
    {
        file: 'ipad-pro-12-portrait.png',
        width: 2048,
        height: 2732,
        deviceWidth: 1024,
        deviceHeight: 1366,
        pixelRatio: 2,
        icon: 360,
    },
];

function splashMedia(size) {
    return `screen and (device-width: ${size.deviceWidth}px) and (device-height: ${size.deviceHeight}px) and (-webkit-device-pixel-ratio: ${size.pixelRatio}) and (orientation: portrait)`;
}

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
await mkdir(path.dirname(manifestPath), { recursive: true });

const splashScreens = splashSizes.map((size) => ({
    file: size.file,
    media: splashMedia(size),
}));

await writeFile(manifestPath, `${JSON.stringify(splashScreens, null, 4)}\n`);

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

console.log(`Wrote ${path.relative(root, manifestPath)}`);
console.log(`Splash label: ${appName}`);
