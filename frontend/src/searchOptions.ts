import { formatCadastralArea } from './parcelFormat';
import type { ParcelResult, SearchResults } from './searchResults';
import type { Texts } from './texts';

export type PickTarget = { readonly kind: 'parcel'; readonly id: number };

export interface SearchOption {
  readonly id: string;
  readonly name: string;
  readonly context: string;
  readonly target: PickTarget;
}

export interface SearchGroup {
  readonly key: 'parcels';
  readonly label: string;
  readonly options: SearchOption[];
}

function parcelContext(parcel: ParcelResult): string {
  const area = formatCadastralArea(parcel.cadastralArea.name, parcel.cadastralArea.code);
  return parcel.municipality.name === parcel.cadastralArea.name ? area : `${area}, ${parcel.municipality.name}`;
}

/** The groups of the list in display order; empty groups are left out and option ids are numbered across groups. */
export function searchGroups(results: SearchResults, texts: Texts): SearchGroup[] {
  const groups: SearchGroup[] = [];
  let count = 0;
  const optionId = (): string => `map-search-option-${String(count++)}`;

  if (results.parcels.length > 0) {
    groups.push({
      key: 'parcels',
      label: texts.text('search.parcels'),
      options: results.parcels.map((parcel) => ({
        id: optionId(),
        name: texts.text('search.parcel', { parcelNumber: parcel.parcelNumber }),
        context: parcelContext(parcel),
        target: { kind: 'parcel', id: parcel.id },
      })),
    });
  }

  return groups;
}

/** The option that Arrow Down (1) or Arrow Up (-1) makes active; it wraps, and starts at an end from no option. */
export function nextActive(current: number | null, count: number, direction: 1 | -1): number | null {
  if (count === 0) return null;
  if (current === null || current >= count) return direction === 1 ? 0 : count - 1;
  return (current + direction + count) % count;
}
