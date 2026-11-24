import { describe, expect, it } from 'vitest';
import { loadTexts, parseTexts, TextsError } from '../src/texts';
import { textsOf } from './support/texts';

const cs = textsOf('cs');
const en = textsOf('en');

describe('parseTexts', () => {
  it('accepts a flat object of strings', () => {
    expect(parseTexts('cs', { 'map.label': 'Mapa' }).language).toBe('cs');
  });

  it.each([
    ['null', null],
    ['an array', ['a']],
    ['a string', 'text'],
    ['an empty object', {}],
    ['a nested object', { a: { b: 'c' } }],
    ['a number value', { a: 1 }],
  ])('rejects %s', (_name, body) => {
    expect(() => parseTexts('cs', body)).toThrow(TextsError);
  });
});

describe('Texts', () => {
  it('replaces the placeholders of a text', () => {
    expect(cs.text('panel.heading', { parcelNumber: 'st. 72' })).toBe('Parcela st. 72');
    expect(en.text('panel.heading', { parcelNumber: 'st. 72' })).toBe('Parcel st. 72');
  });

  it('leaves a placeholder without a value as it is', () => {
    expect(en.text('panel.heading')).toBe('Parcel {parcelNumber}');
  });

  it('renders a key without a text as the key itself', () => {
    expect(en.text('no.such.key' as never)).toBe('no.such.key');
  });

  it('names land types, and an unknown code with the code', () => {
    expect(cs.landTypeName(13)).toBe('Zastavěná plocha a nádvoří');
    expect(en.landTypeName(13)).toBe('Built-up area and courtyard');
    expect(cs.landTypeName(99)).toBe('Neznámý druh pozemku (kód 99)');
    expect(en.landTypeName(99)).toBe('Unknown land type (code 99)');
  });

  it('names land uses, and an unknown code with the code', () => {
    expect(cs.landUseName(17)).toBe('Ostatní komunikace');
    expect(en.landUseName(17)).toBe('Other road');
    expect(cs.landUseName(31)).toBe('Neznámý způsob využití (kód 31)');
    expect(en.landUseName(31)).toBe('Unknown land use (code 31)');
  });

  it('formats numbers by the language', () => {
    expect(cs.formatNumber(13077)).toBe('13 077');
    expect(en.formatNumber(13077)).toBe('13,077');
  });

  it('words the interface strings in each language', () => {
    expect(en.text('map.zoomHint')).toBe('Zoom in to select a parcel.');
    expect(cs.text('map.zoomHint')).toBe('Pro výběr parcely přibližte mapu.');
    expect(en.text('notice.detailsMissing')).toBe(
      'Parcel details have not been imported yet. Run make data, then reload this page.',
    );
    expect(en.text('notice.parcelsMissing')).toBe(
      'Parcel data has not been imported yet. Run make data, then reload this page.',
    );
    expect(en.text('map.error')).toBe('The map could not be loaded. Please try again later.');
    expect(en.text('legend.title')).toBe('Land type');
    expect(en.text('legend.unknown')).toBe('Unknown land type');
    expect(cs.text('legend.title')).toBe('Druh pozemku');
  });
});

describe('loadTexts', () => {
  function respond(response: () => Response | Promise<Response>): { fetchFn: typeof fetch; requests: RequestInit[] } {
    const requests: RequestInit[] = [];
    const fetchFn = ((_url: string | URL | Request, init?: RequestInit) => {
      requests.push(init ?? {});
      return Promise.resolve(response());
    }) as typeof fetch;
    return { fetchFn, requests };
  }

  it('resolves the texts of a valid response, with their language', async () => {
    const { fetchFn } = respond(() => Response.json({ 'map.label': 'Map' }));

    const texts = await loadTexts(fetchFn, '/build/languages/en.json', 'en');

    expect(texts.language).toBe('en');
    expect(texts.text('map.label')).toBe('Map');
  });

  it.each([404, 500])('throws a TextsError naming the status %i', async (status) => {
    const { fetchFn } = respond(() => new Response('{}', { status }));

    await expect(loadTexts(fetchFn, '/x.json', 'en')).rejects.toThrow(new RegExp(String(status)));
    await expect(loadTexts(fetchFn, '/x.json', 'en')).rejects.toBeInstanceOf(TextsError);
  });

  it('throws a TextsError for a body that is not JSON', async () => {
    const { fetchFn } = respond(() => new Response('<html>'));

    await expect(loadTexts(fetchFn, '/x.json', 'en')).rejects.toBeInstanceOf(TextsError);
  });

  it('throws a TextsError for a body that is not texts', async () => {
    const { fetchFn } = respond(() => Response.json([1]));

    await expect(loadTexts(fetchFn, '/x.json', 'en')).rejects.toBeInstanceOf(TextsError);
  });

  it('throws a TextsError with the cause when the request fails', async () => {
    const cause = new TypeError('network down');
    const fetchFn = (() => Promise.reject(cause)) as typeof fetch;

    const error = await loadTexts(fetchFn, '/x.json', 'en').catch((caught: unknown) => caught);

    expect(error).toBeInstanceOf(TextsError);
    expect((error as TextsError).cause).toBe(cause);
  });

  it('asks for JSON', async () => {
    const { fetchFn, requests } = respond(() => Response.json({ a: 'b' }));

    await loadTexts(fetchFn, '/x.json', 'en');

    expect(requests[0]?.headers).toEqual({ Accept: 'application/json' });
  });
});
