export interface ParcelLayerConfiguration {
  readonly tileUrlTemplate: string;
  /** null when the parcel details have not been imported (a tileset from before the parcel store). */
  readonly parcelUrlTemplate: string | null;
  /** null when there is no search index of this tileset (data imported before the search was added). */
  readonly searchUrl: string | null;
  readonly minZoom: number;
  readonly maxZoom: number;
  readonly bounds: {
    readonly west: number;
    readonly south: number;
    readonly east: number;
    readonly north: number;
  };
  readonly attribution: { readonly text: string; readonly url: string };
}

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
  readonly parcels: ParcelLayerConfiguration | null;
}

export class MapConfigurationError extends Error {}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function isFiniteNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

function isParcelLayerConfiguration(value: unknown): value is ParcelLayerConfiguration {
  if (!isRecord(value)) return false;
  const { tileUrlTemplate, parcelUrlTemplate, searchUrl, minZoom, maxZoom, bounds, attribution } = value;
  return (
    typeof tileUrlTemplate === 'string' &&
    tileUrlTemplate.startsWith('/') &&
    ['{z}', '{x}', '{y}'].every((placeholder) => tileUrlTemplate.includes(placeholder)) &&
    (parcelUrlTemplate === null ||
      (typeof parcelUrlTemplate === 'string' &&
        parcelUrlTemplate.startsWith('/') &&
        parcelUrlTemplate.includes('{id}'))) &&
    (searchUrl === null || (typeof searchUrl === 'string' && searchUrl.startsWith('/'))) &&
    isFiniteNumber(minZoom) &&
    isFiniteNumber(maxZoom) &&
    isRecord(bounds) &&
    isFiniteNumber(bounds['west']) &&
    isFiniteNumber(bounds['south']) &&
    isFiniteNumber(bounds['east']) &&
    isFiniteNumber(bounds['north']) &&
    isRecord(attribution) &&
    typeof attribution['text'] === 'string' &&
    typeof attribution['url'] === 'string'
  );
}

function isMapConfiguration(value: unknown): value is MapConfiguration {
  if (!isRecord(value)) return false;
  const { initialView, baseMap, parcels } = value;
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
    typeof attribution['url'] === 'string' &&
    (parcels === null || isParcelLayerConfiguration(parcels))
  );
}

async function fetchBody(fetchFn: typeof fetch, url: string): Promise<unknown> {
  let response: Response;
  try {
    response = await fetchFn(url, { headers: { Accept: 'application/json' } });
  } catch (error) {
    throw new MapConfigurationError(`The map configuration could not be requested: ${String(error)}`, { cause: error });
  }
  if (!response.ok) {
    throw new MapConfigurationError(`The map configuration request failed with status ${String(response.status)}.`);
  }
  try {
    return await response.json();
  } catch (error) {
    throw new MapConfigurationError('The map configuration is not valid JSON.', { cause: error });
  }
}

export async function loadMapConfiguration(fetchFn: typeof fetch, url: string): Promise<MapConfiguration> {
  const body = await fetchBody(fetchFn, url);
  if (!isMapConfiguration(body)) {
    throw new MapConfigurationError('The map configuration is missing a field or has a field of the wrong type.');
  }
  return body;
}
