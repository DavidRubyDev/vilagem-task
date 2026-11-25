import { lookup } from 'node:dns/promises';
import { expect, test } from '@playwright/test';

// Tests the test environment, not the app. Playwright's request context looks a host up for IPv6 as well as IPv4 and
// waits for both. The container name of the app has no IPv6 address, and with the host's DNS search domains that
// lookup was forwarded to the host's DNS servers, which took up to 10 s and made unrelated tests fail at random.
const LOOKUPS = 30;
const SLOW_MS = 500;

test("looks up the app host at once, for IPv6 as well as IPv4, as Playwright's request context does", async ({
  baseURL,
}) => {
  const host = new URL(baseURL ?? 'http://parcel-map:8000').hostname;

  for (const family of [6, 4] as const) {
    for (let attempt = 0; attempt < LOOKUPS; attempt++) {
      const started = performance.now();
      await lookup(host, { all: true, family }).catch(() => undefined);
      const elapsed = performance.now() - started;
      expect(elapsed, `IPv${String(family)} lookup of ${host} took ${String(Math.round(elapsed))} ms`).toBeLessThan(
        SLOW_MS,
      );
    }
  }
});
