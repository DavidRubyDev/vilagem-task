import { describe, expect, it } from 'vitest';
import { LAND_TYPE_COLOURS, landTypeFillColour } from '../src/landTypes';

describe('LAND_TYPE_COLOURS', () => {
  it('has exactly the published land type codes', () => {
    expect([...LAND_TYPE_COLOURS.keys()]).toEqual([2, 3, 4, 5, 6, 7, 8, 10, 11, 13, 14]);
  });

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
    expect(LAND_TYPE_COLOURS.get(code)).toBe(colour);
  });

  it('gives permanent grassland (7 and 8) one colour', () => {
    expect(LAND_TYPE_COLOURS.get(7)).toBe(LAND_TYPE_COLOURS.get(8));
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

    for (const [code, colour] of LAND_TYPE_COLOURS) {
      const position = labels.findIndex(
        (entry, index) => index % 2 === 0 && (entry === code || (Array.isArray(entry) && entry.includes(code))),
      );
      expect(position).toBeGreaterThanOrEqual(0);
      expect(labels[position + 1]).toBe(colour);
    }
  });
});
