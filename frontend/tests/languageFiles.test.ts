import { describe, expect, it } from 'vitest';
import { LAND_TYPE_COLOURS } from '../src/landTypes';
import { availableLanguages, flagUrl, textsUrl, UnknownLanguage } from '../src/languageFiles';
import { languageFiles } from './support/texts';

const flags = import.meta.glob<string>('../languages/*.svg', { query: '?raw', import: 'default', eager: true });
const files = languageFiles();
const cs = files['cs'] ?? {};

function placeholders(text: string): string[] {
  return [...text.matchAll(/\{(\w+)\}/g)].map((match) => match[1] ?? '').sort();
}

function codesOf(prefix: string, entries: Record<string, string>): number[] {
  return Object.keys(entries)
    .filter((key) => key.startsWith(prefix) && key !== `${prefix}unknown`)
    .map((key) => Number(key.slice(prefix.length)))
    .sort((a, b) => a - b);
}

const LAND_TYPE_NAMES: readonly (readonly [number, string, string])[] = [
  [2, 'Arable land', 'Orná půda'],
  [3, 'Hop field', 'Chmelnice'],
  [4, 'Vineyard', 'Vinice'],
  [5, 'Garden', 'Zahrada'],
  [6, 'Orchard', 'Ovocný sad'],
  [7, 'Permanent grassland', 'Trvalý travní porost'],
  [8, 'Permanent grassland', 'Trvalý travní porost'],
  [10, 'Forest land', 'Lesní pozemek'],
  [11, 'Water area', 'Vodní plocha'],
  [13, 'Built-up area and courtyard', 'Zastavěná plocha a nádvoří'],
  [14, 'Other area', 'Ostatní plocha'],
];

const LAND_USE_NAMES: readonly (readonly [number, string, string])[] = [
  [1, 'Greenhouse, hotbed', 'Skleník, pařeniště'],
  [2, 'Tree nursery', 'Školka'],
  [3, 'Woody plant plantation', 'Plantáž dřevin'],
  [4, 'Forest other than commercial forest', 'Les jiný než hospodářský'],
  [5, 'Forest land with a building', 'Lesní pozemek, na kterém je budova'],
  [6, 'Fishpond', 'Rybník'],
  [7, 'Natural or regulated watercourse bed', 'Koryto vodního toku přirozené nebo upravené'],
  [8, 'Artificial watercourse bed', 'Koryto vodního toku umělé'],
  [9, 'Natural water reservoir', 'Vodní nádrž přírodní'],
  [10, 'Artificial water reservoir', 'Vodní nádrž umělá'],
  [11, 'Wetland', 'Zamokřená plocha'],
  [12, 'Shared courtyard', 'Společný dvůr'],
  [13, 'Demolition site', 'Zbořeniště'],
  [14, 'Railway', 'Dráha'],
  [15, 'Motorway', 'Dálnice'],
  [16, 'Road', 'Silnice'],
  [17, 'Other road', 'Ostatní komunikace'],
  [18, 'Other transport area', 'Ostatní dopravní plocha'],
  [19, 'Green space', 'Zeleň'],
  [20, 'Sports and recreation area', 'Sportoviště a rekreační plocha'],
  [21, 'Cemetery', 'Pohřebiště'],
  [22, 'Cultural and educational area', 'Kulturní a osvětová plocha'],
  [23, 'Handling area', 'Manipulační plocha'],
  [24, 'Mining area', 'Dobývací prostor'],
  [25, 'Landfill', 'Skládka'],
  [26, 'Miscellaneous area', 'Jiná plocha'],
  [27, 'Unproductive land', 'Neplodná půda'],
  [28, 'Water area with a building', 'Vodní plocha, na které je budova'],
  [29, 'Photovoltaic power plant', 'Fotovoltaická elektrárna'],
  [30, 'Field boundary, slope', 'Mez, stráň'],
];

describe('the language files', () => {
  it('exist for Czech and English', () => {
    expect(Object.keys(files).sort()).toEqual(['cs', 'en']);
    expect(availableLanguages()).toEqual(['cs', 'en']);
  });

  describe.each(Object.entries(files))('%s', (_code, entries) => {
    it('is a non-empty flat object of non-empty strings', () => {
      expect(Object.keys(entries).length).toBeGreaterThan(0);
      for (const [key, text] of Object.entries(entries)) {
        expect(typeof text, key).toBe('string');
        expect(text.length, key).toBeGreaterThan(0);
      }
    });

    it('has exactly the keys of the Czech file', () => {
      expect(Object.keys(entries).sort()).toEqual(Object.keys(cs).sort());
    });

    it('uses the same placeholders as the Czech file for every key', () => {
      for (const [key, text] of Object.entries(entries)) {
        expect(placeholders(text), key).toEqual(placeholders(cs[key] ?? ''));
      }
    });

    it('names exactly the land type codes of the catalogue', () => {
      expect(codesOf('landType.', entries)).toEqual([...LAND_TYPE_COLOURS.keys()]);
    });

    it('names exactly the land uses 1 to 30', () => {
      expect(codesOf('landUse.', entries)).toEqual(Array.from({ length: 30 }, (_, index) => index + 1));
    });
  });

  it.each(LAND_TYPE_NAMES)('names land type %i %s / %s', (code, en, czech) => {
    expect(files['en']?.[`landType.${String(code)}`]).toBe(en);
    expect(files['cs']?.[`landType.${String(code)}`]).toBe(czech);
  });

  it.each(LAND_USE_NAMES)('names land use %i %s / %s', (code, en, czech) => {
    expect(files['en']?.[`landUse.${String(code)}`]).toBe(en);
    expect(files['cs']?.[`landUse.${String(code)}`]).toBe(czech);
  });
});

describe('textsUrl', () => {
  it('points at the language file', () => {
    expect(textsUrl('cs')).toContain('cs.json');
  });

  it('throws for a language without a texts file', () => {
    expect(() => textsUrl('de')).toThrow(UnknownLanguage);
  });
});

describe('the flag files', () => {
  const flagCodes = Object.keys(flags).map((path) => /([^/]+)\.svg$/.exec(path)?.[1]);

  it('exist for exactly the languages with texts', () => {
    expect([...flagCodes].sort()).toEqual(Object.keys(files).sort());
  });

  it.each(Object.entries(flags))('%s is an SVG with a viewBox', (_path, svg) => {
    expect(svg.startsWith('<svg')).toBe(true);
    expect(svg).toContain('viewBox=');
  });
});

describe('flagUrl', () => {
  it('points at the language flag', () => {
    expect(flagUrl('en')).toContain('en.svg');
  });

  it('throws for a language without a flag file', () => {
    expect(() => flagUrl('de')).toThrow(UnknownLanguage);
  });
});
