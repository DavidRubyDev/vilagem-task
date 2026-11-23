export interface ParcelDetail {
  readonly id: number;
  readonly parcelNumber: string;
  readonly kind: 'building' | 'land';
  readonly cadastralArea: { readonly code: number; readonly name: string };
  readonly municipality: { readonly code: number; readonly name: string };
  readonly landTypeCode: number;
  readonly landUseCode: number | null;
  readonly areaSquareMetres: number;
}

export type ParcelDetailResult = { readonly found: true; readonly detail: ParcelDetail } | { readonly found: false };

export class ParcelDetailError extends Error {}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}

function isFiniteNumber(value: unknown): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

function isNamedCode(value: unknown): value is { readonly code: number; readonly name: string } {
  return isRecord(value) && isFiniteNumber(value['code']) && typeof value['name'] === 'string';
}

function isParcelDetail(value: unknown): value is ParcelDetail {
  if (!isRecord(value)) return false;
  const { id, parcelNumber, kind, cadastralArea, municipality, landTypeCode, landUseCode, areaSquareMetres } = value;
  return (
    isFiniteNumber(id) &&
    typeof parcelNumber === 'string' &&
    (kind === 'building' || kind === 'land') &&
    isNamedCode(cadastralArea) &&
    isNamedCode(municipality) &&
    isFiniteNumber(landTypeCode) &&
    (landUseCode === null || isFiniteNumber(landUseCode)) &&
    isFiniteNumber(areaSquareMetres)
  );
}

export function parcelDetailUrl(template: string, id: number): string {
  return template.replace('{id}', String(id));
}

function isAbort(error: unknown): boolean {
  return error instanceof DOMException && error.name === 'AbortError';
}

export async function loadParcelDetail(
  fetchFn: typeof fetch,
  url: string,
  signal?: AbortSignal,
): Promise<ParcelDetailResult> {
  let response: Response;
  try {
    response = await fetchFn(url, {
      headers: { Accept: 'application/json' },
      ...(signal === undefined ? {} : { signal }),
    });
  } catch (error) {
    if (isAbort(error)) throw error;
    throw new ParcelDetailError(`The parcel details could not be requested: ${String(error)}`, { cause: error });
  }
  if (response.status === 404) {
    return { found: false };
  }
  if (!response.ok) {
    throw new ParcelDetailError(`The parcel details request failed with status ${String(response.status)}.`);
  }

  let body: unknown;
  try {
    body = await response.json();
  } catch (error) {
    if (isAbort(error)) throw error;
    throw new ParcelDetailError('The parcel details are not valid JSON.', { cause: error });
  }
  if (!isParcelDetail(body)) {
    throw new ParcelDetailError('The parcel details are missing a field or have a field of the wrong type.');
  }

  return { found: true, detail: body };
}
