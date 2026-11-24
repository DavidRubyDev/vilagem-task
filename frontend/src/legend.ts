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
  private summary: HTMLElement | null = null;
  private list: HTMLElement | null = null;

  constructor(
    private readonly open: boolean,
    private texts: Texts,
  ) {}

  onAdd(): HTMLElement {
    const details = document.createElement('details');
    details.className = 'maplibregl-ctrl map-legend';
    details.open = this.open;

    const summary = document.createElement('summary');
    const list = document.createElement('ul');
    list.className = 'map-legend-entries';

    details.append(summary, list);
    this.root = details;
    this.summary = summary;
    this.list = list;
    this.setTexts(this.texts);
    return details;
  }

  onRemove(): void {
    this.root?.remove();
    this.root = null;
    this.summary = null;
    this.list = null;
  }

  /** Rewrites the summary and the rows; the <details> element keeps its open state. */
  setTexts(texts: Texts): void {
    this.texts = texts;
    if (this.summary === null || this.list === null) return;
    this.summary.textContent = texts.text('legend.title');
    this.list.replaceChildren(...legendEntries(texts).map(legendRow));
  }
}

function legendRow(entry: LegendEntry): HTMLElement {
  const swatch = document.createElement('span');
  swatch.className = 'map-legend-swatch';
  swatch.setAttribute('aria-hidden', 'true');
  swatch.style.backgroundColor = entry.colour;
  swatch.style.opacity = String(PARCEL_FILL_OPACITY);
  const item = document.createElement('li');
  item.append(swatch, entry.name);
  return item;
}
