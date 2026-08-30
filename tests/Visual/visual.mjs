import { chromium } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';
import { screens } from './screens.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const artifactsRoot = path.join(root, 'artifacts', 'visual');
const baseUrl = process.env.VISUAL_BASE_URL ?? 'http://localhost:38515';
const threshold = Number(process.env.VISUAL_THRESHOLD ?? '0.12');
const maxMismatch = Number(process.env.VISUAL_MAX_MISMATCH ?? '0.03');

const mode = process.argv[2] ?? 'test';

if (!['validate', 'baseline', 'test', 'report'].includes(mode)) {
  console.error(`Unknown visual command: ${mode}`);
  process.exit(1);
}

fs.mkdirSync(artifactsRoot, { recursive: true });

if (mode === 'validate') {
  const validated = validateReferences();
  writeJson(path.join(artifactsRoot, 'references.json'), validated);
  console.log(`Validated ${validated.length} Stitch reference PNGs.`);
  process.exit(0);
}

if (mode === 'report') {
  generateReport();
  process.exit(0);
}

await runVisualSuite(mode);

async function runVisualSuite(runMode) {
  ensureApplication();
  const references = validateReferences();
  writeJson(path.join(artifactsRoot, 'references.json'), references);
  const fixture = seedVisualData();
  writeJson(path.join(artifactsRoot, 'fixture.json'), fixture);

  const browser = await chromium.launch({ headless: true });
  const rows = [];

  for (const screen of screens) {
    const ref = references.find((candidate) => candidate.name === screen.name);
    const dir = path.join(artifactsRoot, screen.name);
    fs.mkdirSync(dir, { recursive: true });
    fs.copyFileSync(path.join(root, screen.reference), path.join(dir, 'reference.png'));

    const context = await browser.newContext({
      viewport: { width: ref.width, height: ref.height },
      deviceScaleFactor: 1,
    });
    const page = await context.newPage();
    await disableMotion(page);
    await authenticate(page, screen.auth, fixture);

    const route = screen.routeKey ? fixture.routes[screen.routeKey] : screen.route;
    await page.goto(new URL(route, baseUrl).toString(), { waitUntil: 'networkidle' });
    await applyScreenSetup(page, screen.setup);
    await page.evaluate(() => document.fonts && document.fonts.ready);
    await page.waitForLoadState('networkidle');

    const actualPath = path.join(dir, runMode === 'baseline' ? 'baseline-actual.png' : 'actual.png');
    await page.screenshot({ path: actualPath, fullPage: false });
    const metrics = compareImages(path.join(root, screen.reference), actualPath, dir, runMode);
    const pageMetrics = await collectPageMetrics(page);

    const row = {
      ...screen,
      route,
      referenceWidth: ref.width,
      referenceHeight: ref.height,
      actual: path.relative(root, actualPath).replaceAll('\\', '/'),
      mismatchPixels: metrics.mismatchPixels,
      totalPixels: metrics.totalPixels,
      mismatchPercentage: metrics.mismatchPercentage,
      result: metrics.mismatchPercentage <= maxMismatch ? 'PASS' : 'REVIEW',
      pageMetrics,
    };
    writeJson(path.join(dir, runMode === 'baseline' ? 'baseline-metrics.json' : 'metrics.json'), row);
    rows.push(row);

    await context.close();
  }

  await browser.close();

  const reportPath = path.join(artifactsRoot, runMode === 'baseline' ? 'baseline-report.json' : 'report.json');
  writeJson(reportPath, {
    generatedAt: new Date().toISOString(),
    baseUrl,
    threshold,
    maxMismatch,
    screens: rows.sort((a, b) => b.mismatchPercentage - a.mismatchPercentage),
  });
  generateReport();

  const worst = rows.reduce((max, row) => Math.max(max, row.mismatchPercentage), 0);
  console.log(`${runMode} captured ${rows.length} screens. Worst mismatch: ${(worst * 100).toFixed(2)}%.`);
  if (runMode === 'test' && rows.some((row) => row.result === 'REVIEW')) {
    console.log('Some screens exceed the visual review threshold; see artifacts/visual/report.md.');
  }
}

function ensureApplication() {
  if (process.env.VISUAL_SKIP_DOCKER === 'true') {
    return;
  }

  execFileSync('docker', ['compose', 'up', '-d', '--build', 'app'], {
    cwd: root,
    stdio: 'inherit',
  });
}

function validateReferences() {
  return screens.map((screen) => {
    const file = path.join(root, screen.reference);
    const buffer = fs.readFileSync(file);
    const signature = buffer.subarray(0, 8).toString('hex');
    if (signature !== '89504e470d0a1a0a') {
      throw new Error(`${screen.reference} is not a valid PNG signature`);
    }
    const png = PNG.sync.read(buffer);
    if (png.width <= 0 || png.height <= 0 || buffer.length < 1024) {
      throw new Error(`${screen.reference} has invalid dimensions or file size`);
    }

    return {
      name: screen.name,
      reference: screen.reference,
      width: png.width,
      height: png.height,
      bytes: buffer.length,
    };
  });
}

