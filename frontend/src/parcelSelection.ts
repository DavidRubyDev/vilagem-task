import type { Map as MapLibreMap, MapMouseEvent } from 'maplibre-gl';
import { COMPLETE_PARCELS_MIN_ZOOM } from './mapOptions';
import { loadParcelDetail, parcelDetailUrl } from './parcelDetail';
import type { ParcelPanel } from './parcelPanel';

const PARCEL_FILL_LAYER = 'parcels-fill';

function isParcelId(value: unknown): value is number {
  return typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
}

/** Selecting a parcel by click: one small request per click, and only the panel changes. */
export class ParcelSelection {
  private selectedId: number | null = null;
  private request: AbortController | null = null;

  constructor(
    private readonly map: MapLibreMap,
    private readonly panel: ParcelPanel,
    private readonly fetchFn: typeof fetch,
    private readonly parcelUrlTemplate: string,
  ) {
    map.on('click', (event) => {
      this.onMapClick(event);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && this.panel.isOpen()) {
        this.clear();
      }
    });
  }

  select(id: number): void {
    this.abort();
    this.selectedId = id;
    this.panel.showLoading();
    const request = new AbortController();
    this.request = request;
    void this.load(id, request);
  }

  clear(): void {
    this.abort();
    this.selectedId = null;
    this.panel.hide();
  }

  private async load(id: number, request: AbortController): Promise<void> {
    try {
      const result = await loadParcelDetail(this.fetchFn, parcelDetailUrl(this.parcelUrlTemplate, id), request.signal);
      if (result.found) {
        this.panel.showDetail(result.detail);
      } else {
        this.panel.showNotFound();
      }
    } catch (error) {
      if (request.signal.aborted) return;
      console.warn('The parcel details could not be loaded.', error);
      this.panel.showError();
    }
  }

  private abort(): void {
    this.request?.abort();
    this.request = null;
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
      this.select(id);
    }
  }
}
