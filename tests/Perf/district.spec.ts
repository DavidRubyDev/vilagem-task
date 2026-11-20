import { mkdirSync, writeFileSync } from 'node:fs';
import type { APIRequestContext, Page, Request } from '@playwright/test';
import { expect, test } from '../E2E/support/offlineTiles';

// Budgets that do not depend on the hardware. Measured on 2026-11-20 (headless Chromium, 1920x1080, whole
// district imported):
//   district view (zoom 10): 9 parcel tile requests, 257 KB;
//   Jicin (zoom 12): 15 requests, 1.9 MB;
//   tile response time: median 12 to 28 ms, maximum 47 ms.
const MAX_PARCEL_TILE_REQUESTS = 30;
const MAX_PARCEL_BYTES = 4_000_000;
const MAX_P95_TILE_MS = 100;
const MAX_SETTLE_MS = 5_000;

const MIN_DISTRICT_WIDTH_DEGREES = 0.5;
const QUIET_MS = 1_000;
const GIVE_UP_MS = 15_000;
const PAN_STEPS = 20;
const PAN_DRAGS: readonly (readonly [number, number])[] = [
  [300, 0],
  [0, 200],
  [-300, 0],
  [0, -200],
];
const JICIN = { longitude: 15.351653, latitude: 50.437045 };
const REPORT_DIRECTORY = 'var/perf';
const REPORT_FILE = `${REPORT_DIRECTORY}/district-perf.json`;

interface ParcelBounds {
  west: number;
  south: number;
  east: number;
  north: number;
}

interface View {
  name: string;
  longitude: number;
  latitude: number;
  zoom: number;
}

interface TileRecord {
  status: number;
  bytes: number;
  durationMs: number;
}

interface Distribution {
  p50: number;
  p95: number;
  max: number;
}

interface PhaseReport {
  requests: number;
  bytes: number;
  tileMs: Distribution;
  settleMs: number;
  frameMs: Distribution;
  longTasks: { count: number; longestMs: number };
}

interface PerfWindow extends Window {
  __frameIntervals: number[];
  __longTasks: number[];
}

const report: Record<string, { load: PhaseReport; pan: PhaseReport }> = {};

test.afterAll(() => {
  if (Object.keys(report).length === 0) return;
  mkdirSync(REPORT_DIRECTORY, { recursive: true });
  const json = JSON.stringify(report, null, 2);
  writeFileSync(REPORT_FILE, json + '\n');
  process.stdout.write(
    `\nParcel map performance report (${REPORT_FILE}, frame numbers are software WebGL):\n${json}\n`,
  );
});

async function parcelBounds(request: APIRequestContext): Promise<ParcelBounds> {
  const response = await request.get('/api/map-config');
  const config = (await response.json()) as { parcels: { bounds: ParcelBounds } | null };
  const bounds = config.parcels?.bounds;
  if (bounds === undefined || bounds.east - bounds.west < MIN_DISTRICT_WIDTH_DEGREES) {
    throw new Error('Import the district first: make data (parcel bounds are too small for the district).');
  }
  return bounds;
}

function distribution(values: number[]): Distribution {
  if (values.length === 0) return { p50: 0, p95: 0, max: 0 };
  const sorted = [...values].sort((a, b) => a - b);
  const nearestRank = (fraction: number): number =>
    sorted[Math.min(sorted.length - 1, Math.max(0, Math.ceil(fraction * sorted.length) - 1))] ?? 0;
  return { p50: round(nearestRank(0.5)), p95: round(nearestRank(0.95)), max: round(sorted[sorted.length - 1] ?? 0) };
}

function round(value: number): number {
  return Math.round(value * 10) / 10;
}

/** Records every parcel tile request of the page and tells when no more are coming. */
class TileTracker {
  records: TileRecord[] = [];
  private inFlight = 0;
  private lastActivity = Date.now();
  private lastResponse = Date.now();
  private phaseStartedAt = Date.now();
  private requests = 0;

  constructor(page: Page) {
    page.on('request', (request) => {
      if (!isParcelTile(request)) return;
      this.inFlight += 1;
      this.requests += 1;
      this.lastActivity = Date.now();
    });
    page.on('requestfinished', (request) => {
      if (!isParcelTile(request)) return;
      void this.finished(request);
    });
    page.on('requestfailed', (request) => {
      if (!isParcelTile(request)) return;
      this.inFlight -= 1;
      this.lastActivity = Date.now();
      this.records.push({ status: 0, bytes: 0, durationMs: 0 });
    });
  }

  startPhase(): void {
    this.records = [];
    this.requests = 0;
    this.phaseStartedAt = Date.now();
    this.lastActivity = this.phaseStartedAt;
    this.lastResponse = this.phaseStartedAt;
  }

  /**
   * Waits until no parcel tile has been in flight for a second; gives up after 15 s.
   *
   * @param since the time to measure the settle time from (default: the start of the phase)
   * @returns the time from `since` to the last parcel tile response
   */
  async waitUntilQuiet(page: Page, atLeastOneRequest: boolean, since = this.phaseStartedAt): Promise<number> {
    const deadline = Date.now() + GIVE_UP_MS;
    while (Date.now() < deadline) {
      const quiet = this.inFlight === 0 && Date.now() - this.lastActivity >= QUIET_MS;
      if (quiet && (!atLeastOneRequest || this.requests > 0)) break;
      await page.waitForTimeout(100);
    }
    return Math.max(0, this.lastResponse - since);
  }

