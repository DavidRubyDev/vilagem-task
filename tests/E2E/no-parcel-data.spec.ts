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
    'Data parcel zatím nebyla importována. Spusťte make data a pak stránku načtěte znovu.',
  );
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  await offlineTiles.waitFor('13/4445/2762');
  expect(parcelRequests).toEqual([]);
  await expect(page.getByRole('complementary', { name: 'Podrobnosti o parcele' })).toHaveCount(0);
  await expect(page.getByText('Pro výběr parcely přibližte mapu.')).toHaveCount(0);
  await expect(page.locator('.map-legend')).toHaveCount(0);
});

test('shows no notice when the parcel data is imported', async ({ page }) => {
  await page.goto('/');

  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  await expect(page.getByRole('status')).toHaveCount(0);
});
