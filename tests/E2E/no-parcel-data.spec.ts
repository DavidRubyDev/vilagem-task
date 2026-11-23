import { expect, test } from './support/offlineTiles';

test('tells the user to import the parcel data when there is none', async ({ page, offlineTiles }) => {
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

  await expect(page.getByRole('status')).toHaveText(
    'Parcel data has not been imported yet. Run make data, then reload this page.',
  );
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  await offlineTiles.waitFor('13/4445/2762');
  expect(parcelRequests).toEqual([]);
  await expect(page.getByRole('complementary', { name: 'Parcel details' })).toHaveCount(0);
  await expect(page.getByText('Zoom in to select a parcel.')).toHaveCount(0);
});

test('shows no notice when the parcel data is imported', async ({ page }) => {
  await page.goto('/');

  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  await expect(page.getByRole('status')).toHaveCount(0);
});
