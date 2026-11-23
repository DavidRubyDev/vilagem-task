const PARCEL_KEY = 'parcel';
const PARCEL_ID = /^[1-9][0-9]{0,14}$/;

function paramsOf(hash: string): URLSearchParams {
  return new URLSearchParams(hash.replace(/^#/, ''));
}

/** The selected parcel of a URL hash like `#parcel=1753295604`; null when there is none or it is malformed. */
export function parcelIdFromHash(hash: string): number | null {
  const value = paramsOf(hash).get(PARCEL_KEY);
  return value !== null && PARCEL_ID.test(value) ? Number(value) : null;
}

/** The hash with the parcel set or (for null) removed, keeping other keys; empty when nothing is left. */
export function hashWithParcel(hash: string, id: number | null): string {
  const params = paramsOf(hash);
  if (id === null) {
    params.delete(PARCEL_KEY);
  } else {
    params.set(PARCEL_KEY, String(id));
  }
  const text = params.toString();
  return text === '' ? '' : `#${text}`;
}
