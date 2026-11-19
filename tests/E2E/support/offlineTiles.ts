import { test as base, expect, type Page } from '@playwright/test';

// Generated 1x1 PNG, no external origin.
const TILE_PNG_BASE64 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

const OSM_TILE = /^https:\/\/tile\.openstreetmap\.org\/(\d+\/\d+\/\d+)\.png$/;

export interface TileLog {
  /** Tiles requested so far, as `z/x/y`, in request order. */
  requested(): string[];
  /** Resolves once the given tile has been requested. */
  waitFor(tile: string, timeoutMs?: number): Promise<void>;
}

interface Fixtures {
  offlineTiles: TileLog;
  /** Console error messages matching one of these patterns are not failures (e.g. a stubbed 500). */
  allowedConsoleErrors: RegExp[];
}

/**
 * Tests never reach the internet: OSM tiles are answered with a stub PNG, any other request that
 * leaves the app origin is aborted and makes the test fail. The OSM tile policy forbids automated
 * traffic that forces tile rendering.
 */
export const test = base.extend<Fixtures>({
  // Playwright requires the destructuring pattern, even when empty.
  // eslint-disable-next-line no-empty-pattern
  allowedConsoleErrors: async ({}, use) => {
    await use([]);
  },
  offlineTiles: [
    async ({ context, baseURL, allowedConsoleErrors }, use) => {
      const appOrigin = new URL(baseURL ?? 'http://parcel-map:8000').origin;
      const tiles: string[] = [];
      const waiters: { tile: string; resolve: () => void }[] = [];
      const unexpected: string[] = [];
      const pageErrors: string[] = [];
      const consoleErrors: string[] = [];

      await context.route('**/*', async (route) => {
        const url = route.request().url();
        if (new URL(url).origin === appOrigin) {
          await route.continue();
          return;
        }
        const tile = OSM_TILE.exec(url)?.[1];
        if (tile === undefined) {
          unexpected.push(url);
          await route.abort();
          return;
        }
        tiles.push(tile);
        for (const waiter of waiters.filter((w) => w.tile === tile)) {
          waiter.resolve();
        }
        await route.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from(TILE_PNG_BASE64, 'base64') });
      });

      const watchPage = (page: Page): void => {
        page.on('pageerror', (error) => {
          pageErrors.push(error.message);
        });
        page.on('console', (message) => {
          const text = message.text();
          const allowed =
            text.includes('GL Driver Message') || allowedConsoleErrors.some((pattern) => pattern.test(text));
          if (message.type() === 'error' && !allowed) {
            consoleErrors.push(message.text());
          }
        });
      };
      context.on('page', watchPage);
      for (const existing of context.pages()) {
        watchPage(existing);
      }

      await use({
        requested: () => [...tiles],
        waitFor: (tile, timeoutMs = 5000) =>
          tiles.includes(tile)
            ? Promise.resolve()
            : new Promise<void>((resolve, reject) => {
                const timer = setTimeout(() => {
                  reject(new Error(`Tile ${tile} was not requested within ${String(timeoutMs)} ms`));
                }, timeoutMs);
                waiters.push({
                  tile,
                  resolve: () => {
                    clearTimeout(timer);
                    resolve();
                  },
                });
              }),
      });

      expect(unexpected, 'requests that left the app origin').toEqual([]);
      expect(pageErrors, 'page errors').toEqual([]);
      expect(consoleErrors, 'console errors').toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };
