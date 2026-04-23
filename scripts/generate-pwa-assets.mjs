import fs from 'fs';
import path from 'path';
import { PNG } from 'pngjs';

const outDir = path.resolve('public/pwa');
fs.mkdirSync(outDir, { recursive: true });

const palette = {
  navy: [7, 41, 73],
  deep: [3, 27, 51],
  sea: [14, 95, 149],
  gold: [242, 174, 46],
  mist: [242, 247, 252],
  white: [255, 255, 255],
};

function setPixel(png, x, y, color, alpha = 255) {
  if (x < 0 || y < 0 || x >= png.width || y >= png.height) {
    return;
  }

  const idx = (png.width * y + x) * 4;
  png.data[idx] = color[0];
  png.data[idx + 1] = color[1];
  png.data[idx + 2] = color[2];
  png.data[idx + 3] = alpha;
}

function blendPixel(png, x, y, color, alpha = 255) {
  if (x < 0 || y < 0 || x >= png.width || y >= png.height) {
    return;
  }

  const idx = (png.width * y + x) * 4;
  const srcA = alpha / 255;
  const dstA = png.data[idx + 3] / 255;
  const outA = srcA + dstA * (1 - srcA);

  if (outA === 0) {
    return;
  }

  png.data[idx] = Math.round((color[0] * srcA + png.data[idx] * dstA * (1 - srcA)) / outA);
  png.data[idx + 1] = Math.round((color[1] * srcA + png.data[idx + 1] * dstA * (1 - srcA)) / outA);
  png.data[idx + 2] = Math.round((color[2] * srcA + png.data[idx + 2] * dstA * (1 - srcA)) / outA);
  png.data[idx + 3] = Math.round(outA * 255);
}

function fillRect(png, x, y, w, h, color, alpha = 255) {
  const x1 = Math.max(0, Math.floor(x));
  const y1 = Math.max(0, Math.floor(y));
  const x2 = Math.min(png.width, Math.ceil(x + w));
  const y2 = Math.min(png.height, Math.ceil(y + h));

  for (let yy = y1; yy < y2; yy += 1) {
    for (let xx = x1; xx < x2; xx += 1) {
      setPixel(png, xx, yy, color, alpha);
    }
  }
}

function fillRoundedRect(png, x, y, w, h, r, color, alpha = 255) {
  const rr = Math.max(0, Math.min(r, Math.min(w, h) / 2));

  for (let yy = Math.floor(y); yy < Math.ceil(y + h); yy += 1) {
    for (let xx = Math.floor(x); xx < Math.ceil(x + w); xx += 1) {
      const dx = xx < x + rr ? x + rr - xx : xx > x + w - rr ? xx - (x + w - rr) : 0;
      const dy = yy < y + rr ? y + rr - yy : yy > y + h - rr ? yy - (y + h - rr) : 0;
      if ((dx === 0 && dy === 0) || dx * dx + dy * dy <= rr * rr) {
        setPixel(png, xx, yy, color, alpha);
      }
    }
  }
}

function fillCircle(png, cx, cy, r, color, alpha = 255) {
  const r2 = r * r;

  for (let yy = Math.floor(cy - r); yy <= Math.ceil(cy + r); yy += 1) {
    for (let xx = Math.floor(cx - r); xx <= Math.ceil(cx + r); xx += 1) {
      const dx = xx - cx;
      const dy = yy - cy;
      if (dx * dx + dy * dy <= r2) {
        setPixel(png, xx, yy, color, alpha);
      }
    }
  }
}

function fillVerticalGradient(png, topColor, bottomColor) {
  for (let y = 0; y < png.height; y += 1) {
    const t = y / Math.max(1, png.height - 1);
    const row = [
      Math.round(topColor[0] * (1 - t) + bottomColor[0] * t),
      Math.round(topColor[1] * (1 - t) + bottomColor[1] * t),
      Math.round(topColor[2] * (1 - t) + bottomColor[2] * t),
    ];

    for (let x = 0; x < png.width; x += 1) {
      setPixel(png, x, y, row, 255);
    }
  }
}

function addGlow(png, cx, cy, radius, color, maxAlpha) {
  for (let yy = Math.floor(cy - radius); yy <= Math.ceil(cy + radius); yy += 1) {
    for (let xx = Math.floor(cx - radius); xx <= Math.ceil(cx + radius); xx += 1) {
      const dx = xx - cx;
      const dy = yy - cy;
      const dist = Math.sqrt(dx * dx + dy * dy);

      if (dist <= radius) {
        const strength = 1 - dist / radius;
        blendPixel(png, xx, yy, color, Math.round(maxAlpha * strength));
      }
    }
  }
}

