import { readdir, unlink, writeFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { generateImages } from 'pwa-asset-generator/dist/main.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const iconPath = path.join(root, 'public/pwa-icon-512.png');
const outputDir = path.join(root, 'public/pwa-splash');
const manifestPath = path.join(root, 'resources/data/pwa-splash-screens.json');

// Keep aligned with config/pwa.php background_color and logo assets.
const background = '#12081c';

await mkdir(outputDir, { recursive: true });
await mkdir(path.dirname(manifestPath), { recursive: true });

for (const file of await readdir(outputDir)) {
    if (file.startsWith('iphone-')) {
        await unlink(path.join(outputDir, file));
    }
}

const { htmlMeta } = await generateImages(iconPath, outputDir, {
    splashOnly: true,
    background,
    padding: '30%',
    type: 'png',
    pathOverride: '/pwa-splash',
    log: true,
    scrape: true,
});

const launchHtml = htmlMeta.appleLaunchImage ?? '';
const splashScreens = [...launchHtml.matchAll(/href="([^"]+)" media="([^"]+)"/g)].map((match) => ({
    file: match[1].replace(/^\/pwa-splash\//, ''),
    media: match[2],
}));

if (splashScreens.length === 0) {
    throw new Error('pwa-asset-generator did not produce any splash screen link tags.');
}

await writeFile(manifestPath, `${JSON.stringify(splashScreens, null, 4)}\n`);

console.log(`Generated ${splashScreens.length} splash screens in ${path.relative(root, outputDir)}`);
console.log(`Wrote ${path.relative(root, manifestPath)}`);
