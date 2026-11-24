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
  path = '/',
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
  await page.goto(path);
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
}

function panelOf(page: Page): Locator {
  return page.getByRole('complementary', { name: 'Podrobnosti o parcele' });
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
  await expect(panel.getByRole('heading')).toHaveText('Parcela st. 72');
  await expect(panel).toContainText('Jičín (659541)');
  await expect(panel).toContainText('Stavební parcela');
  await expect(panel).toContainText('Zastavěná plocha a nádvoří');
  await expect(panel).toContainText('455 m²');
  await expect(panel.getByText('Obec')).toBeVisible();
  await expect(panel.getByText('Způsob využití')).toHaveCount(0);
});

test('links the parcel to the ČÚZK cadastre viewer, where the owners are', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);

  await clickUntilPanelOpens(page);

  const link = panelOf(page).getByRole('link', { name: 'Zobrazit vlastníky v Nahlížení do katastru nemovitostí' });
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
  await expect(panel.getByRole('heading')).toHaveText('Parcela 1171');
  await expect(panel).toContainText('Pozemková parcela');
  await expect(panel).toContainText('Ostatní plocha');
  await expect(panel.getByText('Způsob využití')).toBeVisible();
  await expect(panel).toContainText('Ostatní komunikace');
  await expect(panel).toContainText('13\u00a0077 m²');
});

test('closes with the close button', async ({ page }) => {
  await openAt(page, ST_72.longitude, ST_72.latitude, 18);
  await clickUntilPanelOpens(page);

  await page.getByRole('button', { name: 'Zavřít podrobnosti o parcele' }).click();

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

  await expect(panelOf(page)).toContainText('Podrobnosti o parcele se nepodařilo načíst. Zkuste to prosím znovu.');
});

test.describe('the selected parcel in the URL', () => {
  const requested: string[] = [];

  test.beforeEach(({ page }) => {
    requested.length = 0;
    page.on('request', (request) => {
      const url = new URL(request.url());
      if (url.pathname.startsWith('/tiles/parcels/') || url.pathname.startsWith('/api/parcels/')) {
        requested.push(url.pathname);
      }
    });
  });

  test('opens a link to a parcel fitted to it, without loading the default view first', async ({ page }) => {
    const detail = page.waitForRequest(`**/api/parcels/${FIXTURE_VERSION}/${String(ST_72.id)}`);
    const tile = page.waitForResponse(`**/tiles/parcels/${FIXTURE_VERSION}/16/35562/22101.pbf`);

    await openAt(page, null, null, null, `/#parcel=${String(ST_72.id)}`);

    await detail;
    await tile;
    await expect(panelOf(page).getByRole('heading')).toHaveText('Parcela st. 72');
    expect(requested).not.toContain(`/tiles/parcels/${FIXTURE_VERSION}/12/2222/1381.pbf`);
    await expect.poll(() => countHighlightPixels(page)).toBeGreaterThan(100);
  });

  test('writes the clicked parcel into the URL and removes it when the panel is closed', async ({ page }) => {
    await openAt(page, LAND_1171.longitude, LAND_1171.latitude, 18);

    await clickUntilPanelOpens(page);

    await expect.poll(() => page.url()).toMatch(new RegExp(`#parcel=${String(LAND_1171.id)}$`));
    await page.getByRole('button', { name: 'Zavřít podrobnosti o parcele' }).click();
    await expect.poll(() => page.url()).not.toContain('#');
  });

  test('selects the parcel of a changed hash', async ({ page }) => {
    await openAt(page, null, null, null);

    await page.evaluate((id) => {
      window.location.hash = `#parcel=${String(id)}`;
    }, LAND_1171.id);

    await expect(panelOf(page).getByRole('heading')).toHaveText('Parcela 1171');
  });

  test('says so when the linked parcel does not exist, and stays on the default view', async ({
    page,
    allowedConsoleErrors,
  }) => {
    allowedConsoleErrors.push(/status of 404/);
    const tile = page.waitForResponse(`**/tiles/parcels/${FIXTURE_VERSION}/12/2222/1381.pbf`);

    await openAt(page, null, null, null, '/#parcel=999');

    await tile;
    await expect(panelOf(page)).toContainText(
      'Tato parcela nebyla nalezena. Pokud byla data aktualizována, načtěte stránku znovu.',
    );
  });

  test('ignores a malformed parcel in the URL', async ({ page }) => {
    await openAt(page, null, null, null, '/#parcel=abc');
    await page.waitForTimeout(500);

    expect(requested.filter((path) => path.startsWith('/api/parcels/'))).toEqual([]);
    await expect(panelOf(page)).toBeHidden();
  });
});

