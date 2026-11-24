import { describe, expect, it } from 'vitest';
import { loadSearchResults, SearchError } from '../src/searchResults';

const parcel = {
  id: 1753295604,
  parcelNumber: 'st. 72',
  kind: 'building',
  cadastralArea: { code: 659541, name: 'Jičín' },
  municipality: { code: 572659, name: 'Jičín' },
};

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

const place = {
  kind: 'municipality',
  code: 572659,
  name: 'Jičín',
  context: '',
  center: { longitude: 15.35, latitude: 50.43 },
  bounds: { west: 15.3, south: 50.4, east: 15.4, north: 50.5 },
  parcelId: null,
};

const address = {
  kind: 'address',
  code: 25344951,
  name: 'Valdštejnovo náměstí 58',
  context: 'Staré Město, Jičín',
  center: { longitude: 15.351066, latitude: 50.4373768 },
  bounds: null,
  parcelId: 1753295604,
};

const URL = '/api/search/v1?q=st.%2072&near=15.352,50.437';

async function load(response: Response | Error): Promise<Awaited<ReturnType<typeof loadSearchResults>>> {
  return loadSearchResults(
    () => (response instanceof Error ? Promise.reject(response) : Promise.resolve(response)),
    URL,
    new AbortController().signal,
  );
}

describe('loadSearchResults', () => {
  it('returns the results of a valid body', async () => {
    const outcome = await load(jsonResponse({ places: [], parcels: [parcel] }));

    expect(outcome).toEqual({ kind: 'found', results: { places: [], parcels: [parcel] } });
  });

  it('returns places with bounds and addresses with a parcel', async () => {
    const outcome = await load(jsonResponse({ places: [place, address], parcels: [] }));

    expect(outcome).toEqual({ kind: 'found', results: { places: [place, address], parcels: [] } });
  });

  it.each(['municipality', 'cadastralArea', 'municipalityPart', 'street', 'address'])(
    'accepts the kind %s',
    async (kind) => {
      const outcome = await load(jsonResponse({ places: [{ ...place, kind }], parcels: [] }));

      expect(outcome.kind).toBe('found');
    },
  );

  it('accepts empty lists', async () => {
    const outcome = await load(jsonResponse({ places: [], parcels: [] }));

    expect(outcome).toEqual({ kind: 'found', results: { places: [], parcels: [] } });
  });

  it('accepts a land parcel', async () => {
    const outcome = await load(jsonResponse({ places: [], parcels: [{ ...parcel, kind: 'land' }] }));

    expect(outcome).toMatchObject({ kind: 'found', results: { parcels: [{ kind: 'land' }] } });
  });

  it('reports a 404 as outdated', async () => {
    expect(await load(jsonResponse({ error: 'not_found' }, 404))).toEqual({ kind: 'outdated' });
  });

  it('requests the URL asking for JSON and passes the abort signal on', async () => {
    const controller = new AbortController();
    const calls: { input: Parameters<typeof fetch>[0]; init: Parameters<typeof fetch>[1] }[] = [];

    await loadSearchResults(
      (input, init) => {
        calls.push({ input, init });
        return Promise.resolve(jsonResponse({ places: [], parcels: [] }));
      },
      URL,
      controller.signal,
    );

    expect(calls).toEqual([
      { input: URL, init: { headers: { Accept: 'application/json' }, signal: controller.signal } },
    ]);
  });

  describe('throws a SearchError when', () => {
    const rejects = async (response: Response | Error): Promise<void> => {
      await expect(load(response)).rejects.toBeInstanceOf(SearchError);
    };

    it('the status is 500', async () => {
      await rejects(jsonResponse({ error: 'internal_error' }, 500));
    });

    it('the status is 400', async () => {
      await rejects(jsonResponse({ error: 'bad_request' }, 400));
    });

    it('the request fails', async () => {
      await rejects(new TypeError('Failed to fetch'));
    });

    it('the body is not JSON', async () => {
      await rejects(new Response('<html>', { status: 200 }));
    });

    it('the body is not an object', async () => {
      await rejects(jsonResponse([]));
    });

    it('parcels is missing', async () => {
      await rejects(jsonResponse({ places: [] }));
    });

    it('places is missing', async () => {
      await rejects(jsonResponse({ parcels: [] }));
    });

    it('places is not a list', async () => {
      await rejects(jsonResponse({ places: {}, parcels: [] }));
    });

    it('a place has an unknown kind', async () => {
      await rejects(jsonResponse({ places: [{ ...place, kind: 'river' }], parcels: [] }));
    });

    it('a place has no name or a name that is not text', async () => {
      await rejects(jsonResponse({ places: [{ ...place, name: 5 }], parcels: [] }));
      await rejects(jsonResponse({ places: [{ ...place, context: null }], parcels: [] }));
    });

    it('a place centre is not finite numbers', async () => {
      await rejects(jsonResponse({ places: [{ ...place, center: { longitude: '15', latitude: 50 } }], parcels: [] }));
      await rejects(jsonResponse({ places: [{ ...place, center: null }], parcels: [] }));
    });

    it('place bounds are incomplete or not numbers', async () => {
      await rejects(jsonResponse({ places: [{ ...place, bounds: { west: 1, south: 2, east: 3 } }], parcels: [] }));
      await rejects(
        jsonResponse({ places: [{ ...place, bounds: { west: 1, south: 2, east: 3, north: 'x' } }], parcels: [] }),
      );
      await rejects(jsonResponse({ places: [{ ...place, bounds: undefined }], parcels: [] }));
    });

    it('a place parcel id is 0, fractional, too large or not a number', async () => {
      await rejects(jsonResponse({ places: [{ ...address, parcelId: 0 }], parcels: [] }));
      await rejects(jsonResponse({ places: [{ ...address, parcelId: 1.5 }], parcels: [] }));
      await rejects(jsonResponse({ places: [{ ...address, parcelId: 2 ** 53 }], parcels: [] }));
      await rejects(jsonResponse({ places: [{ ...address, parcelId: '1' }], parcels: [] }));
      await rejects(jsonResponse({ places: [{ ...address, parcelId: undefined }], parcels: [] }));
    });

    it('a place is not an object', async () => {
      await rejects(jsonResponse({ places: [5], parcels: [] }));
    });

    it('a parcel has an unknown kind', async () => {
      await rejects(jsonResponse({ places: [], parcels: [{ ...parcel, kind: 'garden' }] }));
    });

    it('a parcel id is not a positive safe integer', async () => {
      await rejects(jsonResponse({ places: [], parcels: [{ ...parcel, id: 0 }] }));
      await rejects(jsonResponse({ places: [], parcels: [{ ...parcel, id: 1.5 }] }));
      await rejects(jsonResponse({ places: [], parcels: [{ ...parcel, id: 2 ** 53 }] }));
    });

    it('a parcel number is not a string', async () => {
      await rejects(jsonResponse({ places: [], parcels: [{ ...parcel, parcelNumber: 72 }] }));
    });

    it('a cadastral area has no name', async () => {
      await rejects(jsonResponse({ places: [], parcels: [{ ...parcel, cadastralArea: { code: 659541 } }] }));
    });

    it('a municipality code is not a number', async () => {
      await rejects(jsonResponse({ places: [], parcels: [{ ...parcel, municipality: { code: '1', name: 'x' } }] }));
    });
  });

  it('rethrows an abort unchanged', async () => {
    const abort = new DOMException('The operation was aborted.', 'AbortError');

    await expect(load(abort)).rejects.toBe(abort);
  });
});
