import { describe, expect, it } from 'vitest';
import { loadParcelDetail, ParcelDetailError, parcelDetailUrl } from '../src/parcelDetail';

const validBody = {
  id: 1753295604,
  parcelNumber: 'st. 72',
  kind: 'building',
  cadastralArea: { code: 659541, name: 'Jičín' },
  municipality: { code: 572659, name: 'Jičín' },
  landTypeCode: 13,
  landUseCode: null,
  areaSquareMetres: 455,
  cadastreViewerUrl: 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1753295604',
  boundary: {
    type: 'Polygon',
    coordinates: [
      [
        [15.0, 50.0],
        [15.1, 50.0],
        [15.1, 50.1],
        [15.0, 50.0],
      ],
    ],
  },
};

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

const URL = '/api/parcels/v1/1753295604';

describe('parcelDetailUrl', () => {
  it('replaces {id}', () => {
    expect(parcelDetailUrl('/api/parcels/v1/{id}', 1753295604)).toBe('/api/parcels/v1/1753295604');
  });
});

describe('loadParcelDetail', () => {
  it('returns the detail of a valid body', async () => {
    const result = await loadParcelDetail(() => Promise.resolve(jsonResponse(validBody)), URL);

    expect(result).toEqual({ found: true, detail: validBody });
  });

  it('accepts a land use code', async () => {
    const result = await loadParcelDetail(() => Promise.resolve(jsonResponse({ ...validBody, landUseCode: 17 })), URL);

    expect(result).toMatchObject({ found: true, detail: { landUseCode: 17 } });
  });

  it('reports a 404 as not found', async () => {
    const result = await loadParcelDetail(() => Promise.resolve(jsonResponse({ error: 'not_found' }, 404)), URL);

    expect(result).toEqual({ found: false });
  });

  it('requests the URL asking for JSON and passes the abort signal on', async () => {
    const controller = new AbortController();
    const calls: { input: Parameters<typeof fetch>[0]; init: Parameters<typeof fetch>[1] }[] = [];
    const fetchFn: typeof fetch = (input, init) => {
      calls.push({ input, init });
      return Promise.resolve(jsonResponse(validBody));
    };

    await loadParcelDetail(fetchFn, URL, controller.signal);

    expect(calls).toEqual([
      { input: URL, init: { headers: { Accept: 'application/json' }, signal: controller.signal } },
    ]);
  });

  it('rethrows an abort unchanged', async () => {
    const abort = new DOMException('Aborted', 'AbortError');

    await expect(loadParcelDetail(() => Promise.reject(abort), URL)).rejects.toBe(abort);
  });

  describe('throws ParcelDetailError when', () => {
    const rejects = async (fetchFn: typeof fetch): Promise<void> => {
      await expect(loadParcelDetail(fetchFn, URL)).rejects.toBeInstanceOf(ParcelDetailError);
    };

    it('the status is 500', async () => {
      await rejects(() => Promise.resolve(jsonResponse({ error: 'internal_error' }, 500)));
    });

    it('the request fails', async () => {
      await rejects(() => Promise.reject(new TypeError('Failed to fetch')));
    });

    it('the body is not JSON', async () => {
      await rejects(() => Promise.resolve(new Response('<html>', { status: 200 })));
    });

    it('the parcel number is missing', async () => {
      const body: Record<string, unknown> = { ...validBody };
      delete body['parcelNumber'];

      await rejects(() => Promise.resolve(jsonResponse(body)));
    });

    it('the land use code is a string', async () => {
      await rejects(() => Promise.resolve(jsonResponse({ ...validBody, landUseCode: '17' })));
    });

    it('the kind is neither building nor land', async () => {
      await rejects(() => Promise.resolve(jsonResponse({ ...validBody, kind: 'other' })));
    });

    it('the cadastre viewer url is missing', async () => {
      const body: Record<string, unknown> = { ...validBody };
      delete body['cadastreViewerUrl'];

      await rejects(() => Promise.resolve(jsonResponse(body)));
    });

    it('the cadastre viewer url is not https', async () => {
      await rejects(() => Promise.resolve(jsonResponse({ ...validBody, cadastreViewerUrl: 'javascript:alert(1)' })));
      await rejects(() =>
        Promise.resolve(jsonResponse({ ...validBody, cadastreViewerUrl: 'http://nahlizenidokn.cuzk.gov.cz/' })),
      );
    });

    it('the boundary is missing', async () => {
      const body: Record<string, unknown> = { ...validBody };
      delete body['boundary'];

      await rejects(() => Promise.resolve(jsonResponse(body)));
    });

    it('the boundary is not a polygon', async () => {
      await rejects(() =>
        Promise.resolve(jsonResponse({ ...validBody, boundary: { type: 'Point', coordinates: [15, 50] } })),
      );
    });

    it('a boundary position is not two numbers', async () => {
      await rejects(() =>
        Promise.resolve(jsonResponse({ ...validBody, boundary: { type: 'Polygon', coordinates: [[['15', 50]]] } })),
      );
    });

    it('the cadastral area has no name', async () => {
      await rejects(() => Promise.resolve(jsonResponse({ ...validBody, cadastralArea: { code: 659541 } })));
    });
  });
});
