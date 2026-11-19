import { describe, expect, it } from 'vitest';
import { loadMapConfiguration, MapConfigurationError } from '../src/mapConfiguration';

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

  describe('rejects with MapConfigurationError when', () => {
    const rejectsWith = async (fetchFn: typeof fetch): Promise<void> => {
      await expect(loadMapConfiguration(fetchFn, '/api/map-config')).rejects.toBeInstanceOf(MapConfigurationError);
    };

    it('the fetch fails with a network error', async () => {
      await rejectsWith(() => Promise.reject(new TypeError('Failed to fetch')));
    });

    it('the status is 500', async () => {
      await rejectsWith(() => Promise.resolve(new Response('{"error":"internal_error"}', { status: 500 })));
    });

    it('the status is 404', async () => {
      await rejectsWith(() => Promise.resolve(new Response('{"error":"not_found"}', { status: 404 })));
    });

    it('the body is not JSON', async () => {
      await rejectsWith(() => Promise.resolve(new Response('<html>', { status: 200 })));
    });

    it('the center longitude is missing', async () => {
      const body = structuredClone(validBody) as { initialView: { center: Record<string, unknown> } };
      delete body.initialView.center['longitude'];

      await rejectsWith(() => Promise.resolve(jsonResponse(body)));
    });

    it('the zoom is a string', async () => {
      const body = structuredClone(validBody) as { initialView: Record<string, unknown> };
      body.initialView['zoom'] = '12';

      await rejectsWith(() => Promise.resolve(jsonResponse(body)));
    });

    it('the attribution url is not a string', async () => {
      const body = structuredClone(validBody) as { baseMap: { attribution: Record<string, unknown> } };
      body.baseMap.attribution['url'] = 42;

      await rejectsWith(() => Promise.resolve(jsonResponse(body)));
    });
  });
});
