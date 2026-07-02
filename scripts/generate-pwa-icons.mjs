import path from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'public/pwa-icon-512.png');

await sharp(source).resize(180, 180).png().toFile(path.join(root, 'public/apple-touch-icon.png'));
await sharp(source).resize(192, 192).png().toFile(path.join(root, 'public/pwa-icon-192.png'));

console.log('Generated apple-touch-icon.png and pwa-icon-192.png from pwa-icon-512.png');
