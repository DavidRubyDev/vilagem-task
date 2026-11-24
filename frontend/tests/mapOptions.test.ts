import { describe, expect, it } from 'vitest';
import type { MapConfiguration, ParcelLayerConfiguration } from '../src/mapConfiguration';
import { landTypeFillColour } from '../src/landTypes';
import { COMPLETE_PARCELS_MIN_ZOOM, createMapOptions, PARCEL_FILL_OPACITY } from '../src/mapOptions';

const parcels: ParcelLayerConfiguration = {
  tileUrlTemplate: '/tiles/parcels/v1/{z}/{x}/{y}.pbf',
  parcelUrlTemplate: '/api/parcels/v1/{id}',
  minZoom: 9,
  maxZoom: 16,
  bounds: { west: 15.1, south: 50.2, east: 15.3, north: 50.4 },
  attribution: { text: 'ČÚZK, 2026', url: 'https://cuzk.gov.cz/conditions?a=1&b=2' },
};

function configuration(
  overrides: { attributionText?: string; attributionUrl?: string; parcels?: ParcelLayerConfiguration | null } = {},
): MapConfiguration {
  return {
    initialView: { center: { longitude: 15.5, latitude: 50.25 }, zoom: 11.5 },
    baseMap: {
      tileUrlTemplate: 'https://tiles.example.org/{z}/{x}/{y}.png',
      tileSize: 256,
      maxZoom: 19,
      attribution: {
        text: overrides.attributionText ?? '© OpenStreetMap contributors',
        url: overrides.attributionUrl ?? 'https://www.openstreetmap.org/copyright',
      },
    },
    parcels: overrides.parcels === undefined ? null : overrides.parcels,
  };
}

// MapOptions types style as string | StyleSpecification; the factory always builds a specification.
function styleOf(options: ReturnType<typeof createMapOptions>): Exclude<typeof options.style, string | undefined> {
  const { style } = options;
  if (typeof style !== 'object') {
    throw new Error('style must be a specification object');
  }
  return style;
}

const container = { id: 'map' } as HTMLElement;
const ORIGIN = 'http://example.test';

