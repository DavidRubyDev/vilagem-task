import { readdirSync, readFileSync } from 'node:fs';
import type { Page } from '@playwright/test';
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

const LAND_1171 = '#parcel=1761441604';

interface Requests {
  readonly tiles: string[];
  readonly flags: string[];
  readonly texts: string[];
  readonly navigations: string[];
}

function recordRequests(page: Page): Requests {
  const requests: Requests = { tiles: [], flags: [], texts: [], navigations: [] };
  page.on('request', (request) => {
    const { pathname } = new URL(request.url());
    if (pathname.startsWith('/tiles/parcels/')) requests.tiles.push(pathname);
    if (pathname.startsWith('/build/languages/') && pathname.endsWith('.svg')) requests.flags.push(pathname);
    if (pathname.startsWith('/build/languages/') && pathname.endsWith('.json')) requests.texts.push(pathname);
  });
  page.on('framenavigated', (frame) => {
    if (frame === page.mainFrame()) requests.navigations.push(frame.url());
  });
  return requests;
}

async function withoutParcelDetails(page: Page): Promise<void> {
  await page.route('**/api/map-config', async (route) => {
    const response = await route.fetch();
    const body = (await response.json()) as { parcels: Record<string, unknown> };
    await route.fulfill({ response, json: { ...body, parcels: { ...body.parcels, parcelUrlTemplate: null } } });
  });
}