test.describe('when selecting is not possible', () => {
  const HINT = 'Pro výběr parcely přibližte mapu.';
  const NOTICE = 'Podrobnosti o parcelách zatím nebyly importovány. Spusťte make data a pak stránku načtěte znovu.';

  async function openWithoutDetails(page: Page, path: string, zoom: number): Promise<void> {
    await page.route('**/api/map-config', async (route) => {
      const response = await route.fetch();
      const body = (await response.json()) as { parcels: Record<string, unknown> };
      await route.fulfill({
        response,
        json: {
          ...body,
          initialView: { center: { longitude: ST_72.longitude, latitude: ST_72.latitude }, zoom },
          parcels: { ...body.parcels, parcelUrlTemplate: null },
        },
      });
    });
    await page.goto(path);
    await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  }

  test('hints at zooming in below zoom 14', async ({ page }) => {
    await openAt(page, ST_72.longitude, ST_72.latitude, 12);
    await expect(page.getByText(HINT)).toBeVisible();
  });

  test('shows no hint at zoom 18', async ({ page }) => {
    await openAt(page, ST_72.longitude, ST_72.latitude, 18);

    await page.waitForTimeout(500);

    await expect(page.getByText(HINT)).toBeHidden();
  });

  test('hides the hint after zooming in to zoom 14', async ({ page }) => {
    await openAt(page, ST_72.longitude, ST_72.latitude, 13);
    await expect(page.getByText(HINT)).toBeVisible();

    await page.getByRole('button', { name: 'Přiblížit' }).click();

    await expect(page.getByText(HINT)).toBeHidden();
  });

  test('hides the hint while the not-found panel of a link is open, and shows it again after closing', async ({
    page,
    allowedConsoleErrors,
  }) => {
    allowedConsoleErrors.push(/status of 404/);
    await openAt(page, ST_72.longitude, ST_72.latitude, 12, '/#parcel=999');
    await expect(panelOf(page)).toBeVisible();

    await expect(page.getByText(HINT)).toBeHidden();

    await page.getByRole('button', { name: 'Zavřít' }).click();
    await expect(page.getByText(HINT)).toBeVisible();
  });

  test('explains that the details are not imported, and selects nothing', async ({ page }) => {
    const detailRequests: string[] = [];
    page.on('request', (request) => {
      if (request.url().includes('/api/parcels/')) detailRequests.push(request.url());
    });
    await openWithoutDetails(page, '/', 18);

    await expect(page.getByRole('status')).toHaveText(NOTICE);
    await page.locator('.maplibregl-canvas').click();
    await page.waitForTimeout(1000);

    expect(detailRequests).toEqual([]);
    await expect(panelOf(page)).toBeHidden();
    await expect(page.getByText(HINT)).toHaveCount(0);
  });

  test('ignores the parcel of the URL', async ({ page }) => {
    const detailRequests: string[] = [];
    page.on('request', (request) => {
      if (request.url().includes('/api/parcels/')) detailRequests.push(request.url());
    });

    await openWithoutDetails(page, `/#parcel=${String(ST_72.id)}`, 12);
    await page.waitForTimeout(500);

    expect(detailRequests).toEqual([]);
    await expect(panelOf(page)).toBeHidden();
    expect(page.url()).toContain(`#parcel=${String(ST_72.id)}`);
  });
});
