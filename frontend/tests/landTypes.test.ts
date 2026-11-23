import { describe, expect, it } from 'vitest';
import { LAND_TYPES, landTypeFillColour, landTypeName } from '../src/landTypes';

describe('LAND_TYPES', () => {
  it('has exactly the published land type codes', () => {
    expect([...LAND_TYPES.keys()]).toEqual([2, 3, 4, 5, 6, 7, 8, 10, 11, 13, 14]);
  });

  it.each([
    [2, 'Arable land'],
    [3, 'Hop field'],
    [4, 'Vineyard'],
    [5, 'Garden'],
    [6, 'Orchard'],
    [7, 'Permanent grassland'],
    [8, 'Permanent grassland'],
    [10, 'Forest land'],
    [11, 'Water area'],
    [13, 'Built-up area and courtyard'],
    [14, 'Other area'],
  ])('names land type %i %s', (code, name) => {
    expect(LAND_TYPES.get(code)?.name).toBe(name);
  });
});

describe('landTypeName', () => {
  it('returns the name of a known code', () => {
    expect(landTypeName(13)).toBe('Built-up area and courtyard');
  });

  it('names an unknown code with the code', () => {
    expect(landTypeName(99)).toBe('Unknown land type (code 99)');
  });
});

describe('land type colours', () => {
  it.each([
    [2, '#f2d16b'],
    [3, '#b5cf6b'],
    [4, '#a96ab2'],
    [5, '#9fd27f'],
    [6, '#5fb05f'],
    [7, '#c7e59a'],
    [8, '#c7e59a'],
    [10, '#2e8b57'],
    [11, '#5aa9e6'],
    [13, '#d9735b'],
    [14, '#bdbdbd'],
  ])('maps land type %i to %s', (code, colour) => {
    expect(LAND_TYPES.get(code)?.colour).toBe(colour);
  });

  it('gives permanent grassland (7 and 8) one colour', () => {
    expect(LAND_TYPES.get(7)?.colour).toBe(LAND_TYPES.get(8)?.colour);
  });
});

describe('landTypeFillColour', () => {
  it('is a match on the landType property with the fallback colour last', () => {
    const expression = landTypeFillColour();

    expect(expression[0]).toBe('match');
    expect(expression[1]).toEqual(['get', 'landType']);
    expect(expression[expression.length - 1]).toBe('#9e9e9e');
  });

  it('lists every land type code with its colour', () => {
    const expression = landTypeFillColour() as unknown[];
    const labels = expression.slice(2, -1);

    for (const [code, { colour }] of LAND_TYPES) {
      const position = labels.findIndex(
        (entry, index) => index % 2 === 0 && (entry === code || (Array.isArray(entry) && entry.includes(code))),
      );
      expect(position).toBeGreaterThanOrEqual(0);
      expect(labels[position + 1]).toBe(colour);
    }
  });
});