  private async finished(request: Request): Promise<void> {
    const response = await request.response();
    const sizes = await request.sizes();
    this.records.push({
      status: response?.status() ?? 0,
      bytes: sizes.responseBodySize,
      durationMs: request.timing().responseEnd,
    });
    this.inFlight -= 1;
    this.lastActivity = Date.now();
    this.lastResponse = this.lastActivity;
  }
}

function isParcelTile(request: Request): boolean {
  return request.url().includes('/tiles/parcels/');
}

async function framesAndLongTasks(page: Page): Promise<{ frames: number[]; longTasks: number[] }> {
  return page.evaluate(() => {
    const perf = window as unknown as PerfWindow;
    return { frames: [...perf.__frameIntervals], longTasks: [...perf.__longTasks] };
  });
}

async function resetFramesAndLongTasks(page: Page): Promise<void> {
  await page.evaluate(() => {
    const perf = window as unknown as PerfWindow;
    perf.__frameIntervals.length = 0;
    perf.__longTasks.length = 0;
  });
}

async function phaseReport(page: Page, tracker: TileTracker, settleMs: number): Promise<PhaseReport> {
  const { frames, longTasks } = await framesAndLongTasks(page);
  return {
    requests: tracker.records.length,
    bytes: tracker.records.reduce((sum, record) => sum + record.bytes, 0),
    tileMs: distribution(tracker.records.map((record) => record.durationMs)),
    settleMs,
    frameMs: distribution(frames),
    longTasks: { count: longTasks.length, longestMs: round(Math.max(0, ...longTasks)) },
  };
}

async function pan(page: Page): Promise<void> {
  const viewport = page.viewportSize();
  const centerX = (viewport?.width ?? 1920) / 2;
  const centerY = (viewport?.height ?? 1080) / 2;
  for (const [dx, dy] of PAN_DRAGS) {
    await page.mouse.move(centerX, centerY);
    await page.mouse.down();
    await page.mouse.move(centerX + dx, centerY + dy, { steps: PAN_STEPS });
    await page.mouse.up();
  }
}

function expectParcelTilesAnswered(records: TileRecord[], phase: string): void {
  const statuses = records.map((record) => record.status);
  expect(
    statuses.filter((status) => status !== 200 && status !== 204),
    `${phase}: parcel tile responses other than 200 or 204`,
  ).toEqual([]);
}

async function measure(page: Page, request: APIRequestContext, define: (bounds: ParcelBounds) => View): Promise<void> {
  const view = define(await parcelBounds(request));

  await page.addInitScript(() => {
    const perf = window as unknown as PerfWindow;
    perf.__frameIntervals = [];
    perf.__longTasks = [];
    let last = performance.now();
    const tick = (now: number): void => {
      perf.__frameIntervals.push(now - last);
      last = now;
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) perf.__longTasks.push(entry.duration);
    }).observe({ type: 'longtask', buffered: true });
  });
  await page.route('**/api/map-config', async (route) => {
    const response = await route.fetch();
    const body = (await response.json()) as Record<string, unknown>;
    await route.fulfill({
      response,
      json: {
        ...body,
        initialView: { center: { longitude: view.longitude, latitude: view.latitude }, zoom: view.zoom },
      },
    });
  });

  const tracker = new TileTracker(page);
  tracker.startPhase();
  await page.goto('/');
  await expect(page.locator('.maplibregl-canvas')).toBeVisible();
  const loadSettleMs = await tracker.waitUntilQuiet(page, true);
  const loadReport = await phaseReport(page, tracker, loadSettleMs);
  const loadRecords = [...tracker.records];

  await resetFramesAndLongTasks(page);
  tracker.startPhase();
  await pan(page);
  // The drags take long in software WebGL, so the pan settle time counts from the end of the last drag.
  const panSettleMs = await tracker.waitUntilQuiet(page, false, Date.now());
  const panReport = await phaseReport(page, tracker, panSettleMs);

  report[view.name] = { load: loadReport, pan: panReport };

  expect(loadReport.requests, `${view.name}: parcel tile requests while loading`).toBeGreaterThan(0);
  expect(loadReport.requests, `${view.name}: parcel tile requests while loading`).toBeLessThanOrEqual(
    MAX_PARCEL_TILE_REQUESTS,
  );
  expect(loadReport.bytes, `${view.name}: parcel tile bytes while loading`).toBeLessThanOrEqual(MAX_PARCEL_BYTES);
  expect(loadReport.tileMs.p95, `${view.name}: p95 parcel tile duration (ms)`).toBeLessThanOrEqual(MAX_P95_TILE_MS);
  expect(loadReport.settleMs, `${view.name}: time until the last parcel tile arrived (ms)`).toBeLessThanOrEqual(
    MAX_SETTLE_MS,
  );
  expectParcelTilesAnswered(loadRecords, `${view.name} load`);
  expectParcelTilesAnswered(tracker.records, `${view.name} pan`);
}

test('opens the whole district at zoom 10 within the tile budgets', async ({ page, request }) => {
  await measure(page, request, (bounds) => ({
    name: 'district',
    longitude: (bounds.west + bounds.east) / 2,
    latitude: (bounds.south + bounds.north) / 2,
    zoom: 10,
  }));
});

test('opens Jičín at zoom 12 within the tile budgets', async ({ page, request }) => {
  await measure(page, request, () => ({ name: 'jicin-12', ...JICIN, zoom: 12 }));
});

test('opens Jičín at zoom 14 within the tile budgets', async ({ page, request }) => {
  await measure(page, request, () => ({ name: 'jicin-14', ...JICIN, zoom: 14 }));
});
