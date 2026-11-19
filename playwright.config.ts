import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/E2E',
  outputDir: 'var/playwright',
  forbidOnly: !!process.env['CI'],
  retries: 0,
  reporter: 'list',
  use: {
    baseURL: process.env['APP_URL'] ?? 'http://parcel-map:8000',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