test.describe('the language switcher', () => {
  test.use({ viewport: { width: 1280, height: 720 } });

  test('shows a flag button per language above the legend, Czech first', async ({ page }) => {
    await page.goto('/');
    const group = page.getByRole('group', { name: 'Jazyk' });

    await expect(group).toBeVisible();
    const switcher = await group.boundingBox();
    const legend = await page.locator('.map-legend').boundingBox();
    expect((switcher?.y ?? 0) + (switcher?.height ?? 0)).toBeLessThanOrEqual(legend?.y ?? 0);
    expect(switcher?.x).toBeLessThan(20);

    const buttons = group.getByRole('button');
    await expect(buttons).toHaveCount(2);
    await expect(buttons.nth(0)).toHaveAccessibleName('Čeština');
    await expect(buttons.nth(1)).toHaveAccessibleName('English');
    await expect(buttons.nth(0)).toHaveAttribute('aria-pressed', 'true');
    await expect(buttons.nth(1)).toHaveAttribute('aria-pressed', 'false');
    for (const [index, code, name] of [
      [0, 'cs', 'Čeština'],
      [1, 'en', 'English'],
    ] as const) {
      const button = buttons.nth(index);
      await expect(button).toHaveAttribute('title', name);
      await expect(button).toHaveAttribute('lang', code);
      const image = button.locator('img');
      await expect(image).toHaveAttribute('src', new RegExp(`/build/languages/${code}-[^/]+\\.svg$`));
      await expect.poll(() => image.evaluate((element: HTMLImageElement) => element.naturalWidth)).toBeGreaterThan(0);
    }
  });

  test('switches every text in place while the panel is open, without a reload or any tile request', async ({
    page,
    offlineTiles,
  }) => {
    const requests = recordRequests(page);
    await page.goto(`/${LAND_1171}`);
    const panel = page.getByRole('complementary', { name: 'Podrobnosti o parcele' });
    await expect(panel.getByRole('heading')).toHaveText('Parcela 1171');
    await expect(page.locator('.maplibregl-canvas')).toBeVisible();
    await expect(page.getByRole('button', { name: 'English' })).toBeVisible();
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => {
      Object.assign(window, { marker: true, canvasBefore: document.querySelector('.maplibregl-canvas') });
    });
    const before = {
      tiles: requests.tiles.length,
      flags: requests.flags.length,
      osm: offlineTiles.requested().length,
      navigations: requests.navigations.length,
    };

    await page.getByRole('button', { name: 'English' }).click();

    const english = page.getByRole('complementary', { name: 'Parcel details' });
    await expect(english.getByRole('heading')).toHaveText('Parcel 1171');
    await expect(english).toContainText('Other area');
    await expect(english).toContainText('Other road');
    await expect(english).toContainText('13,077 m²');
    await expect(english.getByRole('link', { name: 'Show owners in the ČÚZK cadastre viewer' })).toBeVisible();
    await expect(english.getByRole('button', { name: 'Close parcel details' })).toBeVisible();
    await expect(page.locator('.map-legend summary')).toHaveText('Land type');
    await expect(page.locator('.map-legend li').first()).toHaveText('Arable land');
    await expect(page.locator('.map-legend')).toHaveJSProperty('open', true);
    for (const name of ['Zoom in', 'Zoom out']) {
      await expect(page.getByRole('button', { name })).toHaveAttribute('title', name);
    }
    await expect(page.locator('.maplibregl-canvas')).toHaveAttribute('aria-label', 'Map');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page).toHaveTitle('Jičín Parcel Map');
    await expect(page.getByRole('button', { name: 'English' })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('group', { name: 'Language' })).toBeVisible();
    expect(new URL(page.url()).hash).toBe(LAND_1171);

    await page.waitForLoadState('networkidle');
    expect(await page.evaluate(() => 'marker' in window)).toBe(true);
    expect(
      await page.evaluate(
        () =>
          (window as unknown as { canvasBefore: Element }).canvasBefore ===
          document.querySelector('.maplibregl-canvas'),
      ),
    ).toBe(true);
    expect(requests.tiles.length).toBe(before.tiles);
    expect(offlineTiles.requested().length).toBe(before.osm);
    expect(requests.flags.length).toBe(before.flags);
    expect(requests.texts.filter((path) => /\/en-[^/]+\.json$/.test(path))).toHaveLength(1);
    expect(requests.navigations.length).toBe(before.navigations);
  });

  test('switches back to Czech without requesting the texts again', async ({ page }) => {
    const requests = recordRequests(page);
    await page.goto('/');
    await expect(page.locator('.map-hint')).toHaveText('Pro výběr parcely přibližte mapu.');

    await page.getByRole('button', { name: 'English' }).click();
    await expect(page.locator('.map-hint')).toHaveText('Zoom in to select a parcel.');
    await page.getByRole('button', { name: 'Čeština' }).click();

    await expect(page.locator('.map-hint')).toHaveText('Pro výběr parcely přibližte mapu.');
    await expect(page.locator('html')).toHaveAttribute('lang', 'cs');
    await expect(page).toHaveTitle('Mapa parcel okresu Jičín');
    await page.waitForLoadState('networkidle');
    expect(requests.texts.filter((path) => /\/cs-[^/]+\.json$/.test(path))).toHaveLength(1);
    expect(requests.texts.filter((path) => /\/en-[^/]+\.json$/.test(path))).toHaveLength(1);
  });

  test('switches the not-found text', async ({ page, allowedConsoleErrors }) => {
    allowedConsoleErrors.push(/status of 404/);
    await page.goto('/#parcel=999');
    await expect(page.getByText('Tato parcela nebyla nalezena.')).toBeVisible();

    await page.getByRole('button', { name: 'English' }).click();

    await expect(
      page.getByText('This parcel was not found. If the data has been updated, reload the page.'),
    ).toBeVisible();
  });

  test('switches the error text of a parcel that could not be loaded', async ({ page, allowedConsoleErrors }) => {
    allowedConsoleErrors.push(/status of 500/);
    await page.route('**/api/parcels/**', (route) => route.fulfill({ status: 500, json: { error: 'internal_error' } }));
    await page.goto(`/${LAND_1171}`);
    await expect(page.getByText('Podrobnosti o parcele se nepodařilo načíst.')).toBeVisible();

    await page.getByRole('button', { name: 'English' }).click();

    await expect(page.getByText('The parcel details could not be loaded. Please try again.')).toBeVisible();
  });

  test('switches the notice about missing parcel details', async ({ page }) => {
    await withoutParcelDetails(page);
    await page.goto('/');
    await expect(page.getByRole('status')).toContainText('Podrobnosti o parcelách');

    await page.getByRole('button', { name: 'English' }).click();

    await expect(page.getByRole('status')).toHaveText(
      'Parcel details have not been imported yet. Run make data, then reload this page.',
    );
  });

  test('is shown without a legend when there is no parcel data, and switches the notice', async ({ page }) => {
    await page.route('**/api/map-config', async (route) => {
      const response = await route.fetch();
      const body = (await response.json()) as Record<string, unknown>;
      await route.fulfill({ response, json: { ...body, parcels: null } });
    });
    await page.goto('/');
    await expect(page.getByRole('status')).toContainText('Data parcel zatím nebyla importována.');
    await expect(page.locator('.map-legend')).toHaveCount(0);
    await expect(page.getByRole('group', { name: 'Jazyk' })).toBeVisible();

    await page.getByRole('button', { name: 'English' }).click();

    await expect(page.getByRole('status')).toHaveText(
      'Parcel data has not been imported yet. Run make data, then reload this page.',
    );
  });

  test('stays Czech when the texts of the other language cannot be loaded', async ({ page, allowedConsoleErrors }) => {
    allowedConsoleErrors.push(/status of 500/);
    await page.route('**/build/languages/en-*.json', (route) =>
      route.fulfill({ status: 500, contentType: 'application/json', body: '{}' }),
    );
    await page.goto('/');
    await expect(page.locator('.map-legend summary')).toHaveText('Druh pozemku');

    const failed = page.waitForResponse('**/build/languages/en-*.json');
    await page.getByRole('button', { name: 'English' }).click();
    await failed;

    await expect(page.locator('html')).toHaveAttribute('lang', 'cs');
    await expect(page.locator('.map-legend summary')).toHaveText('Druh pozemku');
    await expect(page.getByRole('button', { name: 'Čeština' })).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('button', { name: 'English' })).toHaveAttribute('aria-pressed', 'false');
  });
});

test.describe('the remembered language', () => {
  test('survives a reload, and only its texts are requested then', async ({ page }) => {
    const requests = recordRequests(page);
    await page.goto('/');
    await page.getByRole('button', { name: 'English' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    requests.texts.length = 0;

    await page.reload();

    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('.map-legend summary')).toHaveText('Land type');
    await expect(page.getByRole('button', { name: 'English' })).toHaveAttribute('aria-pressed', 'true');
    await page.waitForLoadState('networkidle');
    expect(requests.texts).toHaveLength(1);
    expect(requests.texts[0]).toMatch(/\/build\/languages\/en-[^/]+\.json$/);
  });

  test('is replaced by choosing Czech again', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'English' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await page.getByRole('button', { name: 'Čeština' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'cs');

    await page.reload();

    await expect(page.locator('html')).toHaveAttribute('lang', 'cs');
    await expect(page.locator('.map-legend summary')).toHaveText('Druh pozemku');
  });

  test('is ignored when it is not a language', async ({ page }) => {
    await page.addInitScript(() => {
      window.localStorage.setItem('parcel-map.language', 'xx');
    });

    await page.goto('/');

    await expect(page.locator('html')).toHaveAttribute('lang', 'cs');
    await expect(page.locator('.map-legend summary')).toHaveText('Druh pozemku');
  });
});
