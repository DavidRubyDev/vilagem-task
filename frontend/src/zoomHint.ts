import type { Map as MapLibreMap } from 'maplibre-gl';
import { COMPLETE_PARCELS_MIN_ZOOM } from './mapOptions';
import { TEXT } from './text';

/**
 * Tells the user to zoom in while parcels cannot be selected yet. It is updated on `load` and `zoomend` only, never
 * on `move` or `render`, which fire continuously while the map is panned.
 */
export function showZoomHint(map: MapLibreMap): void {
  const hint = document.createElement('p');
  hint.className = 'map-hint';
  hint.textContent = TEXT.zoomHint;
  document.body.append(hint);

  const update = (): void => {
    hint.hidden = map.getZoom() >= COMPLETE_PARCELS_MIN_ZOOM;
  };
  update();
  map.on('load', update);
  map.on('zoomend', update);
}
