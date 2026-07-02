import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const iconSvgPath = path.join(root, 'public/pwa-icon.svg');
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
        file: 'iphone-15-portrait.png',
        width: 1179,
        height: 2556,
        icon: 280,
    },
    {
        file: 'iphone-14-pro-max-portrait.png',
        width: 1290,
        height: 2796,
        icon: 300,
    },
    {
        file: 'iphone-16-pro-portrait.png',
        width: 1206,
        height: 2622,
        icon: 290,
    },
    {
        file: 'iphone-16-pro-max-portrait.png',
        width: 1320,
        height: 2868,
        icon: 310,
    },
    {
        file: 'ipad-pro-12-portrait.png',
        width: 2048,
        height: 2732,
        icon: 360,
    },
    {
        file: 'fallback-portrait.png',
        width: 1290,
        height: 2796,
        icon: 300,
    },
];

const themes = [
    {
        suffix: '',
        background: { r: 255, g: 255, b: 255, alpha: 1 },
        iconBackground: '#ffffff',
        textColor: '#18181b',
    },
    {
        suffix: '-dark',
        background: { r: 24, g: 24, b: 27, alpha: 1 },
        iconBackground: '#171717',
        textColor: '#fafafa',
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

function textSvg(width, label, textColor) {
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

function themedFileName(file, suffix) {
    return file.replace(/\.png$/, `${suffix}.png`);
}

async function renderIcon(size, iconBackground) {
    const svg = (await readFile(iconSvgPath, 'utf8')).replace(
        'fill="#ffffff"',
        `fill="${iconBackground}"`,
    );

    return sharp(Buffer.from(svg)).resize(size, size).png().toBuffer();
}

const appName = await readAppName();

await mkdir(outputDir, { recursive: true });

for (const theme of themes) {
    for (const size of splashSizes) {
        const icon = await renderIcon(size.icon, theme.iconBackground);

        const text = await sharp(textSvg(size.width, appName, theme.textColor))
            .png()
            .toBuffer();

        const textMeta = await sharp(text).metadata();
        const textHeight = textMeta.height ?? 0;
        const gap = Math.round(size.width * 0.035);
        const stackHeight = size.icon + gap + textHeight;
        const top = Math.round((size.height - stackHeight) / 2);
        const iconLeft = Math.round((size.width - size.icon) / 2);
        const outputFile = themedFileName(size.file, theme.suffix);

        await sharp({
            create: {
                width: size.width,
                height: size.height,
                channels: 4,
                background: theme.background,
            },
        })
            .composite([
                { input: icon, top, left: iconLeft },
                { input: text, top: top + size.icon + gap, left: 0 },
            ])
            .png()
            .toFile(path.join(outputDir, outputFile));

        console.log(`Wrote ${outputFile}`);
    }
}

console.log(`Splash label: ${appName}`);
