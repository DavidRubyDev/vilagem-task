import { describe, expect, it } from 'vitest';
import { FALLBACK_COLOUR, LAND_TYPE_COLOURS } from '../src/landTypes';
import { legendEntries } from '../src/legend';
import { textsOf } from './support/texts';

const cs = textsOf('cs');
const en = textsOf('en');

describe('legendEntries', () => {
  it('lists the ten land types and the unknown fallback', () => {
    expect(legendEntries(cs)).toHaveLength(11);
    expect(legendEntries(en)).toHaveLength(11);
  });

  it('names the Czech rows in code order, ending with the unknown land type', () => {
    expect(legendEntries(cs).map((entry) => entry.name)).toEqual([
      'Orná půda',
      'Chmelnice',
      'Vinice',
      'Zahrada',
      'Ovocný sad',
      'Trvalý travní porost',
      'Lesní pozemek',
      'Vodní plocha',
      'Zastavěná plocha a nádvoří',
      'Ostatní plocha',
      'Neznámý druh pozemku',
    ]);
  });

  it('names the English rows in code order, ending with the unknown land type', () => {
    expect(legendEntries(en).map((entry) => entry.name)).toEqual([
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
    expect(legendEntries(cs).filter((entry) => entry.name === 'Trvalý travní porost')).toHaveLength(1);
    expect(legendEntries(en).filter((entry) => entry.name === 'Permanent grassland')).toHaveLength(1);
  });

  it('uses the colour of the land type of the same name', () => {
    for (const texts of [cs, en]) {
      const colours = new Map([...LAND_TYPE_COLOURS].map(([code, colour]) => [texts.landTypeName(code), colour]));
      for (const entry of legendEntries(texts).slice(0, -1)) {
        expect(entry.colour).toBe(colours.get(entry.name));
      }
    }
  });

  it('ends with the fallback grey of unknown land types', () => {
    expect(legendEntries(en).at(-1)).toEqual({ name: 'Unknown land type', colour: '#9e9e9e' });
    expect(legendEntries(cs).at(-1)).toEqual({ name: 'Neznámý druh pozemku', colour: '#9e9e9e' });
    expect(FALLBACK_COLOUR).toBe('#9e9e9e');
  });
});
