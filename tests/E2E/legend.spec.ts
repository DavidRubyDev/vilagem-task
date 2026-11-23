import type { Page } from '@playwright/test';
import { expect, test } from './support/offlineTiles';

const NAMES = [
  'Arable land',
  'Hop field',
  'Vineyard',
  'Garden',
  'Orchard',
  'Permanent grassland',
  'Forest land',
  'Water area',
  'Built-up area and courtyard',
  'Other area',
  'Unknown land type',
];

async function openWithoutDetails(page: Page): Promise<void> {
  await page.route('**/api/map-config', async (route) => {
    const response = await route.fetch();
    const body = (await response.json()) as { parcels: Record<string, unknown> };
    await route.fulfill({ response, json: { ...body, parcels: { ...body.parcels, parcelUrlTemplate: null } } });
  });
  await page.goto('/');
}

test.describe('on a desktop window', () => {
  test.use({ viewport: { width: 1280, height: 720 } });

  test('shows the open legend at the bottom left, with every land type once', async ({ page }) => {
    await page.goto('/');
    const legend = page.locator('.map-legend');

    await expect(legend).toBeVisible();
    await expect(legend).toHaveJSProperty('open', true);
    const box = await legend.boundingBox();
    expect(box?.x).toBeLessThan(20);
    expect(720 - ((box?.y ?? 0) + (box?.height ?? 0))).toBeLessThan(20);
    await expect(legend.locator('li')).toHaveText(NAMES);
    await expect(legend.getByText('Permanent grassland')).toHaveCount(1);
  });

  test('shows the legend when the parcel details are not imported', async ({ page }) => {
    await openWithoutDetails(page);

    await expect(page.locator('.map-legend')).toBeVisible();
  });
});

test.describe('on a phone-sized window', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('starts closed and opens on a click on the summary', async ({ page }) => {
    await page.goto('/');
    const summary = page.locator('.map-legend summary');
    const entries = page.locator('.map-legend li');

    await expect(summary).toBeVisible();
    await expect(entries.first()).toBeHidden();

    await summary.click();

    await expect(entries).toHaveCount(11);
    await expect(entries.first()).toBeVisible();
  });
});
