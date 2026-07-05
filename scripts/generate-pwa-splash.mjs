import { mkdir, readFile, readdir, unlink, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import { generateImages } from 'pwa-asset-generator/dist/main.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const iconPath = path.join(root, 'public/pwa-icon-512.png');
const outputDir = path.join(root, 'public/pwa-splash/v2');
const manifestPath = path.join(root, 'resources/data/pwa-splash-screens.json');

// Keep aligned with config/pwa.php background_color and logo assets.
const background = '#12081c';
const textColor = '#fafafa';
const pathOverride = '/pwa-splash/v2';

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

function titleSvg(width, label) {
    const fontSize = Math.max(32, Math.round(width * 0.055));

    return Buffer.from(`<svg width="${width}" height="${Math.round(fontSize * 1.5)}" xmlns="http://www.w3.org/2000/svg">
  <text
    x="50%"
    y="54%"
    text-anchor="middle"
    dominant-baseline="middle"
    font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
    font-size="${fontSize}"
    font-weight="600"
    fill="${textColor}"
  >${escapeXml(label)}</text>
</svg>`);
}

async function addTitleToSplash(imagePath, appName) {
    const metadata = await sharp(imagePath).metadata();
    const width = metadata.width ?? 0;
    const height = metadata.height ?? 0;

    if (width === 0 || height === 0) {
        throw new Error(`Could not read splash dimensions for ${imagePath}`);
    }

    // Match pwa-asset-generator's 30% padding: icon uses ~40% of the short edge.
    const iconSize = Math.round(Math.min(width, height) * 0.4);
    const gap = Math.round(width * 0.04);
    const textTop = Math.round((height + iconSize) / 2 + gap);
    const text = await sharp(titleSvg(width, appName)).png().toBuffer();

    await sharp(imagePath)
        .composite([{ input: text, top: textTop, left: 0 }])
        .png()
        .toFile(`${imagePath}.tmp`);

    await unlink(imagePath);
    await sharp(`${imagePath}.tmp`).toFile(imagePath);
    await unlink(`${imagePath}.tmp`);
}

await mkdir(outputDir, { recursive: true });
await mkdir(path.dirname(manifestPath), { recursive: true });

const legacyDir = path.join(root, 'public/pwa-splash');
for (const file of await readdir(legacyDir)) {
    if (file.endsWith('.png') && ! file.startsWith('.')) {
        await unlink(path.join(legacyDir, file));
    }
}

const appName = await readAppName();

const { htmlMeta } = await generateImages(iconPath, outputDir, {
    splashOnly: true,
    background,
    padding: '30%',
    type: 'png',
    pathOverride,
    log: true,
    scrape: true,
});

const launchHtml = htmlMeta.appleLaunchImage ?? '';
const splashScreens = [...launchHtml.matchAll(/href="([^"]+)" media="([^"]+)"/g)].map((match) => ({
    file: match[1].replace(`${pathOverride}/`, ''),
    media: match[2],
}));

if (splashScreens.length === 0) {
    throw new Error('pwa-asset-generator did not produce any splash screen link tags.');
}

for (const screen of splashScreens) {
    await addTitleToSplash(path.join(outputDir, screen.file), appName);
}

await writeFile(manifestPath, `${JSON.stringify(splashScreens, null, 4)}\n`);

console.log(`Generated ${splashScreens.length} splash screens in ${path.relative(root, outputDir)}`);
console.log(`Splash label: ${appName}`);
console.log(`Wrote ${path.relative(root, manifestPath)}`);
