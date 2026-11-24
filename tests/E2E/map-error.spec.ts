import { expect, test } from './support/offlineTiles';

test('shows an error instead of a blank page when the map configuration cannot be loaded', async ({
  page,
  allowedConsoleErrors,
}) => {
  await page.route('**/api/map-config', (route) =>
    route.fulfill({ status: 500, contentType: 'application/json', body: '{"error":"internal_error"}' }),
  );
  // The browser logs the stubbed 500 as a console error; page errors (uncaught exceptions) still fail the test.
  allowedConsoleErrors.push(/status of 500/);

  await page.goto('/');

  await expect(page.getByRole('alert')).toHaveText('Mapu se nepodařilo načíst. Zkuste to prosím později.');
  await expect(page.locator('.maplibregl-canvas')).toHaveCount(0);
});

test('shows the Czech fallback from the page when the interface texts cannot be loaded', async ({
  page,
  allowedConsoleErrors,
}) => {
  await page.route('**/build/languages/*.json', (route) =>
    route.fulfill({ status: 500, contentType: 'application/json', body: '{}' }),
  );
  allowedConsoleErrors.push(/status of 500/);

  await page.goto('/');

  await expect(page.getByRole('alert')).toHaveText('Mapu se nepodařilo načíst. Zkuste to prosím později.');
  await expect(page.locator('.maplibregl-canvas')).toHaveCount(0);
});
