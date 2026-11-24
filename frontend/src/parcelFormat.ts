import type { Texts } from './texts';

export function formatArea(squareMetres: number, texts: Texts): string {
  return `${texts.formatNumber(squareMetres)} m²`;
}

export function formatCadastralArea(name: string, code: number): string {
  return `${name} (${String(code)})`;
}

export function plotTypeName(kind: 'building' | 'land', texts: Texts): string {
  return texts.text(kind === 'building' ? 'plotType.building' : 'plotType.land');
}
