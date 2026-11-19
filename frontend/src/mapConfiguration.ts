export interface MapConfiguration {
  readonly initialView: {
    readonly center: { readonly longitude: number; readonly latitude: number };
    readonly zoom: number;
  };
  readonly baseMap: {
    readonly tileUrlTemplate: string;
    readonly tileSize: number;
    readonly maxZoom: number;
    readonly attribution: { readonly text: string; readonly url: string };
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function isFiniteNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

function isMapConfiguration(value: unknown): value is MapConfiguration {
  if (!isRecord(value)) return false;
  const { initialView, baseMap } = value;
  if (!isRecord(initialView) || !isRecord(baseMap)) return false;
  const { center, zoom } = initialView;
  const { tileUrlTemplate, tileSize, maxZoom, attribution } = baseMap;
  return (
    isRecord(center) &&
    isFiniteNumber(center['longitude']) &&
    isFiniteNumber(center['latitude']) &&
    isFiniteNumber(zoom) &&
    typeof tileUrlTemplate === 'string' &&
    isFiniteNumber(tileSize) &&
    isFiniteNumber(maxZoom) &&
    isRecord(attribution) &&
    typeof attribution['text'] === 'string' &&
    typeof attribution['url'] === 'string'
  );
}

export async function loadMapConfiguration(fetchFn: typeof fetch, url: string): Promise<MapConfiguration> {
  const response = await fetchFn(url, { headers: { Accept: 'application/json' } });
  const body: unknown = await response.json();
  if (!isMapConfiguration(body)) {
    throw new Error('The map configuration has an unexpected shape.');
  }
  return body;
}