function drawWave(png, y, amp, thickness, color, alpha = 255) {
  for (let x = 0; x < png.width; x += 1) {
    const yy = y + Math.sin((x / png.width) * Math.PI * 1.8) * amp;
    fillRoundedRect(png, x - 1, yy, 3, thickness, 1.5, color, alpha);
  }
}

function drawBlockLetter(png, x, y, scale, pattern, color) {
  const unit = Math.max(2, Math.round(scale));

  pattern.forEach((row, rowIndex) => {
    [...row].forEach((cell, colIndex) => {
      if (cell === '1') {
        fillRoundedRect(
          png,
          x + colIndex * unit,
          y + rowIndex * unit,
          unit - 1,
          unit - 1,
          Math.max(1, unit / 4),
          color,
          255,
        );
      }
    });
  });
}

function writePng(name, width, height, painter) {
  const png = new PNG({ width, height });
  painter(png);
  fs.writeFileSync(path.join(outDir, name), PNG.sync.write(png));
}

function drawIcon(png, { maskable = false } = {}) {
  const size = png.width;
  fillVerticalGradient(png, [238, 246, 252], [255, 255, 255]);
  addGlow(png, size * 0.84, size * 0.15, size * 0.28, palette.gold, 62);
  addGlow(png, size * 0.12, size * 0.12, size * 0.24, palette.sea, 42);

  const inset = size * (maskable ? 0.1 : 0.08);
  const baseW = size - inset * 2;
  const baseH = size - inset * 2;

  fillRoundedRect(png, inset + size * 0.02, inset + size * 0.12, baseW * 0.9, baseH * 0.62, size * 0.09, [201, 214, 227], 120);
  fillRoundedRect(png, inset + size * 0.08, inset + size * 0.18, baseW * 0.34, baseH * 0.18, size * 0.08, [246, 196, 91], 255);
  fillRoundedRect(png, inset + size * 0.06, inset + size * 0.26, baseW * 0.84, baseH * 0.5, size * 0.1, [242, 174, 46], 255);
  fillRoundedRect(png, inset + size * 0.06, inset + size * 0.3, baseW * 0.84, baseH * 0.46, size * 0.1, [247, 194, 86], 255);

  const docX = inset + size * 0.2;
  const docY = inset + size * 0.16;
  const docW = baseW * 0.46;
  const docH = baseH * 0.52;
  fillRoundedRect(png, docX + size * 0.016, docY + size * 0.02, docW, docH, size * 0.05, [181, 196, 212], 120);
  fillRoundedRect(png, docX, docY, docW, docH, size * 0.05, palette.white, 255);
  fillRect(png, docX + docW * 0.68, docY, docW * 0.22, docH * 0.18, [229, 236, 244], 255);
  fillRect(png, docX + docW * 0.68, docY + docH * 0.18, docW * 0.22, 2, [205, 217, 228], 255);
  fillRect(png, docX + docW * 0.16, docY + docH * 0.2, docW * 0.44, docH * 0.055, [38, 95, 141], 255);
  fillRect(png, docX + docW * 0.16, docY + docH * 0.34, docW * 0.54, docH * 0.045, [163, 185, 206], 255);
  fillRect(png, docX + docW * 0.16, docY + docH * 0.48, docW * 0.5, docH * 0.045, [163, 185, 206], 255);
  fillRect(png, docX + docW * 0.16, docY + docH * 0.62, docW * 0.34, docH * 0.045, [163, 185, 206], 255);

  const badgeCx = inset + baseW * 0.73;
  const badgeCy = inset + baseH * 0.58;
  const badgeR = baseW * 0.18;
  fillCircle(png, badgeCx + size * 0.012, badgeCy + size * 0.016, badgeR, [140, 158, 176], 110);
  fillCircle(png, badgeCx, badgeCy, badgeR, palette.navy, 255);
  fillCircle(png, badgeCx, badgeCy, badgeR * 0.84, [11, 57, 96], 255);

  const monogramScale = Math.max(2, Math.round(size * 0.032));
  const monoX = badgeCx - monogramScale * 4.6;
  const monoY = badgeCy - monogramScale * 4.8;
  drawBlockLetter(png, monoX, monoY, monogramScale, [
    '11110',
    '10001',
    '11110',
    '10000',
    '10000',
  ], [255, 255, 255]);
  drawBlockLetter(png, monoX + monogramScale * 5.2, monoY, monogramScale, [
    '10001',
    '11011',
    '10101',
    '10001',
    '10001',
  ], [255, 255, 255]);
  fillRect(png, badgeCx - badgeR * 0.54, badgeCy + badgeR * 0.54, badgeR * 1.08, Math.max(2, size * 0.018), palette.gold, 255);
}

