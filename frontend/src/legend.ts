import type { IControl } from 'maplibre-gl';
import { FALLBACK_COLOUR, LAND_TYPE_COLOURS } from './landTypes';
import { PARCEL_FILL_OPACITY } from './mapOptions';
import type { Texts } from './texts';

export interface LegendEntry {
  readonly name: string;
  readonly colour: string;
}

/** The rows of the legend: LAND_TYPE_COLOURS de-duplicated by name and colour, in code order, then the unknown fallback. */
export function legendEntries(texts: Texts): readonly LegendEntry[] {
  const entries: LegendEntry[] = [];
  for (const [code, colour] of LAND_TYPE_COLOURS) {
    const name = texts.landTypeName(code);
    if (!entries.some((entry) => entry.name === name && entry.colour === colour)) {
      entries.push({ name, colour });
    }
  }
  entries.push({ name: texts.text('legend.unknown'), colour: FALLBACK_COLOUR });
  return entries;
}

/** The land type key in the bottom-left corner of the map. Static: no map or DOM listeners. */
export class LegendControl implements IControl {
  private root: HTMLElement | null = null;

  constructor(
    private readonly open: boolean,
    private readonly texts: Texts,
  ) {}

  onAdd(): HTMLElement {
    const details = document.createElement('details');
    details.className = 'maplibregl-ctrl map-legend';
    details.open = this.open;

    const summary = document.createElement('summary');
    summary.textContent = this.texts.text('legend.title');

    const list = document.createElement('ul');
    list.className = 'map-legend-entries';
    for (const entry of legendEntries(this.texts)) {
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
