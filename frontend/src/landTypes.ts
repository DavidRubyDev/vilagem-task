import type { ExpressionSpecification } from 'maplibre-gl';
import { TEXT } from './text';

export interface LandTypeStyle {
  readonly name: string;
  readonly colour: string;
}

// Codes are DruhPozemkuKod of the RÚIAN export. Each is pinned by a test to the published code list of
// 2026-11-03; nothing cross-checks them with the PHP list in LandType, so a code-list change updates both by hand.
export const LAND_TYPES: ReadonlyMap<number, LandTypeStyle> = new Map([
  [2, { name: 'Arable land', colour: '#f2d16b' }], // orná půda
  [3, { name: 'Hop field', colour: '#b5cf6b' }], // chmelnice
  [4, { name: 'Vineyard', colour: '#a96ab2' }], // vinice
  [5, { name: 'Garden', colour: '#9fd27f' }], // zahrada
  [6, { name: 'Orchard', colour: '#5fb05f' }], // ovocný sad
  [7, { name: 'Permanent grassland', colour: '#c7e59a' }], // trvalý travní porost
  [8, { name: 'Permanent grassland', colour: '#c7e59a' }], // trvalý travní porost
  [10, { name: 'Forest land', colour: '#2e8b57' }], // lesní pozemek
  [11, { name: 'Water area', colour: '#5aa9e6' }], // vodní plocha
  [13, { name: 'Built-up area and courtyard', colour: '#d9735b' }], // zastavěná plocha a nádvoří
  [14, { name: 'Other area', colour: '#bdbdbd' }], // ostatní plocha
]);

export const FALLBACK_COLOUR = '#9e9e9e';

export function landTypeName(code: number): string {
  return LAND_TYPES.get(code)?.name ?? TEXT.unknownLandType(code);
}

export function landTypeFillColour(): ExpressionSpecification {
  const cases: (number | string)[] = [];
  for (const [code, { colour }] of LAND_TYPES) {
    cases.push(code, colour);
  }
  // The spread of a runtime-built list cannot satisfy the tuple type of a match expression.
  return ['match', ['get', 'landType'], ...cases, FALLBACK_COLOUR] as unknown as ExpressionSpecification;
}
