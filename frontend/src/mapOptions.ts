import type { LayerSpecification, MapOptions, SourceSpecification } from 'maplibre-gl';
import { landTypeFillColour } from './landTypes';
import type { MapConfiguration, ParcelLayerConfiguration } from './mapConfiguration';

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

function attributionLink(attribution: { readonly text: string; readonly url: string }): string {
  return `<a href="${escapeHtml(attribution.url)}">${escapeHtml(attribution.text)}</a>`;
}

function parcelSource(parcels: ParcelLayerConfiguration, origin: string): SourceSpecification {
  const { bounds } = parcels;
  return {
    type: 'vector',
    tiles: [origin + parcels.tileUrlTemplate],
    minzoom: parcels.minZoom,
    maxzoom: parcels.maxZoom,
    bounds: [bounds.west, bounds.south, bounds.east, bounds.north],
    attribution: attributionLink(parcels.attribution),
  };
}

// Outlines start at zoom 14: below it the tiles coalesce parcels, so outlines would trace merged blobs.
const parcelLayers: LayerSpecification[] = [
  {
    id: 'parcels-fill',
    type: 'fill',
    source: 'parcels',
    'source-layer': 'parcels',
    paint: { 'fill-color': landTypeFillColour(), 'fill-opacity': 0.45 },
  },
  {
    id: 'parcels-outline',
    type: 'line',
    source: 'parcels',
    'source-layer': 'parcels',
    minzoom: 14,
    paint: {
      'line-color': '#3d3d3d',
      'line-opacity': 0.7,
      'line-width': ['interpolate', ['linear'], ['zoom'], 14, 0.5, 18, 1.5],
    },
  },
];

/** `origin` makes the vector tile URL absolute: MapLibre loads tiles in a worker that cannot resolve relative URLs. */
export function createMapOptions(configuration: MapConfiguration, container: HTMLElement, origin: string): MapOptions {
  const { initialView, baseMap, parcels } = configuration;
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
          attribution: attributionLink(baseMap.attribution),
        },
        ...(parcels === null ? {} : { parcels: parcelSource(parcels, origin) }),
      },
      layers: [{ id: 'base-map', type: 'raster', source: 'base-map' }, ...(parcels === null ? [] : parcelLayers)],
    },
  };
}
