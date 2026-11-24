import { expect, test } from './support/offlineTiles';

test('opens centred on Jičín at zoom 12', async ({ page, offlineTiles }) => {
  await page.goto('/');

  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  // With 256 px tiles MapLibre requests tiles one zoom level above the map zoom.
  await offlineTiles.waitFor('13/4445/2762');
});

test('shows the OpenStreetMap attribution without any interaction', async ({ page }) => {
  await page.goto('/');

  const link = page.getByRole('link', { name: '© OpenStreetMap contributors' });
  await expect(link).toBeVisible();
  await expect(link).toHaveAttribute('href', 'https://www.openstreetmap.org/copyright');
  await expect(page.locator('.maplibregl-ctrl-attrib')).not.toHaveClass(/maplibregl-compact/);
});

test('zooms in with the zoom button', async ({ page, offlineTiles }) => {
  await page.goto('/');
  await offlineTiles.waitFor('13/4445/2762');

  await page.getByRole('button', { name: 'Přiblížit' }).click();

  await offlineTiles.waitFor('14/8890/5525');
});

test('requests new tiles after dragging the map', async ({ page, offlineTiles }) => {
  await page.goto('/');
  await offlineTiles.waitFor('13/4445/2762');
  const before = new Set(offlineTiles.requested());

  await page.mouse.move(640, 360);
  await page.mouse.down();
  await page.mouse.move(240, 360, { steps: 10 });
  await page.mouse.up();

  await expect.poll(() => offlineTiles.requested().some((tile) => !before.has(tile))).toBe(true);
});
