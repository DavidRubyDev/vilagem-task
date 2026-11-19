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

  await expect(page.getByRole('alert')).toHaveText('The map could not be loaded. Please try again later.');
  await expect(page.locator('.maplibregl-canvas')).toHaveCount(0);
});
