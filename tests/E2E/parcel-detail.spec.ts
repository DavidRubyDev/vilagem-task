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

const HIGHLIGHT_COLOUR = { red: 230, green: 0, blue: 126 };

/** Counts the highlight-coloured pixels of the page, as the outline of the selected parcel is the only magenta. */
async function countHighlightPixels(page: Page): Promise<number> {
  const screenshot = (await page.screenshot()).toString('base64');
  return page.evaluate(
    async ({ image, colour }) => {
      const bitmap = await createImageBitmap(await (await fetch(`data:image/png;base64,${image}`)).blob());
      const canvas = new OffscreenCanvas(bitmap.width, bitmap.height);
      const context = canvas.getContext('2d');
      if (context === null) throw new Error('No 2d context.');
      context.drawImage(bitmap, 0, 0);
      const { data } = context.getImageData(0, 0, bitmap.width, bitmap.height);
      let count = 0;
      for (let index = 0; index < data.length; index += 4) {
        const close =
          Math.abs((data[index] ?? 0) - colour.red) < 12 &&
          Math.abs((data[index + 1] ?? 0) - colour.green) < 12 &&
          Math.abs((data[index + 2] ?? 0) - colour.blue) < 12;
        if (close) count += 1;
      }
      return count;
    },
    { image: screenshot, colour: HIGHLIGHT_COLOUR },
  );
}

test('outlines the selected parcel and removes the outline when it is deselected', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);
  expect(await countHighlightPixels(page)).toBe(0);

  await clickUntilPanelOpens(page);

  await expect.poll(() => countHighlightPixels(page)).toBeGreaterThan(100);
  await page.keyboard.press('Escape');
  await expect.poll(() => countHighlightPixels(page)).toBe(0);
});

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

test('links the parcel to the ČÚZK cadastre viewer, where the owners are', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);

  await clickUntilPanelOpens(page);

  const link = panelOf(page).getByRole('link', { name: 'Show owners in the ČÚZK cadastre viewer' });
  await expect(link).toHaveAttribute(
    'href',
    'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1753295604',
  );
  await expect(link).toHaveAttribute('target', '_blank');
  await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
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
