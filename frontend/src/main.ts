import { Map, NavigationControl, setWorkerUrl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import './app.css';
import { fitPadding } from './fitPadding';
import { LegendControl } from './legend';
import { loadMapConfiguration } from './mapConfiguration';
import { createMapOptions, type InitialBounds } from './mapOptions';
import { loadParcelDetail, parcelDetailUrl, type ParcelDetail } from './parcelDetail';
import { parcelIdFromHash } from './parcelHash';
import { ParcelPanel } from './parcelPanel';
import { ParcelSelection } from './parcelSelection';
import { TEXT } from './text';
import { showZoomHint } from './zoomHint';

function showError(container: HTMLElement): void {
  container.remove();
  const message = document.createElement('p');
  message.className = 'map-error';
  message.setAttribute('role', 'alert');
  message.textContent = 'The map could not be loaded. Please try again later.';
  document.body.append(message);
}

function showNotice(text: string): void {
  const notice = document.createElement('p');
  notice.className = 'map-notice';
  notice.setAttribute('role', 'status');
  notice.textContent = text;
  document.body.append(notice);
}

/** Collapsed on phones, where the open legend would cover a third of the screen. Read once, so no listener. */
function newLegend(): LegendControl {
  return new LegendControl(!window.matchMedia('(max-width: 640px)').matches);
}

interface LinkedParcel {
  readonly detail: ParcelDetail;
  readonly initialBounds: InitialBounds;
}

/**
 * Loads the parcel of a link before the map exists, so the map can open fitted to it instead of loading the tiles
 * of the default view first. The panel shows the outcome; null means the map opens on the default view.
 */
async function openLinkedParcel(
  id: number,
  parcelUrlTemplate: string,
  panel: ParcelPanel,
  fetchFn: typeof fetch,
): Promise<LinkedParcel | null> {
  panel.showLoading();
  try {
    const result = await loadParcelDetail(fetchFn, parcelDetailUrl(parcelUrlTemplate, id));
    if (!result.found) {
      panel.showNotFound();
      return null;
    }
    // The panel is shown first, because its rectangle decides how much of the map the parcel may use.
    panel.showDetail(result.detail);
    const { west, south, east, north } = result.detail.bounds;
    return {
      detail: result.detail,
      initialBounds: {
        bounds: [west, south, east, north],
        padding: fitPadding(panel.element().getBoundingClientRect(), window.innerWidth),
      },
    };
  } catch (error) {
    console.warn('The parcel details could not be loaded.', error);
    panel.showError();
    return null;
  }
}

async function start(): Promise<void> {
  const container = document.getElementById('map');
  if (container === null) {
    throw new Error('The page has no #map element.');
  }
  try {
    setWorkerUrl(workerUrl);
    const fetchFn = window.fetch.bind(window);
    const configuration = await loadMapConfiguration(fetchFn, '/api/map-config');
    const { parcels } = configuration;
    // Neither parcel data nor parcel details: the map has nothing to select, and a parcel in the URL is ignored.
    if (parcels === null || parcels.parcelUrlTemplate === null) {
      showNotice(
        parcels === null
          ? 'Parcel data has not been imported yet. Run make data, then reload this page.'
          : TEXT.detailsNotice,
      );
      const bareMap = new Map(createMapOptions(configuration, container, window.location.origin));
      bareMap.addControl(new NavigationControl({ showCompass: false }), 'top-right');
      if (parcels !== null) {
        bareMap.addControl(newLegend(), 'bottom-left');
      }
      return;
    }
    const { parcelUrlTemplate } = parcels;
    // The panel is created, and may already open for a link, before the map and its hint exist.
    const hintRef: { current: { update(): void } | null } = { current: null };

    const panel = new ParcelPanel(
      () => {
        selection.clear();
      },
      () => {
        hintRef.current?.update();
      },
    );
    const linkedId = parcelIdFromHash(window.location.hash);
    const linked = linkedId === null ? null : await openLinkedParcel(linkedId, parcelUrlTemplate, panel, fetchFn);

    const map = new Map(createMapOptions(configuration, container, window.location.origin, linked?.initialBounds));
    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    map.addControl(newLegend(), 'bottom-left');
    const selection = new ParcelSelection(map, panel, fetchFn, parcelUrlTemplate, window);
    hintRef.current = showZoomHint(map, () => panel.isOpen());
    if (linked !== null) {
      map.once('load', () => {
        selection.adopt(linked.detail);
      });
    }
  } catch (error) {
    console.warn('The map could not be loaded.', error);
    showError(container);
  }
}

await start();
