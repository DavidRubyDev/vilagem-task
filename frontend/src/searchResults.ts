export interface ParcelResult {
  readonly id: number;
  readonly parcelNumber: string;
  readonly kind: 'building' | 'land';
  readonly cadastralArea: { readonly code: number; readonly name: string };
  readonly municipality: { readonly code: number; readonly name: string };
}

export type PlaceKind = 'municipality' | 'cadastralArea' | 'municipalityPart' | 'street' | 'address';

export interface PlaceResult {
  readonly kind: PlaceKind;
  readonly code: number;
  readonly name: string;
  readonly context: string;
  readonly center: { readonly longitude: number; readonly latitude: number };
  readonly bounds: {
    readonly west: number;
    readonly south: number;
    readonly east: number;
    readonly north: number;
  } | null;
  readonly parcelId: number | null;
}

export interface SearchResults {
  readonly places: readonly PlaceResult[];
  readonly parcels: readonly ParcelResult[];
}

export type SearchOutcome = { readonly kind: 'found'; readonly results: SearchResults } | { readonly kind: 'outdated' };

export class SearchError extends Error {}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isFiniteNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

function isNamedCode(value: unknown): value is { readonly code: number; readonly name: string } {
  return isRecord(value) && isFiniteNumber(value['code']) && typeof value['name'] === 'string';
}

function isParcelId(value: unknown): value is number {
  return typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
}

function isParcelResult(value: unknown): value is ParcelResult {
  if (!isRecord(value)) return false;
  const { id, parcelNumber, kind, cadastralArea, municipality } = value;
  return (
    isParcelId(id) &&
    typeof parcelNumber === 'string' &&
    (kind === 'building' || kind === 'land') &&
    isNamedCode(cadastralArea) &&
    isNamedCode(municipality)
  );
}

const PLACE_KINDS: readonly unknown[] = ['municipality', 'cadastralArea', 'municipalityPart', 'street', 'address'];

function isBounds(value: unknown): boolean {
  return (
    isRecord(value) &&
    isFiniteNumber(value['west']) &&
    isFiniteNumber(value['south']) &&
    isFiniteNumber(value['east']) &&
    isFiniteNumber(value['north'])
  );
}

function isPlaceResult(value: unknown): value is PlaceResult {
  if (!isRecord(value)) return false;
  const { kind, code, name, context, center, bounds, parcelId } = value;
  return (
    PLACE_KINDS.includes(kind) &&
    isFiniteNumber(code) &&
    typeof name === 'string' &&
    typeof context === 'string' &&
    isRecord(center) &&
    isFiniteNumber(center['longitude']) &&
    isFiniteNumber(center['latitude']) &&
    (bounds === null || isBounds(bounds)) &&
    (parcelId === null || isParcelId(parcelId))
  );
}

function isSearchResults(value: unknown): value is SearchResults {
  return (
    isRecord(value) &&
    Array.isArray(value['places']) &&
    value['places'].every(isPlaceResult) &&
    Array.isArray(value['parcels']) &&
    value['parcels'].every(isParcelResult)
  );
}

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

/**
 * Throws SearchError when the request fails, answers a status other than 2xx and 404, or the body is not valid
 * results; an aborted request rethrows its AbortError.
 */
export async function loadSearchResults(
  fetchFn: typeof fetch,
  url: string,
  signal: AbortSignal,
): Promise<SearchOutcome> {
  let response: Response;
  try {
    response = await fetchFn(url, { headers: { Accept: 'application/json' }, signal });
  } catch (error) {
    if (isAbort(error)) throw error;
    throw new SearchError(`The search could not be requested: ${String(error)}`, { cause: error });
  }
  // The URL carries the tileset version, so a 404 means the page is older than the data.
  if (response.status === 404) {
    return { kind: 'outdated' };
  }
  if (!response.ok) {
    throw new SearchError(`The search request failed with status ${String(response.status)}.`);
  }

  let body: unknown;
  try {
    body = await response.json();
  } catch (error) {
    if (isAbort(error)) throw error;
    throw new SearchError('The search results are not valid JSON.', { cause: error });
  }
  if (!isSearchResults(body)) {
    throw new SearchError('The search results are missing a field or have a field of the wrong type.');
  }

  return { kind: 'found', results: body };
}