describe('createMapOptions', () => {
  it('passes the container through', () => {
    expect(createMapOptions(configuration(), container, ORIGIN).container).toBe(container);
  });

  it('puts longitude before latitude in the center', () => {
    expect(createMapOptions(configuration(), container, ORIGIN).center).toEqual([15.5, 50.25]);
  });

  it('passes the zoom through', () => {
    expect(createMapOptions(configuration(), container, ORIGIN).zoom).toBe(11.5);
  });

  it('keeps the attribution expanded', () => {
    expect(createMapOptions(configuration(), container, ORIGIN).attributionControl).toEqual({ compact: false });
  });

  it('builds a version 8 style with one raster source from the base map', () => {
    const style = styleOf(createMapOptions(configuration(), container, ORIGIN));

    expect(style.version).toBe(8);
    expect(Object.keys(style.sources)).toEqual(['base-map']);
    expect(style.sources['base-map']).toMatchObject({
      type: 'raster',
      tiles: ['https://tiles.example.org/{z}/{x}/{y}.png'],
      tileSize: 256,
      maxzoom: 19,
    });
  });

  it('builds exactly one raster layer that uses the base map source', () => {
    const style = styleOf(createMapOptions(configuration(), container, ORIGIN));

    expect(style.layers).toEqual([{ id: 'base-map', type: 'raster', source: 'base-map' }]);
  });

  it('renders the attribution as a link', () => {
    const style = styleOf(createMapOptions(configuration(), container, ORIGIN));

    expect(style.sources['base-map']).toMatchObject({
      attribution: '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>',
    });
  });

  it('escapes HTML characters in the attribution text and URL', () => {
    const style = styleOf(
      createMapOptions(
        configuration({ attributionText: `<b>"A" & 'B'</b>`, attributionUrl: 'https://x.org/?a=1&b="2"' }),
        container,
        ORIGIN,
      ),
    );

    expect(style.sources['base-map']).toMatchObject({
      attribution:
        '<a href="https://x.org/?a=1&amp;b=&quot;2&quot;">&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;</a>',
    });
  });

  describe('with parcel data', () => {
    const options = (): ReturnType<typeof createMapOptions> =>
      createMapOptions(configuration({ parcels }), container, ORIGIN);

    it('adds a vector source with absolute tile URLs, zooms, bounds and the credit', () => {
      const style = styleOf(options());

      expect(style.sources['parcels']).toEqual({
        type: 'vector',
        tiles: ['http://example.test/tiles/parcels/v1/{z}/{x}/{y}.pbf'],
        minzoom: 9,
        maxzoom: 16,
        bounds: [15.1, 50.2, 15.3, 50.4],
        attribution: '<a href="https://cuzk.gov.cz/conditions?a=1&amp;b=2">ČÚZK, 2026</a>',
      });
    });

    it('draws the base map, the parcel fill and outline, then the selected parcel casing and outline', () => {
      const style = styleOf(options());

      expect(style.layers.map((layer) => layer.id)).toEqual([
        'base-map',
        'parcels-fill',
        'parcels-outline',
        'selected-parcel-casing',
        'selected-parcel-outline',
      ]);
    });

    it('adds an empty GeoJSON source for the selected parcel', () => {
      const style = styleOf(options());

      expect(style.sources['selected-parcel']).toEqual({
        type: 'geojson',
        data: { type: 'FeatureCollection', features: [] },
      });
    });

    it('draws the selected parcel as a white casing under a magenta line', () => {
      const [casing, outline] = styleOf(options()).layers.slice(3);

      expect(casing).toMatchObject({
        id: 'selected-parcel-casing',
        type: 'line',
        source: 'selected-parcel',
        paint: { 'line-color': '#ffffff', 'line-width': 5, 'line-opacity': 0.9 },
      });
      expect(outline).toMatchObject({
        id: 'selected-parcel-outline',
        type: 'line',
        source: 'selected-parcel',
        paint: { 'line-color': '#e6007e', 'line-width': 2.5 },
      });
    });

    it('keeps the parcel tile layers free of feature-state and filters', () => {
      const layers = styleOf(options()).layers.filter((layer) => layer.id.startsWith('parcels-'));

      expect(layers).toHaveLength(2);
      for (const layer of layers) {
        expect(JSON.stringify(layer)).not.toContain('feature-state');
        expect(layer).not.toHaveProperty('filter');
      }
    });

    it('shares the fill opacity with the legend swatches', () => {
      const fill = styleOf(options()).layers[1];

      expect(PARCEL_FILL_OPACITY).toBe(0.45);
      expect(fill).toMatchObject({ paint: { 'fill-opacity': PARCEL_FILL_OPACITY } });
    });

    it('fills parcels at 0.45 opacity, coloured by land type', () => {
      const fill = styleOf(options()).layers[1];

      expect(fill).toMatchObject({
        type: 'fill',
        source: 'parcels',
        'source-layer': 'parcels',
        paint: { 'fill-opacity': 0.45, 'fill-color': landTypeFillColour() },
      });
    });

    it('draws the outline from zoom 14, the zoom from which parcels can be selected', () => {
      const outline = styleOf(options()).layers[2];

      expect(COMPLETE_PARCELS_MIN_ZOOM).toBe(14);
      expect(outline).toMatchObject({
        type: 'line',
        source: 'parcels',
        'source-layer': 'parcels',
        minzoom: COMPLETE_PARCELS_MIN_ZOOM,
      });
    });
  });

  describe('with initial bounds', () => {
    const initialBounds = {
      bounds: [15.0, 50.0, 15.1, 50.1] as [number, number, number, number],
      padding: { top: 40, right: 40, bottom: 40, left: 356 },
    };

    it('opens the map fitted to the bounds with the padding and a maximum zoom of 18', () => {
      const options = createMapOptions(configuration({ parcels }), container, ORIGIN, initialBounds);

      expect(options.bounds).toEqual([15.0, 50.0, 15.1, 50.1]);
      expect(options.fitBoundsOptions).toEqual({ maxZoom: 18, padding: initialBounds.padding });
    });

    it('does not set the initial view, which the bounds override', () => {
      const options = createMapOptions(configuration({ parcels }), container, ORIGIN, initialBounds);

      expect(options.center).toBeUndefined();
      expect(options.zoom).toBeUndefined();
    });

    it('keeps center and zoom without initial bounds', () => {
      const options = createMapOptions(configuration({ parcels }), container, ORIGIN);

      expect(options.bounds).toBeUndefined();
      expect(options.center).toEqual([15.5, 50.25]);
      expect(options.zoom).toBe(11.5);
    });
  });

  it('draws only the base map when there is no parcel data', () => {
    const style = styleOf(createMapOptions(configuration({ parcels: null }), container, ORIGIN));

    expect(Object.keys(style.sources)).toEqual(['base-map']);
    expect(style.layers.map((layer) => layer.id)).toEqual(['base-map']);
  });
});
