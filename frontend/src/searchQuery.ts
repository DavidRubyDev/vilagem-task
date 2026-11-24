export const SEARCH_MAX_LENGTH = 100;

const MIN_LENGTH = 2;
const NEAR_DECIMALS = 3;

/** Whether a text is worth a request: two characters, or one digit (a parcel number such as 7). */
export function shouldSearch(text: string): boolean {
  const trimmed = text.trim();
  // Code points, as the server counts characters.
  const length = Array.from(trimmed).length;
  if (length > SEARCH_MAX_LENGTH) return false;
  return length >= MIN_LENGTH || /^\d$/.test(trimmed);
}

/** The URL of one search; the map centre ranks equal matches by distance and is rounded to about 100 m. */
export function searchRequestUrl(searchUrl: string, text: string, center: { lng: number; lat: number }): string {
  const near = `${center.lng.toFixed(NEAR_DECIMALS)},${center.lat.toFixed(NEAR_DECIMALS)}`;
  return `${searchUrl}?q=${encodeURIComponent(text.trim())}&near=${near}`;
}
