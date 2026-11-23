import type { IControl } from 'maplibre-gl';
import { FALLBACK_COLOUR, LAND_TYPES } from './landTypes';
import { PARCEL_FILL_OPACITY } from './mapOptions';
import { TEXT } from './text';

export interface LegendEntry {
  readonly name: string;
  readonly colour: string;
}

/** The rows of the legend: LAND_TYPES de-duplicated by name and colour, in code order, then the unknown fallback. */
export function legendEntries(): readonly LegendEntry[] {
  const entries: LegendEntry[] = [];
  for (const { name, colour } of LAND_TYPES.values()) {
    if (!entries.some((entry) => entry.name === name && entry.colour === colour)) {
      entries.push({ name, colour });
    }
  }
  entries.push({ name: TEXT.legendUnknown, colour: FALLBACK_COLOUR });
  return entries;
}

/** The land type key in the bottom-left corner of the map. Static: no map or DOM listeners. */
export class LegendControl implements IControl {
  private root: HTMLElement | null = null;

  constructor(private readonly open: boolean) {}

  onAdd(): HTMLElement {
    const details = document.createElement('details');
    details.className = 'maplibregl-ctrl map-legend';
    details.open = this.open;

    const summary = document.createElement('summary');
    summary.textContent = TEXT.legendTitle;

    const list = document.createElement('ul');
    list.className = 'map-legend-entries';
    for (const entry of legendEntries()) {
      const swatch = document.createElement('span');
      swatch.className = 'map-legend-swatch';
      swatch.setAttribute('aria-hidden', 'true');
      swatch.style.backgroundColor = entry.colour;
      swatch.style.opacity = String(PARCEL_FILL_OPACITY);
      const item = document.createElement('li');
      item.append(swatch, entry.name);
      list.append(item);
    }

    details.append(summary, list);
    this.root = details;
    return details;
  }

  onRemove(): void {
    this.root?.remove();
    this.root = null;
  }
}
