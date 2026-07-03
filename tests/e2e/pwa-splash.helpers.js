import { expect, test, devices } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '../..');

export const EXPECTED_SPLASH_FILES = [
    'iphone-se-portrait.png',
    'iphone-x-portrait.png',
    'iphone-xr-portrait.png',
    'iphone-xs-max-portrait.png',
    'iphone-14-portrait.png',
    'iphone-15-portrait.png',
    'iphone-16-portrait.png',
    'iphone-14-plus-portrait.png',
    'iphone-14-pro-max-portrait.png',
    'iphone-16-pro-portrait.png',
    'iphone-16-pro-max-portrait.png',
    'ipad-pro-12-portrait.png',
];

export async function getStartupImages(page) {
    return page.evaluate(() => {
        return [...document.querySelectorAll('link[rel="apple-touch-startup-image"]')].map((link) => ({
            href: link.getAttribute('href'),
            media: link.getAttribute('media'),
        }));
    });
}

export async function resolveStartupImageForDevice(page) {
    return page.evaluate(() => {
        const links = [...document.querySelectorAll('link[rel="apple-touch-startup-image"]')];
        const matches = links.filter((link) => {
            const media = link.getAttribute('media');

            if (! media) {
                return false;
            }

            return window.matchMedia(media).matches;
        });

        const selected = matches.at(-1);

        return {
            deviceWidth: window.screen.width,
            deviceHeight: window.screen.height,
            pixelRatio: window.devicePixelRatio,
            matchCount: matches.length,
            href: selected?.getAttribute('href') ?? null,
            media: selected?.getAttribute('media') ?? null,
        };
    });
}

export async function loadImageDimensions(page, url) {
    return page.evaluate(async (imageUrl) => {
        const response = await fetch(imageUrl);

        if (! response.ok) {
            return { ok: false, status: response.status };
        }

        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);

        try {
            const image = await new Promise((resolve, reject) => {
                const el = new Image();

                el.onload = () => resolve(el);
                el.onerror = () => reject(new Error(`Failed to decode ${imageUrl}`));
                el.src = objectUrl;
            });

            return {
                ok: true,
                status: response.status,
                width: image.naturalWidth,
                height: image.naturalHeight,
            };
        } finally {
            URL.revokeObjectURL(objectUrl);
        }
    }, url);
}

export function readLocalPngDimensions(file) {
    const pngPath = path.join(root, 'public/pwa-splash', file);
    const buffer = readFileSync(pngPath);
    const width = buffer.readUInt32BE(16);
    const height = buffer.readUInt32BE(20);

    return { width, height };
}
