import type { Map as MapLibreMap } from 'maplibre-gl';
import type { Texts } from './texts';

function label(element: Element | null, text: string): void {
  if (element === null) return;
  element.setAttribute('title', text);
  element.setAttribute('aria-label', text);
}

/**
 * Sets the labels MapLibre writes itself. MapLibre has no runtime locale setter (only a constructor option), so
 * they are set through the DOM, which also lets a language switch relabel them without recreating the map.
 */
export function labelMapControls(map: MapLibreMap, texts: Texts): void {
  map.getCanvas().setAttribute('aria-label', texts.text('map.label'));
  const container = map.getContainer();
  label(container.querySelector('.maplibregl-ctrl-zoom-in'), texts.text('map.zoomIn'));
  label(container.querySelector('.maplibregl-ctrl-zoom-out'), texts.text('map.zoomOut'));
}