function seedVisualData() {
  const output = execFileSync('docker', ['compose', 'exec', '-T', 'app', 'php', 'tests/Visual/seed.php'], {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  return JSON.parse(output);
}

async function authenticate(page, auth, fixture) {
  if (auth === 'guest') {
    return;
  }

  const persona = fixture.users[auth] ?? fixture.users.member;
  await page.goto(new URL('/login', baseUrl).toString(), { waitUntil: 'networkidle' });
  await page.fill('#email', persona.email);
  await page.fill('#password', fixture.basePassword);
  await page.click('button[type="submit"]');
  await page.waitForURL(baseUrl + '/');
}

async function applyScreenSetup(page, setup) {
  if (setup === 'submit-invalid-login') {
    await page.fill('#email', 'unknown@example.test');
    await page.fill('#password', 'not-the-password');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
  }

  if (setup === 'focus-reply-composer') {
    const textarea = page.locator('textarea').last();
    if (await textarea.count()) {
      await textarea.focus();
      await textarea.fill('I have a small note to add about this lens.');
    }
  }
}

async function disableMotion(page) {
  await page.addStyleTag({
    content: `
      *, *::before, *::after {
        animation-duration: 0s !important;
        animation-delay: 0s !important;
        transition-duration: 0s !important;
        transition-delay: 0s !important;
        scroll-behavior: auto !important;
      }
    `,
  });
}

async function collectPageMetrics(page) {
  return page.evaluate(() => {
    const rect = (selector) => {
      const element = document.querySelector(selector);
      if (!element) {
        return null;
      }
      const box = element.getBoundingClientRect();
      return {
        x: Math.round(box.x),
        y: Math.round(box.y),
        width: Math.round(box.width),
        height: Math.round(box.height),
      };
    };

    return {
      header: rect('.public-header'),
      headerInner: rect('.public-header__inner'),
      main: rect('main'),
      primaryPanel: rect('.auth-panel, .home-feed, .discover-grid, .group-hero, .page-shell'),
      overflowX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    };
  });
}

function compareImages(referencePath, actualPath, outputDir, runMode) {
  const reference = PNG.sync.read(fs.readFileSync(referencePath));
  const actualRaw = PNG.sync.read(fs.readFileSync(actualPath));
  const width = Math.min(reference.width, actualRaw.width);
  const height = Math.min(reference.height, actualRaw.height);
  const referenceCropped = crop(reference, width, height);
  const actual = crop(actualRaw, width, height);
  const diff = new PNG({ width, height });

  const mismatchPixels = pixelmatch(referenceCropped.data, actual.data, diff.data, width, height, {
    threshold,
    includeAA: false,
  });
  const totalPixels = width * height;
  const mismatchPercentage = mismatchPixels / totalPixels;
  const suffix = runMode === 'baseline' ? 'baseline-diff' : 'diff';

  fs.writeFileSync(path.join(outputDir, `${suffix}.png`), PNG.sync.write(diff));
  fs.writeFileSync(path.join(outputDir, runMode === 'baseline' ? 'baseline-overlay.png' : 'overlay.png'), PNG.sync.write(overlay(referenceCropped, actual, width, height)));

  return { mismatchPixels, totalPixels, mismatchPercentage };
}

function crop(png, width, height) {
  if (png.width === width && png.height === height) {
    return png;
  }

  const out = new PNG({ width, height });
  PNG.bitblt(png, out, 0, 0, width, height, 0, 0);
  return out;
}

function overlay(reference, actual, width, height) {
  const out = new PNG({ width, height });
  for (let i = 0; i < out.data.length; i += 4) {
    out.data[i] = Math.round(reference.data[i] * 0.5 + actual.data[i] * 0.5);
    out.data[i + 1] = Math.round(reference.data[i + 1] * 0.5 + actual.data[i + 1] * 0.5);
    out.data[i + 2] = Math.round(reference.data[i + 2] * 0.5 + actual.data[i + 2] * 0.5);
    out.data[i + 3] = 255;
  }
  return out;
}

function generateReport() {
  const finalPath = path.join(artifactsRoot, 'report.json');
  const baselinePath = path.join(artifactsRoot, 'baseline-report.json');
  const report = fs.existsSync(finalPath)
    ? JSON.parse(fs.readFileSync(finalPath, 'utf8'))
    : fs.existsSync(baselinePath)
      ? JSON.parse(fs.readFileSync(baselinePath, 'utf8'))
      : { screens: [] };
  const baseline = fs.existsSync(baselinePath)
    ? JSON.parse(fs.readFileSync(baselinePath, 'utf8'))
    : { screens: [] };
  const baselineByName = new Map(baseline.screens.map((screen) => [screen.name, screen]));

  const lines = [
    '# Samtli Stitch Visual Report',
    '',
    `Generated: ${new Date().toISOString()}`,
    '',
    '| Screen | Route/state | Baseline mismatch | Final mismatch | Result | Notes |',
    '| --- | --- | ---: | ---: | --- | --- |',
  ];

  for (const screen of report.screens) {
    const baselineScreen = baselineByName.get(screen.name);
    const baselineValue = baselineScreen ? percent(baselineScreen.mismatchPercentage) : 'n/a';
    const finalValue = fs.existsSync(finalPath) ? percent(screen.mismatchPercentage) : 'n/a';
    lines.push(`| ${screen.name} | ${screen.route ?? screen.state} | ${baselineValue} | ${finalValue} | ${screen.result} | ${screen.notes ?? screen.variant ?? ''} |`);
  }

  fs.writeFileSync(path.join(artifactsRoot, 'report.md'), `${lines.join('\n')}\n`);
}

function percent(value) {
  return `${(Number(value) * 100).toFixed(2)}%`;
}

function writeJson(file, data) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`);
}
