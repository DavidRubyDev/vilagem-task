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