function drawBrowserFrame(png, x, y, w, h) {
  fillRoundedRect(png, x, y, w, h, 28, palette.white, 255);
  fillRect(png, x, y, w, 58, [237, 243, 250], 255);
  fillCircle(png, x + 28, y + 29, 6, [236, 106, 94], 255);
  fillCircle(png, x + 48, y + 29, 6, [240, 191, 79], 255);
  fillCircle(png, x + 68, y + 29, 6, [94, 189, 114], 255);
  fillRoundedRect(png, x + 110, y + 16, w - 146, 26, 13, [250, 252, 255], 255);
}

function drawDesktopScreenshot(png) {
  fillVerticalGradient(png, [236, 245, 253], [246, 250, 255]);
  addGlow(png, png.width * 0.92, png.height * 0.08, png.width * 0.18, palette.gold, 58);
  addGlow(png, png.width * 0.06, png.height * 0.09, png.width * 0.16, palette.sea, 46);

  drawBrowserFrame(png, 80, 68, png.width - 160, png.height - 136);
  fillRect(png, 110, 160, png.width - 220, 74, palette.navy, 248);
  fillRoundedRect(png, 132, 180, 46, 34, 10, [255, 255, 255], 255);
  fillRect(png, 198, 182, 180, 10, [255, 255, 255], 255);
  fillRect(png, 198, 200, 130, 8, [185, 212, 236], 255);

  for (let index = 0; index < 5; index += 1) {
    fillRoundedRect(png, 420 + index * 110, 182, 88, 22, 10, [16, 70, 110], 255);
  }

  fillRoundedRect(png, 128, 272, 314, 350, 24, [8, 47, 82], 238);
  fillRoundedRect(png, 472, 272, 728, 350, 24, [255, 255, 255], 255);
  fillRoundedRect(png, 836, 272, 332, 350, 24, [248, 251, 255], 255);

  fillRoundedRect(png, 154, 312, 240, 126, 20, [255, 255, 255], 255);
  fillRoundedRect(png, 154, 458, 240, 126, 20, [255, 255, 255], 255);

  for (let row = 0; row < 2; row += 1) {
    for (let col = 0; col < 4; col += 1) {
      const blockX = 506 + col * 164;
      const blockY = 312 + row * 154;
      fillRoundedRect(png, blockX, blockY, 128, 110, 20, palette.mist, 255);
      fillRoundedRect(
        png,
        blockX + 42,
        blockY + 24,
        44,
        36,
        12,
        row === 0 ? [246, 215, 122] : [189, 220, 244],
        255,
      );
      fillRect(png, blockX + 20, blockY + 72, 88, 10, [74, 100, 130], 255);
      fillRect(png, blockX + 26, blockY + 90, 60, 8, [185, 202, 220], 255);
    }
  }

  fillRoundedRect(png, 864, 306, 274, 78, 18, [7, 41, 73], 255);
  fillRoundedRect(png, 864, 402, 274, 88, 18, [255, 255, 255], 255);
  fillRoundedRect(png, 864, 510, 274, 88, 18, [255, 255, 255], 255);
}

