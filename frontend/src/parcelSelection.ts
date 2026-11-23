import { GeoJSONSource, type Map as MapLibreMap, type MapMouseEvent } from 'maplibre-gl';
import { fitPadding } from './fitPadding';
import { COMPLETE_PARCELS_MIN_ZOOM } from './mapOptions';
import { loadParcelDetail, parcelDetailUrl, type ParcelDetail } from './parcelDetail';
import { hashWithParcel, parcelIdFromHash } from './parcelHash';
import type { ParcelPanel } from './parcelPanel';
import { SELECTED_PARCEL_SOURCE, selectedParcelData } from './selectedParcel';

const PARCEL_FILL_LAYER = 'parcels-fill';
const FIT_MAX_ZOOM = 18;

function isParcelId(value: unknown): value is number {
  return typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
}

/** Selecting a parcel by click or link: one small request per selection, and only the panel and one source change. */
export class ParcelSelection {
  private selectedId: number | null = null;
  private request: AbortController | null = null;

  constructor(
    private readonly map: MapLibreMap,
    private readonly panel: ParcelPanel,
    private readonly fetchFn: typeof fetch,
    private readonly parcelUrlTemplate: string,
    private readonly win: Window,
  ) {
    map.on('click', (event) => {
      this.onMapClick(event);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && this.panel.isOpen()) {
        this.clear();
      }
    });
    win.addEventListener('hashchange', () => {
      this.onHashChange();
    });
  }

  select(id: number, options: { fit: boolean }): void {
    this.abort();
    this.selectedId = id;
    this.showHighlight(null);
    this.writeHash(id);
    this.panel.showLoading();
    const request = new AbortController();
    this.request = request;
    void this.load(id, options.fit, request);
  }

  /** Takes over a parcel whose details are already shown, as when the page was opened by a link. */
  adopt(detail: ParcelDetail): void {
    this.selectedId = detail.id;
    this.showHighlight(detail);
  }

  clear(): void {
    this.abort();
    this.selectedId = null;
    this.showHighlight(null);
    this.writeHash(null);
    this.panel.hide();
  }

  private async load(id: number, fit: boolean, request: AbortController): Promise<void> {
    try {
      const result = await loadParcelDetail(this.fetchFn, parcelDetailUrl(this.parcelUrlTemplate, id), request.signal);
      if (!result.found) {
        this.panel.showNotFound();
        return;
      }
      this.panel.showDetail(result.detail);
      this.showHighlight(result.detail);
      if (fit) {
        this.fitTo(result.detail);
      }
    } catch (error) {
      if (request.signal.aborted) return;
      console.warn('The parcel details could not be loaded.', error);
      this.panel.showError();
    }
  }

  private fitTo(detail: ParcelDetail): void {
    const { west, south, east, north } = detail.bounds;
    const rectangle = this.panel.element().getBoundingClientRect();
    this.map.fitBounds(
      [
        [west, south],
        [east, north],
      ],
      { maxZoom: FIT_MAX_ZOOM, padding: fitPadding(rectangle, this.win.innerWidth) },
    );
  }

  private showHighlight(detail: ParcelDetail | null): void {
    const source = this.map.getSource(SELECTED_PARCEL_SOURCE);
    if (source instanceof GeoJSONSource) {
      source.setData(selectedParcelData(detail)).catch((error: unknown) => {
        console.warn('The selected parcel could not be highlighted.', error);
      });
    }
  }

  private writeHash(id: number | null): void {
    const { history, location } = this.win;
    const hash = hashWithParcel(location.hash, id);
    history.replaceState(history.state, '', location.pathname + location.search + hash);
  }

  private abort(): void {
    this.request?.abort();
    this.request = null;
  }

  private onHashChange(): void {
    const id = parcelIdFromHash(this.win.location.hash);
    if (id !== null) {
      if (id !== this.selectedId) {
        this.select(id, { fit: true });
      }
    } else if (this.selectedId !== null) {
      this.clear();
    }
  }

  private onMapClick(event: MapMouseEvent): void {
    if (this.map.getZoom() < COMPLETE_PARCELS_MIN_ZOOM) return;

    const id = this.map
      .queryRenderedFeatures(event.point, { layers: [PARCEL_FILL_LAYER] })
      .map((feature) => feature.id)
      .find(isParcelId);
    if (id === undefined) {
      this.clear();
    } else if (id !== this.selectedId) {
      this.select(id, { fit: false });
    }
  }
}
