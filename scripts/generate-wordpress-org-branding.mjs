import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(scriptDir, '..');
const dependencyRoot = process.env.ZQ_NODE_MODULES || path.join(rootDir, 'node_modules');
const require = createRequire(path.join(dependencyRoot, 'package.json'));
const sharp = require('sharp');
const assetDir = path.join(rootDir, 'wordpress-org-assets');
const sourceDir = path.join(assetDir, 'source');
const wordmarkPath = path.join(sourceDir, 'zipquantum-wordmark-dark.png');
const appIconPath = path.join(sourceDir, 'zipquantum-app-icon.svg');

function bannerBackground(width, height) {
  return Buffer.from(`
    <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 772 250">
      <defs>
        <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#060a12"/>
          <stop offset="0.58" stop-color="#0a0f1c"/>
          <stop offset="1" stop-color="#0e1727"/>
        </linearGradient>
        <radialGradient id="glow" cx="92%" cy="12%" r="72%">
          <stop offset="0" stop-color="#9eff28" stop-opacity="0.18"/>
          <stop offset="0.42" stop-color="#3dde76" stop-opacity="0.07"/>
          <stop offset="1" stop-color="#0a0f1c" stop-opacity="0"/>
        </radialGradient>
        <pattern id="grid" width="28" height="28" patternUnits="userSpaceOnUse">
          <path d="M 28 0 L 0 0 0 28" fill="none" stroke="#9eb0ca" stroke-opacity="0.08" stroke-width="0.7"/>
        </pattern>
        <linearGradient id="rule" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#9eff28"/>
          <stop offset="1" stop-color="#3dde76"/>
        </linearGradient>
      </defs>
      <rect width="772" height="250" fill="url(#bg)"/>
      <rect width="772" height="250" fill="url(#glow)"/>
      <rect width="772" height="250" fill="url(#grid)"/>
      <circle cx="730" cy="30" r="105" fill="none" stroke="#9eff28" stroke-opacity="0.08" stroke-width="1"/>
      <circle cx="730" cy="30" r="72" fill="none" stroke="#3dde76" stroke-opacity="0.07" stroke-width="1"/>
      <rect x="355" y="54" width="2" height="142" rx="1" fill="url(#rule)"/>
      <text x="394" y="79" fill="#9eff28" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="13" font-weight="700" letter-spacing="2.8">OFFICIAL WORDPRESS PLUGIN</text>
      <text x="391" y="127" fill="#edf5ff" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="35" font-weight="750" letter-spacing="-1.2">Smart Links</text>
      <text x="391" y="165" fill="#edf5ff" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="35" font-weight="750" letter-spacing="-1.2">&amp; QR Codes</text>
      <text x="394" y="193" fill="#9eb0ca" font-family="Inter, Segoe UI, Arial, sans-serif" font-size="14" font-weight="500">WordPress + WooCommerce</text>
      <rect x="0" y="247" width="772" height="3" fill="url(#rule)"/>
    </svg>
  `);
}

async function writeBanner(width, height, filename) {
  const scale = width / 772;
  const wordmark = await sharp(wordmarkPath)
    .resize({ width: Math.round(280 * scale), withoutEnlargement: false })
    .png()
    .toBuffer();

  await sharp(bannerBackground(width, height))
    .composite([
      {
        input: wordmark,
        left: Math.round(42 * scale),
        top: Math.round(87 * scale),
      },
    ])
    .png({ compressionLevel: 9, adaptiveFiltering: true })
    .toFile(path.join(assetDir, filename));
}

async function writeIcon(size, filename) {
  await sharp(appIconPath)
    .resize(size, size, { fit: 'cover', kernel: sharp.kernel.lanczos3 })
    .png({ compressionLevel: 9, adaptiveFiltering: true })
    .toFile(path.join(assetDir, filename));
}

await Promise.all([
  writeBanner(772, 250, 'banner-772x250.png'),
  writeBanner(1544, 500, 'banner-1544x500.png'),
  writeIcon(128, 'icon-128x128.png'),
  writeIcon(256, 'icon-256x256.png'),
]);