function drawMobileScreenshot(png) {
  fillVerticalGradient(png, [236, 245, 253], [247, 250, 255]);
  addGlow(png, png.width * 0.84, png.height * 0.07, png.width * 0.28, palette.gold, 64);
  addGlow(png, png.width * 0.08, png.height * 0.12, png.width * 0.22, palette.sea, 48);

  fillRoundedRect(png, 145, 60, png.width - 290, png.height - 120, 56, [17, 26, 38], 255);
  fillRoundedRect(png, 163, 86, png.width - 326, png.height - 172, 46, palette.white, 255);
  fillRoundedRect(png, png.width / 2 - 64, 78, 128, 16, 8, [17, 26, 38], 255);

  const screenX = 185;
  const screenY = 132;
  const screenW = png.width - 370;

  fillRect(png, screenX, screenY, screenW, png.height - 244, [244, 248, 253], 255);
  fillRoundedRect(png, screenX + 24, screenY + 24, screenW - 48, 74, 24, palette.navy, 248);
  fillRoundedRect(png, screenX + 46, screenY + 44, 40, 34, 10, [255, 255, 255], 255);
  fillRect(png, screenX + 106, screenY + 48, 160, 10, [255, 255, 255], 255);
  fillRect(png, screenX + 106, screenY + 66, 110, 8, [185, 212, 236], 255);

  fillRoundedRect(png, screenX + 24, screenY + 128, screenW - 48, 184, 28, [255, 255, 255], 255);
  fillRoundedRect(png, screenX + 24, screenY + 332, screenW - 48, 214, 28, [255, 255, 255], 255);
  fillRoundedRect(png, screenX + 24, screenY + 566, screenW - 48, 182, 28, [8, 47, 82], 240);

  for (let index = 0; index < 4; index += 1) {
    const blockX = screenX + 48 + (index % 2) * 148;
    const blockY = screenY + 158 + Math.floor(index / 2) * 108;
    fillRoundedRect(png, blockX, blockY, 116, 84, 20, palette.mist, 255);
    fillRoundedRect(
      png,
      blockX + 36,
      blockY + 18,
      44,
      28,
      10,
      index % 2 === 0 ? [246, 215, 122] : [189, 220, 244],
      255,
    );
    fillRect(png, blockX + 22, blockY + 58, 72, 8, [92, 118, 148], 255);
  }

  fillRoundedRect(png, screenX + 48, screenY + 364, screenW - 96, 46, 18, palette.mist, 255);
  fillRoundedRect(png, screenX + 48, screenY + 426, screenW - 96, 46, 18, palette.mist, 255);
  fillRoundedRect(png, screenX + 48, screenY + 488, screenW - 96, 46, 18, palette.mist, 255);

  fillRoundedRect(png, screenX + 48, screenY + 602, screenW - 96, 60, 20, [255, 255, 255], 255);
  fillRoundedRect(png, screenX + 48, screenY + 682, screenW - 96, 60, 20, [255, 255, 255], 255);
}

writePng('icon-192.png', 192, 192, (png) => drawIcon(png));
writePng('icon-512.png', 512, 512, (png) => drawIcon(png));
writePng('icon-maskable-512.png', 512, 512, (png) => drawIcon(png, { maskable: true }));
writePng('apple-touch-icon.png', 180, 180, (png) => drawIcon(png));
writePng('favicon-64.png', 64, 64, (png) => drawIcon(png));
writePng('screenshot-wide.png', 1280, 720, (png) => drawDesktopScreenshot(png));
writePng('screenshot-mobile.png', 720, 1280, (png) => drawMobileScreenshot(png));

fs.writeFileSync(path.join(outDir, 'mask-icon.svg'), `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-labelledby="title">
  <title>PMS Drive</title>
  <defs>
    <linearGradient id="bg" x1="0" x2="0" y1="0" y2="1">
      <stop offset="0%" stop-color="#eef6fc"/>
      <stop offset="100%" stop-color="#ffffff"/>
    </linearGradient>
  </defs>
  <rect width="512" height="512" rx="104" fill="url(#bg)"/>
  <rect x="120" y="142" width="126" height="66" rx="28" fill="#f6c45b"/>
  <rect x="102" y="178" width="320" height="206" rx="44" fill="#f2ae2e"/>
  <rect x="102" y="196" width="320" height="188" rx="44" fill="#f7c256"/>
  <rect x="170" y="122" width="152" height="190" rx="22" fill="#d6e2ef" opacity=".45"/>
  <rect x="162" y="114" width="152" height="190" rx="22" fill="#ffffff"/>
  <path d="M266 114h48v44h-48z" fill="#e4edf5"/>
  <path d="M266 114l48 44h-48z" fill="#cfdbe7"/>
  <rect x="188" y="150" width="86" height="16" rx="8" fill="#0e5f95"/>
  <rect x="188" y="190" width="102" height="12" rx="6" fill="#9fb7cf"/>
  <rect x="188" y="222" width="94" height="12" rx="6" fill="#9fb7cf"/>
  <rect x="188" y="254" width="64" height="12" rx="6" fill="#9fb7cf"/>
  <circle cx="338" cy="304" r="78" fill="#072949"/>
  <circle cx="338" cy="304" r="66" fill="#0b3960"/>
  <path d="M301 266h18c15 0 25 9 25 22s-10 22-25 22h-7v28h-11v-72Zm11 35h7c8 0 13-5 13-13s-5-13-13-13h-7v26Zm45-35h11l16 24 16-24h11v72h-11v-54l-16 23-16-23v54h-11v-72Z" fill="#ffffff"/>
  <rect x="296" y="342" width="84" height="10" rx="5" fill="#f2ae2e"/>
</svg>`);
