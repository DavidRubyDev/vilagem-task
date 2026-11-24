import type { Page } from '@playwright/test';

const HIGHLIGHT_COLOUR = { red: 230, green: 0, blue: 126 };

/** Counts the highlight-coloured pixels of the page, as the outline of the selected parcel is the only magenta. */
export async function countHighlightPixels(page: Page): Promise<number> {
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
