import type { Map as MapLibreMap } from 'maplibre-gl';
import { COMPLETE_PARCELS_MIN_ZOOM } from './mapOptions';
import { TEXT } from './text';

/** The hint is only for a map that cannot select yet, and never shares the screen with the parcel panel. */
export function zoomHintVisible(zoom: number, panelOpen: boolean): boolean {
  return zoom < COMPLETE_PARCELS_MIN_ZOOM && !panelOpen;
}

/**
 * Tells the user to zoom in while parcels cannot be selected yet. It is updated on `load`, `zoomend` and when the
 * caller reports that the panel opened or closed, never on `move` or `render`, which fire continuously while the map
 * is panned.
 */
export function showZoomHint(map: MapLibreMap, isPanelOpen: () => boolean): { update(): void } {
  const hint = document.createElement('p');
  hint.className = 'map-hint';
  hint.textContent = TEXT.zoomHint;
  document.body.append(hint);

  const update = (): void => {
    hint.hidden = !zoomHintVisible(map.getZoom(), isPanelOpen());
  };
  update();
  map.on('load', update);
  map.on('zoomend', update);
  return { update };
}
