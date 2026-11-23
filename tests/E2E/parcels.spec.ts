import { FIXTURE_VERSION } from './support/fixture';
import { expect, test } from './support/offlineTiles';

const CONDITIONS_URL =
  'https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx';

test('requests the parcel tile of the map centre and receives a vector tile', async ({ page }) => {
  const response = page.waitForResponse(`**/tiles/parcels/${FIXTURE_VERSION}/12/2222/1381.pbf`);

  await page.goto('/');

  const tile = await response;
  expect(tile.status()).toBe(200);
  expect(tile.headers()['content-type']).toBe('application/vnd.mapbox-vector-tile');
});

test('requests the tile of the next zoom level after zooming in', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  const zoom13 = page.waitForResponse(`**/tiles/parcels/${FIXTURE_VERSION}/13/4445/2762.pbf`);
  const response = page.waitForResponse(`**/tiles/parcels/${FIXTURE_VERSION}/14/8890/5525.pbf`);

  // A second click during the first zoom animation would not add a full level, so wait for zoom 13 to settle.
  await page.getByRole('button', { name: 'Zoom in' }).click();
  await zoom13;
  await page.getByRole('button', { name: 'Zoom in' }).click();

  expect((await response).status()).toBeLessThan(300);
});

test('shows the ČÚZK credit next to the OpenStreetMap credit', async ({ page }) => {
  await page.goto('/');

  const cuzk = page.getByRole('link', { name: 'ČÚZK, 2026' });
  await expect(cuzk).toBeVisible();
  await expect(cuzk).toHaveAttribute('href', CONDITIONS_URL);
  await expect(page.getByRole('link', { name: '© OpenStreetMap contributors' })).toBeVisible();
});

test('shows only the base map and requests no parcel tile when there is no parcel data', async ({
  page,
  offlineTiles,
}) => {
  const parcelRequests: string[] = [];
  page.on('request', (request) => {
    if (request.url().includes('/tiles/parcels/')) parcelRequests.push(request.url());
  });
  await page.route('**/api/map-config', async (route) => {
    const response = await route.fetch();
    const body = (await response.json()) as Record<string, unknown>;
    await route.fulfill({ response, json: { ...body, parcels: null } });
  });

  await page.goto('/');

  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  await offlineTiles.waitFor('13/4445/2762');
  expect(parcelRequests).toEqual([]);
});
