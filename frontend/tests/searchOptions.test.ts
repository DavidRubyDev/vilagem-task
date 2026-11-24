import { describe, expect, it } from 'vitest';
import { nextActive, searchGroups } from '../src/searchOptions';
import type { ParcelResult, PlaceResult } from '../src/searchResults';
import { textsOf } from './support/texts';

function parcel(id: number, parcelNumber: string, area: string, municipality: string): ParcelResult {
  return {
    id,
    parcelNumber,
    kind: 'land',
    cadastralArea: { code: 659541, name: area },
    municipality: { code: 572659, name: municipality },
  };
}

describe('searchGroups', () => {
  it('leaves out empty groups', () => {
    expect(searchGroups({ places: [], parcels: [] }, textsOf('cs'))).toEqual([]);
  });

  it('makes one group of parcels with the label of the language', () => {
    const results = { places: [], parcels: [parcel(1, 'st. 72', 'Jičín', 'Jičín')] };

    const [group, ...others] = searchGroups(results, textsOf('cs'));

    expect(others).toEqual([]);
    expect(group?.key).toBe('parcels');
    expect(group?.label).toBe('Parcely');
    expect(searchGroups(results, textsOf('en'))[0]?.label).toBe('Parcels');
  });

  it('names a parcel option by its number and its context by the cadastral area', () => {
    const results = { places: [], parcels: [parcel(1753295604, 'st. 72', 'Jičín', 'Jičín')] };

    const option = searchGroups(results, textsOf('cs'))[0]?.options[0];

    expect(option).toEqual({
      id: 'map-search-option-0',
      name: 'Parcela st. 72',
      context: 'Jičín (659541)',
      target: { kind: 'parcel', id: 1753295604 },
    });
  });

  it('adds the municipality to the context when it differs from the cadastral area', () => {
    const results = { places: [], parcels: [parcel(2, '867/121', 'Robousy', 'Jičín')] };

    expect(searchGroups(results, textsOf('cs'))[0]?.options[0]?.context).toBe('Robousy (659541), Jičín');
  });

  it('numbers the option ids in order', () => {
    const results = {
      places: [],
      parcels: [parcel(1, '5', 'Jičín', 'Jičín'), parcel(2, '6', 'Jičín', 'Jičín'), parcel(3, '7', 'Jičín', 'Jičín')],
    };

    const options = searchGroups(results, textsOf('cs')).flatMap((group) => group.options);

    expect(options.map((option) => option.id)).toEqual([
      'map-search-option-0',
      'map-search-option-1',
      'map-search-option-2',
    ]);
  });

  it('names the parcel in English', () => {
    const results = { places: [], parcels: [parcel(1, 'st. 72', 'Jičín', 'Jičín')] };

    expect(searchGroups(results, textsOf('en'))[0]?.options[0]?.name).toBe('Parcel st. 72');
  });
});

function place(overrides: Partial<PlaceResult> & Pick<PlaceResult, 'kind' | 'name'>): PlaceResult {
  return {
    code: 1,
    context: '',
    center: { longitude: 15.35, latitude: 50.43 },
    bounds: { west: 15.3, south: 50.4, east: 15.4, north: 50.5 },
    parcelId: null,
    ...overrides,
  };
}

