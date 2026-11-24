import { describe, expect, it } from 'vitest';
import { nextActive, searchGroups } from '../src/searchOptions';
import type { ParcelResult } from '../src/searchResults';
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
