import type { MapOptions } from 'maplibre-gl';
import type { MapConfiguration } from './mapConfiguration';

const HTML_ESCAPES: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
};

function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (character) => HTML_ESCAPES[character] ?? character);
}

export function createMapOptions(configuration: MapConfiguration, container: HTMLElement): MapOptions {
  const { initialView, baseMap } = configuration;
  return {
    container,
    center: [initialView.center.longitude, initialView.center.latitude],
    zoom: initialView.zoom,
    attributionControl: { compact: false },
    style: {
      version: 8,
      sources: {
        'base-map': {
          type: 'raster',
          tiles: [baseMap.tileUrlTemplate],
          tileSize: baseMap.tileSize,
          maxzoom: baseMap.maxZoom,
          attribution: `<a href="${escapeHtml(baseMap.attribution.url)}">${escapeHtml(baseMap.attribution.text)}</a>`,
        },
      },
      layers: [{ id: 'base-map', type: 'raster', source: 'base-map' }],
    },
  };
}
