import { mkdirSync, writeFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

// The search must answer while the user types, so every query of the fixed list has to be fast over HTTP with the
// whole district imported. The requests are made with Node's fetch, not with Playwright's request context or the
// browser, so the measurement is the server and the network only (about 3 ms for a search), without the host lookup
// of the request context.
const MAX_P95_MS = 30;
const REQUESTS_PER_QUERY = 20;
const REPORT_DIRECTORY = 'var/perf';
const REPORT_FILE = `${REPORT_DIRECTORY}/search-perf.json`;
const MAP_CENTRE = '15.352,50.437';
const APP_URL = process.env['APP_URL'] ?? 'http://parcel-map:8000';

const PLACE_QUERIES: readonly string[] = [
  'ji',
  'jicin',
  'nova paka',
  'husova',
  'husova 1',
  'robousy 1',
  'nadrazni',
  'na',
];
const PARCEL_QUERIES: readonly string[] = ['80/2', 'st. 72', '1171', '867/121 robousy'];
const QUERIES: readonly string[] = [...PLACE_QUERIES, ...PARCEL_QUERIES];

interface QueryReport {
  p50: number;
  p95: number;
  max: number;
}

const report: Record<string, QueryReport> = {};

test.afterAll(() => {
  if (Object.keys(report).length === 0) return;
  mkdirSync(REPORT_DIRECTORY, { recursive: true });
  const json = JSON.stringify(report, null, 2);
  writeFileSync(REPORT_FILE, json + '\n');
  process.stdout.write(`\nSearch performance report (${REPORT_FILE}):\n${json}\n`);
});

function nearestRank(sorted: number[], fraction: number): number {
  const index = Math.min(sorted.length - 1, Math.max(0, Math.ceil(fraction * sorted.length) - 1));
  return sorted[index] ?? 0;
}

function round(value: number): number {
  return Math.round(value * 10) / 10;
}

for (const query of QUERIES) {
  test(`answers "${query}" within ${String(MAX_P95_MS)} ms at the 95th percentile`, async () => {
    const config = await fetch(`${APP_URL}/api/map-config`);
    const body = (await config.json()) as { parcels: { searchUrl: string | null } | null };
    const searchUrl = body.parcels?.searchUrl;
    if (searchUrl === undefined || searchUrl === null) {
      throw new Error('Import the district first: make data (the map configuration has no searchUrl).');
    }
    const url = `${APP_URL}${searchUrl}?q=${encodeURIComponent(query)}&near=${MAP_CENTRE}`;

    const durations: number[] = [];
    for (let attempt = 0; attempt < REQUESTS_PER_QUERY; attempt += 1) {
      const startedAt = performance.now();
      const response = await fetch(url);
      await response.text();
      durations.push(performance.now() - startedAt);
      expect(response.status).toBe(200);
    }

    const sorted = [...durations].sort((a, b) => a - b);
    const p95 = nearestRank(sorted, 0.95);
    report[query] = {
      p50: round(nearestRank(sorted, 0.5)),
      p95: round(p95),
      max: round(sorted[sorted.length - 1] ?? 0),
    };
    expect(p95).toBeLessThanOrEqual(MAX_P95_MS);
  });
}
