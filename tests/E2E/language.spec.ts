import { readdirSync, readFileSync } from 'node:fs';
import { expect, test } from './support/offlineTiles';

const LANGUAGES_DIRECTORY = 'frontend/languages';

test('opens in Czech', async ({ page }) => {
  await page.goto('/');

  await expect(page.locator('html')).toHaveAttribute('lang', 'cs');
  await expect(page).toHaveTitle('Mapa parcel okresu Jičín');
  await expect(page.locator('.map-legend summary')).toHaveText('Druh pozemku');
  await expect(page.locator('.map-hint')).toHaveText('Pro výběr parcely přibližte mapu.');
  const zoomIn = page.getByRole('button', { name: 'Přiblížit' });
  await expect(zoomIn).toBeVisible();
  await expect(page.locator('.maplibregl-canvas')).toHaveAttribute('aria-label', 'Mapa');
});

test('requests exactly one texts file on load, the Czech one', async ({ page }) => {
  const requests: string[] = [];
  page.on('request', (request) => {
    const { pathname } = new URL(request.url());
    if (pathname.startsWith('/build/languages/') && pathname.endsWith('.json')) requests.push(pathname);
  });

  await page.goto('/');
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  await page.waitForLoadState('networkidle');

  expect(requests).toHaveLength(1);
  expect(requests[0]).toMatch(/\/build\/languages\/cs-[^/]+\.json$/);
});

test('holds no interface text in the JavaScript bundle', async ({ page }) => {
  const response = await page.request.get('/build/app.js');
  const bundle = await response.text();

  const texts = readdirSync(LANGUAGES_DIRECTORY)
    .filter((file) => file.endsWith('.json'))
    .flatMap((file) =>
      Object.values(JSON.parse(readFileSync(`${LANGUAGES_DIRECTORY}/${file}`, 'utf8')) as Record<string, string>),
    )
    .filter((text) => text.length >= 12);
  expect(texts.length).toBeGreaterThan(50);
  expect(
    texts.filter((text) => bundle.includes(text)),
    'texts found in the bundle',
  ).toEqual([]);
  expect(bundle).toContain('/build/languages/cs-');
});

test('formats the area of a linked parcel with Czech number grouping', async ({ page }) => {
  await page.goto('/#parcel=1761441604');

  await expect(page.getByRole('complementary', { name: 'Podrobnosti o parcele' })).toContainText('13 077 m²');
});
