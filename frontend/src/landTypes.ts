import type { ExpressionSpecification } from 'maplibre-gl';

// Codes are DruhPozemkuKod of the RÚIAN export. Each is pinned by a test to the published code list of
// 2026-11-03; nothing cross-checks them with the PHP list in LandType, so a code-list change updates both by hand.
export const LAND_TYPE_COLOURS: ReadonlyMap<number, string> = new Map([
  [2, '#f2d16b'], // orná půda
  [3, '#b5cf6b'], // chmelnice
  [4, '#a96ab2'], // vinice
  [5, '#9fd27f'], // zahrada
  [6, '#5fb05f'], // ovocný sad
  [7, '#c7e59a'], // trvalý travní porost
  [8, '#c7e59a'], // trvalý travní porost
  [10, '#2e8b57'], // lesní pozemek
  [11, '#5aa9e6'], // vodní plocha
  [13, '#d9735b'], // zastavěná plocha a nádvoří
  [14, '#bdbdbd'], // ostatní plocha
]);

export const FALLBACK_COLOUR = '#9e9e9e';

export function landTypeFillColour(): ExpressionSpecification {
  const cases: (number | string)[] = [];
  for (const [code, colour] of LAND_TYPE_COLOURS) {
    cases.push(code, colour);
  }
  // The spread of a runtime-built list cannot satisfy the tuple type of a match expression.
  return ['match', ['get', 'landType'], ...cases, FALLBACK_COLOUR] as unknown as ExpressionSpecification;
}
