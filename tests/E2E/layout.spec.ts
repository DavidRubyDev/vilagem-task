import type { Page } from '@playwright/test';
import { expectNoOverlap } from './support/layout';
import { expect, test } from './support/offlineTiles';

const VIEWPORTS = [
  { name: '1280x720', width: 1280, height: 720 },
  { name: '641x480', width: 641, height: 480 },
  { name: '390x844', width: 390, height: 844 },
  { name: '360x640', width: 360, height: 640 },
];

const ELEMENTS = [
  '.map-search',
  '.parcel-panel',
  '.map-language',
  '.map-legend',
  '.maplibregl-ctrl-attrib',
  '.maplibregl-ctrl-top-right .maplibregl-ctrl-group',
  '.map-hint',
  '.map-notice',
];

/** The legend is checked open, its largest state; it starts closed on narrow screens. */
async function openLegend(page: Page): Promise<void> {
  await expect(page.locator('.map-legend summary')).toBeVisible();
  if (!(await page.locator('.map-legend li').first().isVisible())) {
    await page.locator('.map-legend summary').click();
  }
  await expect(page.locator('.map-legend li').first()).toBeVisible();
}

async function withoutParcelDetails(page: Page): Promise<void> {
  await page.route('**/api/map-config', async (route) => {
    const response = await route.fetch();
    const body = (await response.json()) as { parcels: Record<string, unknown> };
    await route.fulfill({ response, json: { ...body, parcels: { ...body.parcels, parcelUrlTemplate: null } } });
  });
}

for (const { name, width, height } of VIEWPORTS) {
  test.describe(`at ${name}`, () => {
    test.use({ viewport: { width, height } });

    test('the open parcel panel fits its content and overlaps nothing', async ({ page }) => {
      await page.goto('/#parcel=1753295604');
      await expect(page.getByRole('heading', { name: 'Parcela st. 72' })).toBeVisible();
      await expect(page.locator('.maplibregl-canvas')).toBeVisible();
      await openLegend(page);

      await expectNoOverlap(page, ELEMENTS);

      const panel = await page.locator('.parcel-panel').boundingBox();
      expect(panel?.height).toBeLessThan(height - 24);
      if (width === 1280) expect(panel?.height).toBeLessThan(400 - 12);
      // Even on the smallest landscape screen the panel keeps room for its title and a few facts.
      if (width === 641) expect(panel?.height).toBeGreaterThanOrEqual(100);
    });

    test('the open parcel panel shows all its content where there is room for it', async ({ page }) => {
      await page.goto('/#parcel=1753295604');
      await expect(page.getByRole('heading', { name: 'Parcela st. 72' })).toBeVisible();
      await expect(page.locator('.maplibregl-canvas')).toBeVisible();
      await openLegend(page);

      const fits = await page
        .locator('.parcel-panel')
        .evaluate((element) => element.scrollHeight <= element.clientHeight);

      // 1280x720 and 390x844 are tall enough for the whole panel with the legend open; the others scroll.
      expect(fits).toBe(height >= 720);
    });

    test('the open list of results overlaps nothing but the panel', async ({ page }) => {
      await page.goto('/#parcel=1753295604');
      await expect(page.getByRole('heading', { name: 'Parcela st. 72' })).toBeVisible();
      await expect(page.locator('.maplibregl-canvas')).toBeVisible();
      await openLegend(page);

      await page.getByRole('combobox', { name: 'Hledat na mapě' }).fill('867/121');
      await expect(page.getByRole('option')).toHaveCount(1);

      await expectNoOverlap(page, [...ELEMENTS.filter((selector) => selector !== '.parcel-panel'), '.map-search-list']);
    });

    test('the open list of results in English overlaps nothing but the panel', async ({ page }) => {
      await page.goto('/#parcel=1753295604');
      await expect(page.getByRole('heading', { name: 'Parcela st. 72' })).toBeVisible();
      await expect(page.locator('.maplibregl-canvas')).toBeVisible();
      await openLegend(page);
      await page.getByRole('button', { name: 'English' }).click();

      await page.getByRole('combobox', { name: 'Search the map' }).fill('867/121');
      await expect(page.getByRole('option')).toHaveCount(1);

      await expectNoOverlap(page, [...ELEMENTS.filter((selector) => selector !== '.parcel-panel'), '.map-search-list']);
    });

    test('the message that nothing was found overlaps nothing but the panel', async ({ page }) => {
      await page.goto('/#parcel=1753295604');
      await expect(page.getByRole('heading', { name: 'Parcela st. 72' })).toBeVisible();
      await openLegend(page);

      await page.getByRole('combobox', { name: 'Hledat na mapě' }).fill('9999');
      await expect(page.locator('.map-search-status')).toBeVisible();

      await expectNoOverlap(page, [
        ...ELEMENTS.filter((selector) => selector !== '.parcel-panel'),
        '.map-search-status',
      ]);
    });

    test('the open parcel panel in English overlaps nothing', async ({ page }) => {
      await page.goto('/#parcel=1753295604');
      await expect(page.getByRole('heading', { name: 'Parcela st. 72' })).toBeVisible();
      await expect(page.locator('.maplibregl-canvas')).toBeVisible();
      await openLegend(page);

      await page.getByRole('button', { name: 'English' }).click();

      await expect(page.getByRole('heading', { name: 'Parcel st. 72' })).toBeVisible();
      await expectNoOverlap(page, ELEMENTS);
      const panel = await page.locator('.parcel-panel').boundingBox();
      expect(panel?.height).toBeLessThan(height - 24);
    });

    test('the not-found panel overlaps nothing', async ({ page, allowedConsoleErrors }) => {
      allowedConsoleErrors.push(/status of 404/);
      await page.goto('/#parcel=999');
      await expect(page.getByText('Tato parcela nebyla nalezena')).toBeVisible();
      await openLegend(page);

      await expectNoOverlap(page, ELEMENTS);
    });

    test('the default view with the zoom hint overlaps nothing', async ({ page }) => {
      await page.goto('/');
      await expect(page.locator('.map-hint')).toBeVisible();
      await openLegend(page);

      await expectNoOverlap(page, ELEMENTS);
    });

    test('the notice about missing parcel details overlaps nothing', async ({ page }) => {
      await withoutParcelDetails(page);
      await page.goto('/');
      await expect(page.locator('.map-notice')).toBeVisible();
      await openLegend(page);

      await expectNoOverlap(page, ELEMENTS);
    });
  });
}
