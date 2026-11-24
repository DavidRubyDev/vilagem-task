import { Map, NavigationControl, setWorkerUrl } from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import './app.css';
import { applyDocumentTexts } from './documentTexts';
import { fitPadding } from './fitPadding';
import { DEFAULT_LANGUAGE, languageName } from './language';
import { availableLanguages, flagUrl, textsUrl } from './languageFiles';
import { LanguageSwitcherControl } from './languageSwitcher';
import { LegendControl } from './legend';
import { Localization } from './localization';
import { loadMapConfiguration } from './mapConfiguration';
import { labelMapControls } from './mapLabels';
import { MapNotice } from './mapNotice';
import { createMapOptions, type InitialBounds } from './mapOptions';
import { loadParcelDetail, parcelDetailUrl, type ParcelDetail } from './parcelDetail';
import { parcelIdFromHash } from './parcelHash';
import { ParcelPanel } from './parcelPanel';
import { ParcelSelection } from './parcelSelection';
import { loadTexts, type Texts } from './texts';
import { showZoomHint } from './zoomHint';

/** The page ships a hidden Czech error; the loaded texts, when there are any, replace its wording. */
function showError(container: HTMLElement, message: HTMLElement, texts: Texts | null): void {
  container.remove();
  if (texts !== null) {
    message.textContent = texts.text('map.error');
  }
  message.hidden = false;
}

/** Collapsed on phones, where the open legend would cover a third of the screen. Read once, so no listener. */
function newLegend(texts: Texts): LegendControl {
  return new LegendControl(!window.matchMedia('(max-width: 640px)').matches, texts);
}

function newLanguageSwitcher(localization: Localization): LanguageSwitcherControl {
  const options = availableLanguages().map((code) => ({ code, name: languageName(code), flagUrl: flagUrl(code) }));
  return new LanguageSwitcherControl(options, localization.current(), (code) => {
    void localization.switchTo(code);
  });
}

interface LinkedParcel {
  readonly detail: ParcelDetail;
  readonly initialBounds: InitialBounds;
}

/**
 * Loads the parcel of a link before the map exists, so the map can open fitted to it instead of loading the tiles
 * of the default view first. The panel shows the outcome; null means the map opens on the default view.
 */
async function openLinkedParcel(
  id: number,
  parcelUrlTemplate: string,
  panel: ParcelPanel,
  fetchFn: typeof fetch,
): Promise<LinkedParcel | null> {
  panel.showLoading();
  try {
    const result = await loadParcelDetail(fetchFn, parcelDetailUrl(parcelUrlTemplate, id));
    if (!result.found) {
      panel.showNotFound();
      return null;
    }
    // The panel is shown first, because its rectangle decides how much of the map the parcel may use.
    panel.showDetail(result.detail);
    const { west, south, east, north } = result.detail.bounds;
    return {
      detail: result.detail,
      initialBounds: {
        bounds: [west, south, east, north],
        padding: fitPadding(panel.element().getBoundingClientRect(), window.innerWidth),
      },
    };
  } catch (error) {
    console.warn('The parcel details could not be loaded.', error);
    panel.showError();
    return null;
  }
}

async function start(): Promise<void> {
  const container = document.getElementById('map');
  const errorMessage = document.querySelector<HTMLElement>('.map-error');
  if (container === null || errorMessage === null) {
    throw new Error('The page has no #map or .map-error element.');
  }
  let texts: Texts | null = null;
  try {
    setWorkerUrl(workerUrl);
    const fetchFn = window.fetch.bind(window);
    const language = DEFAULT_LANGUAGE;
    // The texts are a static file and the configuration a handler: they are requested together, not one after the other.
    const [configurationResult, textsResult] = await Promise.allSettled([
      loadMapConfiguration(fetchFn, '/api/map-config'),
      loadTexts(fetchFn, textsUrl(language), language),
    ]);
    texts = textsResult.status === 'fulfilled' ? textsResult.value : null;
    const fail = (reason: unknown): void => {
      console.warn('The map could not be loaded.', reason);
      showError(container, errorMessage, texts);
    };
    if (configurationResult.status === 'rejected') {
      fail(configurationResult.reason);
      return;
    }
    if (textsResult.status === 'rejected') {
      fail(textsResult.reason);
      return;
    }
    const configuration = configurationResult.value;
    const loadedTexts = textsResult.value;
    applyDocumentTexts(document, loadedTexts);
    const localization = new Localization(loadedTexts, (code) => loadTexts(fetchFn, textsUrl(code), code));
    const { parcels } = configuration;
    // Neither parcel data nor parcel details: the map has nothing to select, and a parcel in the URL is ignored.
    if (parcels === null || parcels.parcelUrlTemplate === null) {
      const notice = new MapNotice(parcels === null ? 'notice.parcelsMissing' : 'notice.detailsMissing', loadedTexts);
      const bareMap = new Map(createMapOptions(configuration, container, window.location.origin));
      bareMap.addControl(new NavigationControl({ showCompass: false }), 'top-right');
      labelMapControls(bareMap, loadedTexts);
      const legend = parcels === null ? null : newLegend(loadedTexts);
      if (legend !== null) {
        bareMap.addControl(legend, 'bottom-left');
      }
      // Added last: MapLibre puts a later bottom control above the earlier ones, so the switcher sits above the legend.
      const switcher = newLanguageSwitcher(localization);
      bareMap.addControl(switcher, 'bottom-left');
      localization.onChange((next) => {
        applyDocumentTexts(document, next);
        labelMapControls(bareMap, next);
        notice.setTexts(next);
        legend?.setTexts(next);
        switcher.setTexts(next);
      });
      return;
    }
    const { parcelUrlTemplate } = parcels;
    // The panel is created, and may already open for a link, before the map and its hint exist.
    const hintRef: { current: { update(): void } | null } = { current: null };

    const panel = new ParcelPanel(
      loadedTexts,
      () => {
        selection.clear();
      },
      () => {
        hintRef.current?.update();
      },
    );
    const linkedId = parcelIdFromHash(window.location.hash);
    const linked = linkedId === null ? null : await openLinkedParcel(linkedId, parcelUrlTemplate, panel, fetchFn);

    const map = new Map(createMapOptions(configuration, container, window.location.origin, linked?.initialBounds));
    map.addControl(new NavigationControl({ showCompass: false }), 'top-right');
    labelMapControls(map, loadedTexts);
    const legend = newLegend(loadedTexts);
    map.addControl(legend, 'bottom-left');
    const switcher = newLanguageSwitcher(localization);
    map.addControl(switcher, 'bottom-left');
    const selection = new ParcelSelection(map, panel, fetchFn, parcelUrlTemplate, window);
    const hint = showZoomHint(map, () => panel.isOpen(), loadedTexts);
    hintRef.current = hint;
    localization.onChange((next) => {
      applyDocumentTexts(document, next);
      labelMapControls(map, next);
      panel.setTexts(next);
      hint.setTexts(next);
      legend.setTexts(next);
      switcher.setTexts(next);
    });
    if (linked !== null) {
      map.once('load', () => {
        selection.adopt(linked.detail);
      });
    }
  } catch (error) {
    console.warn('The map could not be loaded.', error);
    showError(container, errorMessage, texts);
  }
}

await start();
