import { describe, expect, it } from 'vitest';
import { loadMapConfiguration } from '../src/mapConfiguration';

const validBody = {
  initialView: { center: { longitude: 15.351653, latitude: 50.437045 }, zoom: 12 },
  baseMap: {
    tileUrlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    tileSize: 256,
    maxZoom: 19,
    attribution: { text: '© OpenStreetMap contributors', url: 'https://www.openstreetmap.org/copyright' },
  },
};

function jsonResponse(body: unknown): Response {
  return new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } });
}

describe('loadMapConfiguration', () => {
  it('requests the given URL asking for JSON', async () => {
    const calls: { input: Parameters<typeof fetch>[0]; init: Parameters<typeof fetch>[1] }[] = [];
    const fetchFn: typeof fetch = (input, init) => {
      calls.push({ input, init });
      return Promise.resolve(jsonResponse(validBody));
    };

    await loadMapConfiguration(fetchFn, '/api/map-config');

    expect(calls).toEqual([{ input: '/api/map-config', init: { headers: { Accept: 'application/json' } } }]);
  });

  it('returns the configuration of a valid response', async () => {
    const configuration = await loadMapConfiguration(() => Promise.resolve(jsonResponse(validBody)), '/api/map-config');

    expect(configuration).toEqual(validBody);
  });
});
