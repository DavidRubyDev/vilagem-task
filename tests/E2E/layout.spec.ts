import { expectNoOverlap } from './support/layout';
import { expect, test } from './support/offlineTiles';

const VIEWPORTS = [
  { name: '1280x720', width: 1280, height: 720 },
  { name: '641x480', width: 641, height: 480 },
  { name: '390x844', width: 390, height: 844 },
  { name: '360x640', width: 360, height: 640 },
];

const ELEMENTS = ['.parcel-panel', '.maplibregl-ctrl-attrib', '.maplibregl-ctrl-top-right .maplibregl-ctrl-group'];

for (const { name, width, height } of VIEWPORTS) {
  test.describe(`at ${name}`, () => {
    test.use({ viewport: { width, height } });

    test('the open parcel panel fits its content and overlaps nothing', async ({ page }) => {
      await page.goto('/#parcel=1753295604');
      await expect(page.getByRole('heading', { name: 'Parcel st. 72' })).toBeVisible();
      await expect(page.locator('.maplibregl-canvas')).toBeVisible();

      await expectNoOverlap(page, ELEMENTS);

      const panel = await page.locator('.parcel-panel').boundingBox();
      expect(panel?.height).toBeLessThan(height - 24);
      if (width === 1280) expect(panel?.height).toBeLessThan(400 - 12);
    });
  });
}
