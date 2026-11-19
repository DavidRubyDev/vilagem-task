import { Map, NavigationControl, setWorkerUrl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import './app.css';
import { loadMapConfiguration } from './mapConfiguration';
import { createMapOptions } from './mapOptions';

async function start(): Promise<void> {
  const container = document.getElementById('map');
  if (container === null) {
    throw new Error('The page has no #map element.');
  }
  setWorkerUrl(workerUrl);
  const configuration = await loadMapConfiguration(window.fetch.bind(window), '/api/map-config');
  const map = new Map(createMapOptions(configuration, container));
  map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
}

await start();
