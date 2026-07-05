import { mkdir, readFile, readdir, unlink, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { generateImages } from 'pwa-asset-generator/dist/main.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const iconPath = path.join(root, 'public/pwa-icon-512.png');
const outputDir = path.join(root, 'public/pwa-splash');
const manifestPath = path.join(root, 'resources/data/pwa-splash-screens.json');
const splashHtmlPath = path.join(root, 'resources/splash/splash-source.html');

// Keep aligned with config/pwa.php background_color and logo assets.
const background = '#12081c';
const textColor = '#fafafa';

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
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

async function writeSplashSourceHtml(appName) {
    const iconRelativePath = path
        .relative(path.dirname(splashHtmlPath), iconPath)
        .split(path.sep)
        .join('/');

    const html = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body {
      margin: 0;
      width: 100%;
      height: 100%;
      background: ${background};
    }
    body {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .splash {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 3.5vmin;
      text-align: center;
      padding: 8vmin;
    }
    .splash img {
      width: 36vmin;
      height: 36vmin;
      object-fit: contain;
    }
    .splash p {
      margin: 0;
      color: ${textColor};
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      font-size: 6vmin;
      font-weight: 600;
      letter-spacing: -0.02em;
    }
  </style>
</head>
<body>
  <div class="splash">
    <img src="${iconRelativePath}" alt="">
    <p>${escapeHtml(appName)}</p>
  </div>
</body>
</html>
`;

    await mkdir(path.dirname(splashHtmlPath), { recursive: true });
    await writeFile(splashHtmlPath, html);
}

await mkdir(outputDir, { recursive: true });
await mkdir(path.dirname(manifestPath), { recursive: true });

for (const file of await readdir(outputDir)) {
    if (file.startsWith('iphone-')) {
        await unlink(path.join(outputDir, file));
    }
}

const appName = await readAppName();

await writeSplashSourceHtml(appName);

const { htmlMeta } = await generateImages(splashHtmlPath, outputDir, {
    splashOnly: true,
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
console.log(`Splash label: ${appName}`);
console.log(`Wrote ${path.relative(root, manifestPath)}`);
