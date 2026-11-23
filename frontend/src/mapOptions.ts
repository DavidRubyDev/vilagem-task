import type { LayerSpecification, MapOptions, SourceSpecification } from 'maplibre-gl';
import type { Padding } from './fitPadding';
import { landTypeFillColour } from './landTypes';
import type { MapConfiguration, ParcelLayerConfiguration } from './mapConfiguration';
import { SELECTED_PARCEL_SOURCE, selectedParcelData } from './selectedParcel';

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

// From this zoom on the tiles are complete: below it they leave out the smallest parcels to stay within the tile
// budget. Outlines start here, and so does selecting a parcel (smaller parcels are a pixel or two wide below it).
export const COMPLETE_PARCELS_MIN_ZOOM = 14;

// Shared with the legend swatches, so they look like the map.
export const PARCEL_FILL_OPACITY = 0.45;

const parcelLayers: LayerSpecification[] = [
  {
    id: 'parcels-fill',
    type: 'fill',
    source: 'parcels',
    'source-layer': 'parcels',
    paint: { 'fill-color': landTypeFillColour(), 'fill-opacity': PARCEL_FILL_OPACITY },
  },
  {
    id: 'parcels-outline',
    type: 'line',
    source: 'parcels',
    'source-layer': 'parcels',
    minzoom: COMPLETE_PARCELS_MIN_ZOOM,
    paint: {
      'line-color': '#3d3d3d',
      'line-opacity': 0.7,
      'line-width': ['interpolate', ['linear'], ['zoom'], 14, 0.5, 18, 1.5],
    },
  },
];

// Above the parcel layers. The boundary comes from the parcel details, so this works at every zoom and for a parcel
// that is not in view yet; feature-state or a filter on the tile layers would make every tile's paint data depend on
// the selection.
const selectedParcelLayers: LayerSpecification[] = [
  {
    id: 'selected-parcel-casing',
    type: 'line',
    source: SELECTED_PARCEL_SOURCE,
    paint: { 'line-color': '#ffffff', 'line-width': 5, 'line-opacity': 0.9 },
  },
  {
    id: 'selected-parcel-outline',
    type: 'line',
    source: SELECTED_PARCEL_SOURCE,
    paint: { 'line-color': '#e6007e', 'line-width': 2.5 },
  },
];

/** The map opens fitted to these bounds (west, south, east, north) instead of the initial view. */
export interface InitialBounds {
  readonly bounds: [number, number, number, number];
  readonly padding: Padding;
}

// A single parcel must not be shown closer than this when the map is fitted to it.
const FIT_MAX_ZOOM = 18;

/**
 * `origin` makes the vector tile URL absolute: MapLibre loads tiles in a worker that cannot resolve relative URLs.
 * `initialBounds` overrides the initial view, so no tiles of the initial view are loaded first.
 */
export function createMapOptions(
  configuration: MapConfiguration,
  container: HTMLElement,
  origin: string,
  initialBounds?: InitialBounds,
): MapOptions {
  const { initialView, baseMap, parcels } = configuration;
  return {
    container,
    ...(initialBounds === undefined
      ? { center: [initialView.center.longitude, initialView.center.latitude], zoom: initialView.zoom }
      : {
          bounds: initialBounds.bounds,
          fitBoundsOptions: { maxZoom: FIT_MAX_ZOOM, padding: initialBounds.padding },
        }),
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
        ...(parcels === null
          ? {}
          : {
              parcels: parcelSource(parcels, origin),
              [SELECTED_PARCEL_SOURCE]: { type: 'geojson', data: selectedParcelData(null) },
            }),
      },
      layers: [
        { id: 'base-map', type: 'raster', source: 'base-map' },
        ...(parcels === null ? [] : [...parcelLayers, ...selectedParcelLayers]),
      ],
    },
  };
}
