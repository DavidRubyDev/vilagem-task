import { describe, expect, it } from 'vitest';
import type { MapConfiguration } from '../src/mapConfiguration';
import { createMapOptions } from '../src/mapOptions';

function configuration(overrides: { attributionText?: string; attributionUrl?: string } = {}): MapConfiguration {
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

describe('createMapOptions', () => {
  it('passes the container through', () => {
    expect(createMapOptions(configuration(), container).container).toBe(container);
  });

  it('puts longitude before latitude in the center', () => {
    expect(createMapOptions(configuration(), container).center).toEqual([15.5, 50.25]);
  });

  it('passes the zoom through', () => {
    expect(createMapOptions(configuration(), container).zoom).toBe(11.5);
  });

  it('keeps the attribution expanded', () => {
    expect(createMapOptions(configuration(), container).attributionControl).toEqual({ compact: false });
  });

  it('builds a version 8 style with one raster source from the base map', () => {
    const style = styleOf(createMapOptions(configuration(), container));

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
    const style = styleOf(createMapOptions(configuration(), container));

    expect(style.layers).toEqual([{ id: 'base-map', type: 'raster', source: 'base-map' }]);
  });

  it('renders the attribution as a link', () => {
    const style = styleOf(createMapOptions(configuration(), container));

    expect(style.sources['base-map']).toMatchObject({
      attribution: '<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>',
    });
  });

  it('escapes HTML characters in the attribution text and URL', () => {
    const style = styleOf(
      createMapOptions(
        configuration({ attributionText: `<b>"A" & 'B'</b>`, attributionUrl: 'https://x.org/?a=1&b="2"' }),
        container,
      ),
    );

    expect(style.sources['base-map']).toMatchObject({
      attribution:
        '<a href="https://x.org/?a=1&amp;b=&quot;2&quot;">&lt;b&gt;&quot;A&quot; &amp; &#39;B&#39;&lt;/b&gt;</a>',
    });
  });
});
