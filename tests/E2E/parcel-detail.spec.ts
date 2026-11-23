import type { Locator, Page } from '@playwright/test';
import { FIXTURE_VERSION } from './support/fixture';
import { expect, test } from './support/offlineTiles';

const ST_72 = { id: 1753295604, longitude: 15.3510535, latitude: 50.4373542 };
const LAND_1171 = { id: 1761441604, longitude: 15.3517724, latitude: 50.4368209 };

async function openAt(
  page: Page,
  longitude: number | null,
  latitude: number | null,
  zoom: number | null,
): Promise<void> {
  await page.route('**/api/map-config', async (route) => {
    const response = await route.fetch();
    const body = (await response.json()) as Record<string, unknown>;
    await route.fulfill({
      response,
      json:
        longitude === null || latitude === null || zoom === null
          ? body
          : { ...body, initialView: { center: { longitude, latitude }, zoom } },
    });
  });
  await page.goto('/');
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
}

function panelOf(page: Page): Locator {
  return page.getByRole('complementary', { name: 'Parcel details' });
}

/** A click before the tile is rendered finds no parcel, so it is retried until the panel opens. */
async function clickUntilPanelOpens(page: Page, position?: { x: number; y: number }): Promise<void> {
  const canvas = page.locator('.maplibregl-canvas');
  await expect(async () => {
    await canvas.click(position === undefined ? {} : { position });
    await expect(panelOf(page)).toBeVisible({ timeout: 500 });
  }).toPass();
}

test('opens the panel of the clicked building parcel', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);
  const request = page.waitForRequest(`**/api/parcels/${FIXTURE_VERSION}/${String(ST_72.id)}`);

  await clickUntilPanelOpens(page);

  await request;
  const panel = panelOf(page);
  await expect(panel.getByRole('heading')).toHaveText('Parcel st. 72');
  await expect(panel).toContainText('Jičín (659541)');
  await expect(panel).toContainText('Building plot');
  await expect(panel).toContainText('Built-up area and courtyard');
  await expect(panel).toContainText('455 m²');
  await expect(panel.getByText('Municipality')).toBeVisible();
  await expect(panel.getByText('Land use')).toHaveCount(0);
});

test('shows the land use of a land parcel', async ({ page }) => {
  await openAt(page, LAND_1171.longitude, LAND_1171.latitude, 18);

  await clickUntilPanelOpens(page);

  const panel = panelOf(page);
  await expect(panel.getByRole('heading')).toHaveText('Parcel 1171');
  await expect(panel).toContainText('Land plot');
  await expect(panel).toContainText('Other area');
  await expect(panel.getByText('Land use')).toBeVisible();
  await expect(panel).toContainText('Other road');
  await expect(panel).toContainText('13,077 m²');
});

test('closes with the close button', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);
  await clickUntilPanelOpens(page);

  await page.getByRole('button', { name: 'Close parcel details' }).click();

  await expect(panelOf(page)).toBeHidden();
});

test('closes with Escape', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);
  await clickUntilPanelOpens(page);

  await page.keyboard.press('Escape');

  await expect(panelOf(page)).toBeHidden();
});

test('closes when the click hits no parcel', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);
  await clickUntilPanelOpens(page);
  const size = page.viewportSize();
  if (size === null) throw new Error('The page has no viewport.');

  // 540 px east and 310 px north of the map centre (zoom 18), where no fixture parcel is.
  await page.locator('.maplibregl-canvas').click({ position: { x: size.width - 100, y: size.height / 2 - 310 } });

  await expect(panelOf(page)).toBeHidden();
});

test('selects nothing below zoom 14', async ({ page }) => {
  const detailRequests: string[] = [];
  page.on('request', (request) => {
    if (request.url().includes('/api/parcels/')) detailRequests.push(request.url());
  });
  await openAt(page, null, null, null);

  await page.locator('.maplibregl-canvas').click();
  await page.waitForTimeout(1000);

  expect(detailRequests).toEqual([]);
  await expect(panelOf(page)).toBeHidden();
});

test('shows an error when the details cannot be loaded', async ({ page, allowedConsoleErrors }) => {
  allowedConsoleErrors.push(/status of 500/);
  await page.route('**/api/parcels/**', (route) => route.fulfill({ status: 500, json: { error: 'internal_error' } }));
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);

  await clickUntilPanelOpens(page);

  await expect(panelOf(page)).toContainText('The parcel details could not be loaded. Please try again.');
});