describe('searchGroups with places', () => {
  it('lists the places before the parcels, with the group labels of the language', () => {
    const results = {
      places: [place({ kind: 'municipality', name: 'Jičín' })],
      parcels: [parcel(1, 'st. 72', 'Jičín', 'Jičín')],
    };

    const groups = searchGroups(results, textsOf('cs'));

    expect(groups.map((group) => group.key)).toEqual(['places', 'parcels']);
    expect(groups.map((group) => group.label)).toEqual(['Místa', 'Parcely']);
    expect(searchGroups(results, textsOf('en')).map((group) => group.label)).toEqual(['Places', 'Parcels']);
  });

  it('leaves out the places group when there are no places', () => {
    const results = { places: [], parcels: [parcel(1, 'st. 72', 'Jičín', 'Jičín')] };

    expect(searchGroups(results, textsOf('cs')).map((group) => group.key)).toEqual(['parcels']);
  });

  it('numbers the options across the groups', () => {
    const results = {
      places: [place({ kind: 'street', name: 'Husova' }), place({ kind: 'street', name: 'Tyršova', code: 2 })],
      parcels: [parcel(1, '5', 'Jičín', 'Jičín')],
    };

    const ids = searchGroups(results, textsOf('cs')).flatMap((group) => group.options.map((option) => option.id));

    expect(ids).toEqual(['map-search-option-0', 'map-search-option-1', 'map-search-option-2']);
  });

  it.each([
    ['municipality', 'Obec', 'Municipality'],
    ['cadastralArea', 'Katastrální území', 'Cadastral area'],
    ['municipalityPart', 'Část obce', 'Part of a municipality'],
    ['street', 'Ulice', 'Street'],
    ['address', 'Adresa', 'Address'],
  ] as const)('names the kind %s in both languages', (kind, czech, english) => {
    const results = { places: [place({ kind, name: 'X' })], parcels: [] };

    expect(searchGroups(results, textsOf('cs'))[0]?.options[0]?.context).toBe(czech);
    expect(searchGroups(results, textsOf('en'))[0]?.options[0]?.context).toBe(english);
  });

  it('puts the context of a place after the kind name', () => {
    const results = {
      places: [place({ kind: 'municipalityPart', name: 'Staré Město', context: 'Jičín' })],
      parcels: [],
    };

    const option = searchGroups(results, textsOf('cs'))[0]?.options[0];

    expect(option?.name).toBe('Staré Město');
    expect(option?.context).toBe('Část obce · Jičín');
  });

  it('targets the bounds of a place, or its centre when it has none', () => {
    const withBounds = place({ kind: 'street', name: 'Husova' });
    const point = place({
      kind: 'municipalityPart',
      name: 'Podhrad',
      bounds: null,
      center: { longitude: 15.1, latitude: 50.2 },
    });

    const options = searchGroups({ places: [withBounds, point], parcels: [] }, textsOf('cs'))[0]?.options;

    expect(options?.[0]?.target).toEqual({ kind: 'place', center: [15.35, 50.43], bounds: [15.3, 50.4, 15.4, 50.5] });
    expect(options?.[1]?.target).toEqual({ kind: 'place', center: [15.1, 50.2], bounds: null });
  });

  it('targets the parcel of an address', () => {
    const results = {
      places: [
        place({
          kind: 'address',
          name: 'Valdštejnovo náměstí 58',
          context: 'Staré Město, Jičín',
          parcelId: 1753295604,
        }),
      ],
      parcels: [],
    };

    const option = searchGroups(results, textsOf('cs'))[0]?.options[0];

    expect(option?.target).toEqual({ kind: 'parcel', id: 1753295604 });
    expect(option?.context).toBe('Adresa · Staré Město, Jičín');
  });

  it('treats an address without a parcel as a place', () => {
    const results = { places: [place({ kind: 'address', name: 'X 1', bounds: null })], parcels: [] };

    expect(searchGroups(results, textsOf('cs'))[0]?.options[0]?.target.kind).toBe('place');
  });
});

describe('nextActive', () => {
  it('has no active option in an empty list', () => {
    expect(nextActive(null, 0, 1)).toBeNull();
    expect(nextActive(null, 0, -1)).toBeNull();
    expect(nextActive(3, 0, 1)).toBeNull();
  });

  it('starts at the first option going down and at the last going up', () => {
    expect(nextActive(null, 3, 1)).toBe(0);
    expect(nextActive(null, 3, -1)).toBe(2);
  });

  it('moves by one', () => {
    expect(nextActive(0, 3, 1)).toBe(1);
    expect(nextActive(2, 3, -1)).toBe(1);
  });

  it('wraps around at both ends', () => {
    expect(nextActive(2, 3, 1)).toBe(0);
    expect(nextActive(0, 3, -1)).toBe(2);
  });

  it('stays on the only option', () => {
    expect(nextActive(0, 1, 1)).toBe(0);
    expect(nextActive(0, 1, -1)).toBe(0);
  });
});
