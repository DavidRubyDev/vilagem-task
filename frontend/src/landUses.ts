import { TEXT } from './text';

// Codes are ZpusobyVyuzitiPozemku of the RÚIAN export, pinned by a test to the published code list of 2026-11-03.
export const LAND_USE_NAMES: ReadonlyMap<number, string> = new Map([
  [1, 'Greenhouse, hotbed'], // skleník, pařeniště
  [2, 'Tree nursery'], // školka
  [3, 'Woody plant plantation'], // plantáž dřevin
  [4, 'Forest other than commercial forest'], // les jiný než hospodářský
  [5, 'Forest land with a building'], // lesní pozemek, na kterém je budova
  [6, 'Fishpond'], // rybník
  [7, 'Natural or regulated watercourse bed'], // koryto vodního toku přirozené nebo upravené
  [8, 'Artificial watercourse bed'], // koryto vodního toku umělé
  [9, 'Natural water reservoir'], // vodní nádrž přírodní
  [10, 'Artificial water reservoir'], // vodní nádrž umělá
  [11, 'Wetland'], // zamokřená plocha
  [12, 'Shared courtyard'], // společný dvůr
  [13, 'Demolition site'], // zbořeniště
  [14, 'Railway'], // dráha
  [15, 'Motorway'], // dálnice
  [16, 'Road'], // silnice
  [17, 'Other road'], // ostatní komunikace
  [18, 'Other transport area'], // ostatní dopravní plocha
  [19, 'Green space'], // zeleň
  [20, 'Sports and recreation area'], // sportoviště a rekreační plocha
  [21, 'Cemetery'], // pohřebiště
  [22, 'Cultural and educational area'], // kulturní a osvětová plocha
  [23, 'Handling area'], // manipulační plocha
  [24, 'Mining area'], // dobývací prostor
  [25, 'Landfill'], // skládka
  [26, 'Miscellaneous area'], // jiná plocha
  [27, 'Unproductive land'], // neplodná půda
  [28, 'Water area with a building'], // vodní plocha, na které je budova
  [29, 'Photovoltaic power plant'], // fotovoltaická elektrárna
  [30, 'Field boundary, slope'], // mez, stráň
]);

export function landUseName(code: number): string {
  return LAND_USE_NAMES.get(code) ?? TEXT.unknownLandUse(code);
}
