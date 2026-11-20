import { defineConfig, devices } from '@playwright/test';

// Not part of `make check`: it measures the running app with real data (see tests/Perf/district.spec.ts).
export default defineConfig({
  testDir: 'tests/Perf',
  outputDir: 'var/playwright-perf',
  retries: 0,
  workers: 1,
  timeout: 120_000,
  reporter: 'list',
  use: {
    baseURL: process.env['APP_URL'] ?? 'http://parcel-map:8000',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], viewport: { width: 1920, height: 1080 } },
    },
  ],
});
