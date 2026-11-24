import { expect, type Page } from '@playwright/test';

interface Box {
  readonly selector: string;
  readonly left: number;
  readonly top: number;
  readonly right: number;
  readonly bottom: number;
}

function describeBox(box: Box): string {
  const edges = [box.left, box.top, box.right, box.bottom].map((edge) => String(Math.round(edge)));
  return `${box.selector} [${edges.join(', ')}]`;
}

/**
 * Asserts that every visible element matching one of the selectors lies inside the viewport and that no two of them
 * intersect. Touching edges are allowed.
 */
export async function expectNoOverlap(page: Page, selectors: readonly string[]): Promise<void> {
  const viewport = page.viewportSize();
  if (viewport === null) throw new Error('The page has no viewport size.');

  const boxes: Box[] = [];
  for (const selector of selectors) {
    for (const element of await page.locator(selector).all()) {
      if (!(await element.isVisible())) continue;
      const rectangle = await element.boundingBox();
      if (rectangle === null) continue;
      boxes.push({
        selector,
        left: rectangle.x,
        top: rectangle.y,
        right: rectangle.x + rectangle.width,
        bottom: rectangle.y + rectangle.height,
      });
    }
  }

  const tolerance = 0.01;
  for (const box of boxes) {
    const inside =
      box.left >= -tolerance &&
      box.top >= -tolerance &&
      box.right <= viewport.width + tolerance &&
      box.bottom <= viewport.height + tolerance;
    expect(
      inside,
      `${describeBox(box)} lies outside the ${String(viewport.width)}x${String(viewport.height)} viewport`,
    ).toBe(true);
  }
  for (const [index, first] of boxes.entries()) {
    for (const second of boxes.slice(index + 1)) {
      const overlaps =
        Math.min(first.right, second.right) - Math.max(first.left, second.left) > tolerance &&
        Math.min(first.bottom, second.bottom) - Math.max(first.top, second.top) > tolerance;
      expect(overlaps, `${describeBox(first)} overlaps ${describeBox(second)}`).toBe(false);
    }
  }
}
