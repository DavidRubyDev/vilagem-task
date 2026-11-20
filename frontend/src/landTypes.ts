import type { ExpressionSpecification } from 'maplibre-gl';

// Codes are DruhPozemkuKod of the RÚIAN export. The colours live here until task 004 adds a legend and attribute store.
export const LAND_TYPE_COLOURS: ReadonlyMap<number, string> = new Map([
  [2, '#f2d16b'], // arable land
  [3, '#b5cf6b'], // hop field
  [4, '#a96ab2'], // vineyard
  [5, '#9fd27f'], // garden
  [6, '#5fb05f'], // orchard
  [7, '#c7e59a'], // permanent grassland
  [8, '#c7e59a'], // permanent grassland
  [10, '#2e8b57'], // forest
  [11, '#5aa9e6'], // water area
  [13, '#d9735b'], // built-up area and courtyard
  [14, '#bdbdbd'], // other area
]);

const FALLBACK_COLOUR = '#9e9e9e';

export function landTypeFillColour(): ExpressionSpecification {
  const cases: (number | string)[] = [];
  for (const [code, colour] of LAND_TYPE_COLOURS) {
    cases.push(code, colour);
  }
  // The spread of a runtime-built list cannot satisfy the tuple type of a match expression.
  return ['match', ['get', 'landType'], ...cases, FALLBACK_COLOUR] as unknown as ExpressionSpecification;
}
