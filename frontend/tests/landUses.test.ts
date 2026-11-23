import { describe, expect, it } from 'vitest';
import { LAND_USE_NAMES, landUseName } from '../src/landUses';

describe('LAND_USE_NAMES', () => {
  it('has exactly the codes 1 to 30', () => {
    expect([...LAND_USE_NAMES.keys()]).toEqual(Array.from({ length: 30 }, (_, index) => index + 1));
  });

  it.each([
    [1, 'Greenhouse, hotbed'],
    [7, 'Natural or regulated watercourse bed'],
    [15, 'Motorway'],
    [17, 'Other road'],
    [26, 'Miscellaneous area'],
    [30, 'Field boundary, slope'],
  ])('names code %i %s', (code, name) => {
    expect(LAND_USE_NAMES.get(code)).toBe(name);
  });
});

describe('landUseName', () => {
  it('returns the name of a known code', () => {
    expect(landUseName(17)).toBe('Other road');
  });

  it('names an unknown code with the code', () => {
    expect(landUseName(31)).toBe('Unknown land use (code 31)');
  });
});
