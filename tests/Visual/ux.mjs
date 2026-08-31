import { chromium, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const baseUrl = process.env.VISUAL_BASE_URL ?? 'http://localhost:38515';

ensureApplication();
const fixture = seedVisualData();
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await context.newPage();

await login(page, fixture.users.member.email, fixture.basePassword);

await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Home' })).toHaveAttribute('href', '/');
await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Explore' })).toHaveAttribute('href', '/groups');
await expect(page.locator('.account-menu summary')).toBeVisible();
await page.locator('.account-menu summary').click();
await expect(page.getByRole('link', { name: 'Account settings' })).toBeVisible();
await expect(page.locator('.account-menu button', { hasText: 'Log out' })).toBeVisible();

await page.goto(new URL('/groups', baseUrl).toString(), { waitUntil: 'networkidle' });
await expect(page.locator('a[href="/groups/create"]')).toHaveCount(1);

await page.goto(new URL(fixture.routes.photographyGroup, baseUrl).toString(), { waitUntil: 'networkidle' });
await expect(page.getByRole('link', { name: /Start discussion/ })).toHaveCount(1);
await expect(page.getByText('New discussion')).toHaveCount(0);
await page.getByRole('link', { name: /Start discussion/ }).click();
await page.waitForURL(new URL(fixture.routes.startDiscussion, baseUrl).toString());

await page.goto(new URL('/account', baseUrl).toString(), { waitUntil: 'networkidle' });
await page.fill('#first_name', 'Nora');
await page.fill('#last_name', 'Visual');
await page.fill('#email', 'nora.visual@samtli.test');
await page.getByRole('button', { name: 'Save changes' }).click();
await page.waitForURL(new URL('/account', baseUrl).toString());
await expect(page.getByText('Account updated.')).toBeVisible();
await expect(page.locator('.account-menu summary')).toContainText('Nora Visual');

await page.locator('.account-menu summary').click();
await page.locator('.account-menu button', { hasText: 'Log out' }).click();
await page.waitForURL(new URL('/', baseUrl).toString());
await page.goto(new URL('/account', baseUrl).toString(), { waitUntil: 'networkidle' });
await page.waitForURL(new URL('/login', baseUrl).toString());

await context.close();
await browser.close();

console.log('UX navigation verification passed.');

function ensureApplication() {
  if (process.env.VISUAL_SKIP_DOCKER === 'true') {
    return;
  }

  execFileSync('docker', ['compose', 'up', '-d', '--build', 'app'], {
    cwd: root,
    stdio: 'inherit',
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

async function login(page, email, password) {
  await page.goto(new URL('/login', baseUrl).toString(), { waitUntil: 'networkidle' });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(new URL('/', baseUrl).toString());
}
