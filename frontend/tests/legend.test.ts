import { describe, expect, it } from 'vitest';
import { FALLBACK_COLOUR, LAND_TYPES } from '../src/landTypes';
import { legendEntries } from '../src/legend';

describe('legendEntries', () => {
  it('lists the ten land types and the unknown fallback', () => {
    expect(legendEntries()).toHaveLength(11);
  });

  it('names the rows in code order, ending with the unknown land type', () => {
    expect(legendEntries().map((entry) => entry.name)).toEqual([
      'Arable land',
      'Hop field',
      'Vineyard',
      'Garden',
      'Orchard',
      'Permanent grassland',
      'Forest land',
      'Water area',
      'Built-up area and courtyard',
      'Other area',
      'Unknown land type',
    ]);
  });

  it('shows permanent grassland once, although two codes share it', () => {
    expect(legendEntries().filter((entry) => entry.name === 'Permanent grassland')).toHaveLength(1);
  });

  it('uses the colour of the land type of the same name', () => {
    const colours = new Map([...LAND_TYPES.values()].map((style) => [style.name, style.colour]));
    for (const entry of legendEntries().slice(0, -1)) {
      expect(entry.colour).toBe(colours.get(entry.name));
    }
  });

  it('ends with the fallback grey of unknown land types', () => {
    expect(legendEntries().at(-1)).toEqual({ name: 'Unknown land type', colour: '#9e9e9e' });
    expect(FALLBACK_COLOUR).toBe('#9e9e9e');
  });
});
