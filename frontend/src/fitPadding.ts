const MARGIN = 40;
const PANEL_GAP = 24;
// The panel sits on the left of the map above this width and on top of it up to this width (see app.css).
const NARROW_VIEWPORT_MAX_WIDTH = 640;

export interface Padding {
  readonly top: number;
  readonly right: number;
  readonly bottom: number;
  readonly left: number;
}

const SEARCH_BOX_GAP = 24;

/** Padding that keeps a place fitted into the map clear of the search box above it. */
export function searchFitPadding(searchBox: { bottom: number }): Padding {
  return { top: searchBox.bottom + SEARCH_BOX_GAP, right: MARGIN, bottom: MARGIN, left: MARGIN };
}

/** Padding that keeps a parcel fitted into the map clear of the panel that overlays it. */
export function fitPadding(panel: { right: number; bottom: number }, viewportWidth: number): Padding {
  if (viewportWidth > NARROW_VIEWPORT_MAX_WIDTH) {
    return { top: MARGIN, right: MARGIN, bottom: MARGIN, left: panel.right + PANEL_GAP };
  }
  return { top: panel.bottom + PANEL_GAP, right: MARGIN, bottom: MARGIN, left: MARGIN };
}
