import type { Map as MapLibreMap } from 'maplibre-gl';
import { debounce } from './debounce';
import type { ParcelSelection } from './parcelSelection';
import { SearchBox } from './searchBox';
import { searchGroups, type PickTarget } from './searchOptions';
import { searchFitPadding } from './fitPadding';
import { searchRequestUrl, shouldSearch } from './searchQuery';
import { loadSearchResults, type SearchResults } from './searchResults';
import type { Texts } from './texts';

export const SEARCH_DEBOUNCE_MS = 200;
// Street level: a fitted place never zooms closer than this, and a place without bounds is shown at it.
const PLACE_ZOOM = 17;

/**
 * Wires the search box to the search API and the map. No map listener is added: the map centre is read with
 * `getCenter()` when a request is sent, typing touches only the search box, and a pick makes one camera move.
 */
export class MapSearch {
  private readonly box: SearchBox;
  private readonly debounced = debounce((text: string) => {
    void this.search(text);
  }, SEARCH_DEBOUNCE_MS);
  private request: AbortController | null = null;
  // Kept so that a language switch can render the same results again.
  private results: SearchResults | null = null;

  constructor(
    private readonly map: MapLibreMap,
    private readonly selection: ParcelSelection,
    private readonly fetchFn: typeof fetch,
    private readonly searchUrl: string,
    private texts: Texts,
  ) {
    this.box = new SearchBox(texts, {
      onInput: (text) => {
        this.onInput(text);
      },
      onPick: (target) => {
        this.onPick(target);
      },
    });
  }

  /** Rewrites the texts of the box and of an open list. */
  setTexts(texts: Texts): void {
    this.texts = texts;
    this.box.setTexts(texts, this.results === null ? null : searchGroups(this.results, texts));
  }

  private onInput(text: string): void {
    if (!shouldSearch(text)) {
      this.debounced.cancel();
      this.abort();
      this.results = null;
      this.box.close();
      return;
    }
    this.debounced.call(text);
  }

  private async search(text: string): Promise<void> {
    this.abort();
    const request = new AbortController();
    this.request = request;
    this.box.showSearching();
    const center = this.map.getCenter();
    try {
      const outcome = await loadSearchResults(
        this.fetchFn,
        searchRequestUrl(this.searchUrl, text, center),
        request.signal,
      );
      if (request.signal.aborted) return;
      if (outcome.kind === 'outdated') {
        this.results = null;
        this.box.showOutdated();
        return;
      }
      this.results = outcome.results;
      this.box.showGroups(searchGroups(outcome.results, this.texts));
    } catch (error) {
      if (request.signal.aborted) return;
      console.warn('The search failed.', error);
      this.results = null;
      this.box.showError();
    }
  }

  private onPick(target: PickTarget): void {
    this.debounced.cancel();
    this.abort();
    if (target.kind === 'parcel') {
      this.selection.select(target.id, { fit: true });
      return;
    }
    // A place is not a parcel: the panel closes, and the map makes one camera move to the place.
    this.selection.clear();
    if (target.bounds === null) {
      this.map.easeTo({ center: target.center, zoom: PLACE_ZOOM });
      return;
    }
    const [west, south, east, north] = target.bounds;
    this.map.fitBounds(
      [
        [west, south],
        [east, north],
      ],
      { maxZoom: PLACE_ZOOM, padding: searchFitPadding(this.box.element().getBoundingClientRect()) },
    );
  }

  private abort(): void {
    this.request?.abort();
    this.request = null;
  }
}
