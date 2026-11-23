import { Map, NavigationControl, setWorkerUrl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import './app.css';
import { loadMapConfiguration } from './mapConfiguration';
import { createMapOptions } from './mapOptions';
import { ParcelPanel } from './parcelPanel';
import { ParcelSelection } from './parcelSelection';

function showError(container: HTMLElement): void {
  container.remove();
  const message = document.createElement('p');
  message.className = 'map-error';
  message.setAttribute('role', 'alert');
  message.textContent = 'The map could not be loaded. Please try again later.';
  document.body.append(message);
}

function showNoParcelDataNotice(): void {
  const notice = document.createElement('p');
  notice.className = 'map-notice';
  notice.setAttribute('role', 'status');
  notice.textContent = 'Parcel data has not been imported yet. Run make data, then reload this page.';
  document.body.append(notice);
}

async function start(): Promise<void> {
  const container = document.getElementById('map');
  if (container === null) {
    throw new Error('The page has no #map element.');
  }
  try {
    setWorkerUrl(workerUrl);
    const configuration = await loadMapConfiguration(window.fetch.bind(window), '/api/map-config');
    const map = new Map(createMapOptions(configuration, container, window.location.origin));
    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    if (configuration.parcels === null) {
      showNoParcelDataNotice();
    } else {
      const fetchFn = window.fetch.bind(window);
      const selection = new ParcelSelection(
        map,
        new ParcelPanel(() => {
          selection.clear();
        }),
        fetchFn,
        configuration.parcels.parcelUrlTemplate,
      );
    }
  } catch (error) {
    console.warn('The map could not be loaded.', error);
    showError(container);
  }
}

await start();
