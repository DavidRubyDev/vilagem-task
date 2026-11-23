import { TEXT, UI_LOCALE } from './text';

const AREA_FORMAT = new Intl.NumberFormat(UI_LOCALE);

export function formatArea(squareMetres: number): string {
  return `${AREA_FORMAT.format(squareMetres)} m²`;
}

export function formatCadastralArea(name: string, code: number): string {
  return `${name} (${String(code)})`;
}

export function plotTypeName(kind: 'building' | 'land'): string {
  return kind === 'building' ? TEXT.buildingPlot : TEXT.landPlot;
}
