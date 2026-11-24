# 008: Language switcher

- **Status:** in-progress
- **Created:** 2026-11-24
- **Branch:** task/008-language-switcher
- **Request:** "language switcher. Only english and czech for now. The switcher lives above the legend. The texts are not
  loaded with the js. But the page also does not reload on language switch. Figure it out. Czech will be the default"

  Answers to the plan's questions, verbatim: "Q1: recommended; Q2: recommended; Q3: how about English and Czech flags
  with the full name (English | Čeština) on hover; Q4: recommended; Q5: recommended; Q6: good". On the revised
  switcher, verbatim: "Q7: UK flag. 2: Czech first... approved". They mean:
  - Q1: the browser language (`navigator.language`) is ignored. Every first visit is Czech; only an explicit choice
    changes the language.
  - Q2: the choice is remembered in `localStorage` only. It is not in the URL and not in a cookie.
  - Q3: the switcher shows a flag per language. The language's full name in that language ("Čeština", "English")
    appears on hover.
  - Q4: the CLAUDE.md Language rule gets an exception for user-facing texts (Decision 14).
  - Q5: the panel's height caps grow by the switcher's height. Small windows show less of the panel before it
    scrolls (Decision 12).
  - Q6: the Czech wording proposed in the draft is accepted (Decision 13).
  - Q7: English is represented by the flag of the United Kingdom (Union Jack).
  - Order: Czech first. The default language comes first, then the others alphabetically.

## Goal

The interface is Czech by default and can be switched to English with flag buttons above the legend. The texts of
each language are data: one JSON file per language, fetched at run time as a separate, content-hashed file and never
compiled into the JS bundle. Switching re-renders every visible text in place. The page does not reload, the map is
not recreated and no tile is fetched again. Adding a language later means adding two files (texts and flag), with no
code change.

## Scope

**In scope:**

- Language files `frontend/languages/<code>.json` (texts) and `<code>.svg` (flag) for `cs` and `en`, emitted by Vite
  as separate files under `public/build/languages/`.
- Every UI string moves into the language files. This includes the two strings still inline in `main.ts` (the fatal
  error and the no-parcel-data notice), the land type and land use names, the page title and MapLibre's zoom button
  and canvas labels.
- Czech is the default. `index.html` is Czech (`lang`, title, fallback error).
- Number formatting per language (`cs` gives `13 077 m²`, `en` gives `13,077 m²`).
- A language switcher (flag buttons) above the legend, shown whenever the map is shown.
- Switching in place: panel (whatever it shows), legend, zoom hint, notice, switcher, `<html lang>`, document title,
  aria labels and MapLibre's zoom buttons and canvas label.
- Remembering the choice in `localStorage`.
- Consistency tests across the language files, the panel caps for the switcher and the no-overlap E2E.
- README, the CLAUDE.md Language rule and the Glossary.

**Out of scope:**

- Detecting the browser language (Q1).
- The language in the URL or in a cookie (Q2), and server-side rendering of the language.
- Translating the map attribution ("© OpenStreetMap contributors", "ČÚZK, 2026"). It is a credit that comes from
  `/api/map-config`, and changing it would need a style or source change, which refetches tiles.
- Translating CLI output, API error bodies and the README. The backend stays language-neutral.
- Languages other than Czech and English.
- Any backend (PHP) change.
- The phone-landscape layout (for example 844×390). It is not a tested viewport. Its panel shows 74 px before
  scrolling (Decision 12).
- The 006 follow-ups (test hardening, final review), still deferred.
- Editing the `done` task files 001 to 007.

## Decisions

1. **The frontend owns the texts, as static files.**
   - Each language has one flat JSON object of text key to text, with `{name}` placeholders:
     `frontend/languages/<code>.json`.
   - Vite emits each file unchanged as `public/build/languages/<code>-<hash>.json`. The bundle holds only the URL.
   - No PHP is involved: no handler, no use case, nothing for deptrac. The backend stays language-neutral, as in
     task 006 Decision 7, and serving static data through a handler and a use case would add layers for nothing.
2. **Caching.**
   - The file names are content-hashed, so the URLs are safe for any server that caches.
   - The PHP built-in server sends no `Cache-Control`, `ETag` or `Last-Modified` for static files (Verified facts),
     so every page load fetches the current language's file once. It is about 4 KB, the same treatment as today's
     `app.js`.
   - Within a page session every loaded language is cached in memory, so switching back costs no request.
   - The other language is fetched on demand, not prefetched. Most users never switch, and one small request is not
     noticeable.
3. **Start-up and first paint.**
   - `main.ts` fetches the texts of the start language in parallel with `/api/map-config`
     (`Promise.allSettled`) and creates the map when both are in.
   - On the running app a static file answers in about 0.6 ms and `/api/map-config` in about 2 ms (Verified facts).
     The texts are therefore never on the critical path of the first map paint.
   - No `<link rel="preload">`: the hashed name is unknown to the hand-written HTML, and the file arrives first
     anyway.
   - **If the texts fail to load**, start-up fails like a failed map configuration. The map container is removed and
     the fallback error in `index.html` is shown, in Czech (the default language).
   - **If the configuration fails but the texts loaded**, the error is shown in the loaded language
     (`map.error`).
4. **Languages are discovered from the files.**
   - The language code is the file's base name: an ISO 639-1 code (`cs`, `en`). The same code is used as:
     - `<html lang>`;
     - the `Intl` locale for numbers;
     - the `lang` attribute of the language's switcher button.
   - `DEFAULT_LANGUAGE = 'cs'` is the only language constant in code.
   - Order: the default language first, then the others alphabetically by code ("Czech first").
   - A language's name is its own name from `Intl.DisplayNames([code], { type: 'language' })`, with the first
     letter upper-cased in that language: "Čeština", "English". No data is needed for it.
   - A new `de.json` + `de.svg` would add a button and a language with no code change.
5. **Key type from the default file.**
   - `TextKey` is `keyof typeof csTexts` with `import type csTexts from '../languages/cs.json'`. The import is
     type-only, so nothing of the file is bundled, and a typo in a key fails `tsc` (Verified facts).
   - At run time a file is validated only as a flat object of strings. Completeness is guaranteed by the consistency
     test (every file has exactly the keys of `cs.json`).
   - A key missing at run time renders as the key itself. That is a visible fault, not a broken panel; the test
     makes it unreachable for committed files.
6. **Land type and land use names live in the language files.**
   - Keys: `landType.<code>`, `landUse.<code>`, `landType.unknown`, `landUse.unknown`.
   - The frontend catalogue keeps only the colours: `LAND_TYPES` becomes `LAND_TYPE_COLOURS`, and `landUses.ts` is
     removed.
   - The code lists stay pinned by tests, now over the language files (Test scenarios).
   - Czech names are the official ST_UCIS names (task 006 Verified facts) with the first letter upper-cased, for
     example "Zastavěná plocha a nádvoří".
   - The parcel number keeps the cadastral `st.` notation in both languages (task 006 Decision 10).
7. **The flag is the language's second file.**
   - `frontend/languages/<code>.svg`, a hand-written SVG emitted as `public/build/languages/<code>-<hash>.svg` and
     shown with `<img>`.
   - It is not inlined into the bundle, which keeps the rule "language data is not in the JS" uniform. The bundle
     gains only the URLs.
   - Flag emoji are not used: Windows draws regional-indicator pairs as letters ("CZ", "GB"), Chrome included
     (Verified facts).
   - Both flags are shown in a 3:2 box of 24×16 CSS px. They are vectors, so they are sharp at every pixel ratio.
   - The Union Jack (2:1) is cropped at its sides to 3:2 with `preserveAspectRatio="xMidYMid slice"`.
   - The flags are not on the critical path: they are requested when the switcher is added after the map is
     created. A language switch requests no flag.
8. **The switcher.**
   - It is a MapLibre control (`LanguageSwitcherControl`) added to `bottom-left` after the legend. MapLibre inserts
     bottom-corner controls at the top of the corner, so the switcher sits above the legend.
   - It is shown in all three map states:
     - with details;
     - details not imported;
     - no parcel data, where there is no legend and it stands alone.
   - Accessibility:
     - Each button's accessible name is the language's own name (`aria-label`), and its `lang` attribute is the
       code, so a screen reader pronounces it correctly. `aria-pressed` marks the current language.
     - The flag image is decorative (`alt=""`).
     - The group has `role="group"` and a translated `aria-label` (`language.label`).
   - On hover the name shows as the native `title` tooltip, the same text as the accessible name. It needs no
     script, no listener and no layout.
   - Phones have no hover. Their users see the flags and the highlighted current language, and a tap switches the
     whole interface at once.
   - A custom CSS tooltip was rejected. It would stick out of the corner over the legend or the map, and it would
     need its own overlap cases.
   - Listeners: one `click` listener per button. Clicking the current language does nothing (the switch is a no-op).
9. **Switching re-renders in place** (`Localization`).
   - `Localization` holds the current `Texts` and a cache of loaded `Texts` per language. `switchTo(language)`:
     - for the current language with nothing pending, does nothing;
     - for a cached language, applies it at once;
     - otherwise loads it, caches it and applies it.
   - Applying sets the current texts and calls every `onChange` listener with them.
   - **The last request wins.** If the user picks another language (or the current one) while a load is pending,
     the earlier result is cached but not applied.
   - **A failed load** keeps the current language. It logs `console.warn('The texts could not be loaded.', error)`
     and resolves. The switcher keeps the current language pressed.
   - Listeners are plain callbacks, registered by `main.ts`. Each component has a `setTexts(texts)` method that
     rewrites its texts:
     - The panel re-renders its current content (loading, details, not found or error). It does not change
       visibility, does not call `onVisibilityChange` and does not move the map.
     - The legend keeps its `<details>` element and open state, and rewrites the summary and rows.
     - The switcher updates `aria-pressed` and the group label.
   - No map listener, no style, layer or source change, no `fitBounds`, no hash change. The map shows no text of its
     own except the attribution, which is not translated.
10. **MapLibre's own labels** are re-labelled through the DOM, because MapLibre 6.19 has no runtime locale setter
    (Verified facts).
    - `labelMapControls(map, texts)` sets the canvas `aria-label` (`map.getCanvas()`), and the `title` and
      `aria-label` of `.maplibregl-ctrl-zoom-in` and `.maplibregl-ctrl-zoom-out` inside `map.getContainer()`.
    - It runs after the `NavigationControl` is added and on every switch: one code path. The `locale` map option is
      not used.
    - MapLibre sets these labels only once (canvas in the constructor, buttons in `onAdd`), so nothing overwrites
      them later.
    - The attribution toggle button is hidden in non-compact mode (`compact: false`) and is left alone.
11. **Remembering the choice** (Q2).
    - Key `parcel-map.language` in `localStorage`, written after every successful switch.
    - At start-up a stored value that names an available language is used; anything else (nothing stored, an
      unknown code) gives `cs`. `navigator.language` is ignored (Q1).
    - Storage that is unavailable or throws (privacy modes, blocked storage) is treated as "nothing stored". A
      failed write is ignored with a `console.warn`.
    - Only the start language's texts are fetched at start-up.
12. **Layout** (Q5).
    - The switcher is 74×24 px measured (Verified facts). With the 10 px control margin it needs 2.25 rem above the
      legend.
    - The panel's height caps grow by that much:
      - `calc(100% - 19.75rem)` (was 17.5 rem);
      - `calc(100% - 24.25rem)` up to 800 px (was 22 rem).
    - The 007 lift of the bottom-left corner (`bottom: 4.75rem` up to 800 px) and the breakpoints are unchanged.
    - Accepted cost, visible panel before scrolling:
      - 641×480: 92 px (was 128);
      - 360×640: 252 of 275 px (was 288);
      - 320×568: 180 px;
      - 844×390: 74 px (not a tested viewport).
    - Today's caps would fit with only 2 px to spare at 641×480, which is too fragile across fonts.
13. **Czech wording** (Q6): the full text tables are under Architecture > Frontend. The draft listed the page title,
    hint, viewer link, "Typ parcely", the notices, the error and "Jazyk", and the developer accepted them. The other
    Czech strings follow the same style and the official ČÚZK terms (katastrální území, druh pozemku, způsob
    využití, výměra, stavební / pozemková parcela).
14. **CLAUDE.md Language rule exception** (Q4).
    - Step 1 adds this to the Language bullet in CLAUDE.md > Project:

      > Exception: user-facing texts are data. `frontend/languages/<language>.json` holds them in its language,
      > `frontend/index.html` carries the default language's title and fallback error, and tests and task files may
      > quote these texts verbatim. Everything else stays English.

    - It is added in the step that first adds Czech texts, not in the task-file commit.
15. **The existing E2E specs assert the Czech texts** from step 1 on, because Czech is the default. This changes the
    expected behaviour deliberately; it does not weaken the tests. English is covered by the new language specs.
16. **No logging** beyond the existing `console.warn` pattern (task 006 Decision 15).

## Verified facts

Verified on 2026-11-24 against `main` at 0dcca55, unless noted.

**Current frontend**

- `frontend/src/text.ts` holds `UI_LOCALE = 'en'` and `TEXT`. It is used in:
  - `parcelPanel.ts`, `legend.ts`, `zoomHint.ts`, `parcelFormat.ts`;
  - `landTypes.ts`, `landUses.ts` (unknown-code fallbacks);
  - `main.ts` (`TEXT.detailsNotice`).
- Two strings are inline in `main.ts`: "The map could not be loaded. Please try again later." and "Parcel data has
  not been imported yet. Run make data, then reload this page."
- `frontend/index.html` is static: `<html lang="en">`, `<title>Jičín Parcel Map</title>`, served by
  `ShowMapPageHandler`.
- The API returns land type and land use codes, not names.
- `tsconfig.app.json` includes `frontend` with `types: ["vite/client"]` and `moduleResolution: "bundler"`.
  `vite.config.ts` names every asset `[name][extname]`.

**PHP built-in server** (curl against the running app, and a throwaway `php -S` in the `parcel-map-app` image)

- Static files come with only `Date`, `Connection`, `Content-Type` and `Content-Length`: no `Cache-Control`, `ETag`
  or `Last-Modified`.
- `.json` is served as `application/json` and `.svg` as `image/svg+xml`. `<img>` needs the latter to render an SVG.
- Five requests each: a static file took 0.56 to 0.80 ms; `/api/map-config` took 2.0 to 6.9 ms.

**Vite 8.3.8, Vitest 5.1.1, TypeScript 6.0.3** (scratch build in `mcr.microsoft.com/playwright:v1.65.1-noble` with
the project's `node_modules`)

- `import.meta.glob<string>('../x/*.json', { query: '?url', import: 'default', eager: true })` inlines files under
  4 KB as base64 `data:` URLs, which would put the texts into the JS.
- With `query: '?url&no-inline'` each file is emitted as a separate asset. The bundle contains only
  `{"../x/cs.json": "/build/x/cs-D6hUUOvP.json", …}`. The same works for `*.svg`. Files are copied unchanged.
- In `rolldownOptions.output.assetFileNames(asset)`, `asset.originalFileNames` holds the source path relative to
  the Vite root (`x/cs.json`). In this project, with the root at the repository root, that is
  `frontend/languages/cs.json`.
- Vitest resolves the same glob: `'/x/cs.json?no-inline'`. A plain eager glob of the JSON files in a test gives
  their parsed content.
- `import type csTexts from '../x/cs.json'; type TextKey = keyof typeof csTexts;` type-checks, and an unknown key
  fails with `TS2322: Type '"nope"' is not assignable to type '"a.b" | "legend.title"'`.

**Intl** (Node in the Playwright image; Chromium uses the same ICU data)

- `new Intl.NumberFormat('cs').format(13077)` is `13 077` (code points `31 33 a0 30 37 37`, U+00A0 no-break space).
- `new Intl.NumberFormat('en').format(13077)` is `13,077`.
- `new Intl.DisplayNames(['cs'], { type: 'language' }).of('cs')` is `čeština`; for `en` it is `English`.

**MapLibre 6.19.0** (`node_modules/maplibre-gl/dist/maplibre-gl.d.ts`, `maplibre-gl-dev.mjs`)

- The only localisation API is the constructor option `locale`. There is no setter; the table is the private
  `_locale`. The official locale example only sets it at construction.
- `Map#addControl`: `if (position.includes("bottom")) positionContainer.insertBefore(controlElement, firstChild)`. A
  later bottom control therefore sits above the earlier ones.
- The canvas gets `aria-label` = `Map.Title` ("Map") once, in `_setupContainer`.
- `NavigationControl.onAdd` sets `title` and `aria-label` of `.maplibregl-ctrl-zoom-in` / `-zoom-out` once
  (`_setButtonTitle`). `_updateZoomButtons` only toggles `disabled`.

**Flags**

- Flag emoji render as two letters on Windows because Segoe UI Emoji has no flag ligatures; Chrome on Windows shows
  the letters. Sources: Mozilla bug 1970980, WordPress Trac #63451, emoji-picker-element issue #269.
- Both flag files on Wikimedia Commons are public domain, so the hand-written SVGs carry no licence obligation:
  - Czech flag (`File:Flag_of_the_Czech_Republic.svg`): "According to the Czech Copyright Act, this work is in the
    public domain"; white `#fff`, blue `#11457e`, red `#d7141a`.
  - UK flag (`File:Flag_of_the_United_Kingdom.svg`): Crown copyright expired, Public Domain Mark 1.0; red
    `#c8102e` (200, 16, 46), blue `#012169` (1, 33, 105), white.
- The two SVGs below (187 and 485 bytes) render correctly in the Playwright Chromium at device pixel ratio 2.

**Layout** (headless Chromium against the running dev app with real data, OSM blocked)

- Setup: a mock switcher was inserted as the first child of `.maplibregl-ctrl-bottom-left`, with the CSS of
  Architecture > Frontend; Czech texts were injected into the legend, panel, hint and notice; the proposed panel
  caps were applied; the legend was open.
- Switcher: 74×24 px. Boxes are `[left, top, right, bottom]` in px.

  | Viewport | Switcher | Legend (open) | Panel (`#parcel=1761441604`) | Panel visible / content |
  |---|---|---|---|---|
  | 1280×720 | 10, 459, 84, 483 | 10, 493, 203, 710 | 12, 12, 332, 267 | 255 / 255 |
  | 641×480 | 10, 143, 84, 167 | 10, 177, 203, 394 | 12, 12, 332, 104 | 92 / 255 |
  | 390×844 | 10, 507, 84, 531 | 10, 541, 203, 758 | 12, 12, 334, 267 | 255 / 255 |
  | 360×640 | 10, 303, 84, 327 | 10, 337, 203, 554 | 12, 12, 304, 264 | 252 / 275 |
  | 320×568 | 10, 231, 84, 255 | 10, 265, 203, 482 | 12, 12, 264, 192 | 180 / 275 |
  | 844×390 | 10, 129, 84, 153 | 10, 163, 203, 380 | 12, 12, 332, 86 | 74 / 255 |

- The gap between the panel's bottom and the switcher's top is at least 39 px. No two elements overlap at any of
  these sizes, with the hint (default view) or the panel.
- Czech texts:
  - The legend is the same height as the English one (217 px), 195 px wide.
  - The hint "Pro výběr parcely přibližte mapu." is 222 px wide on one line and fits at 320 px.
  - The Czech details notice wraps to at most 7 lines at 320 px (top 12 to 151) and stays clear of the
    bottom-left corner.
- The scripts are not committed.

**Code lists**: the Czech land type and land use names are those of ST_UCIS 2026-11-03, recorded in task 006
Verified facts.

## Steps

The first commit on the branch is this task file (`docs(tasks): add 008 Language switcher`). Each step is one
commit, and every commit passes `make check`. Write the E2E test of each step first and watch it fail for the right
reason, then drive the modules with Vitest.

1. **Czech interface from data files**
   (`feat(map): load the interface texts from per-language files, Czech by default (#008)`).
   - The page is Czech. Its texts come from `frontend/languages/cs.json`, fetched at start-up in parallel with the
     map configuration. `en.json` exists and is complete.
   - `text.ts` and `landUses.ts` are removed, and `LAND_TYPES` becomes `LAND_TYPE_COLOURS`.
   - `index.html` is Czech.
   - The existing E2E specs assert Czech.
   - Adds the consistency tests and the E2E proof that the bundle holds no texts.
   - Docs: CLAUDE.md Language rule exception, Glossary `Language` and `Texts`, README.
2. **Switch the language in place** (`feat(map): switch the interface language without reloading (#008)`).
   - Adds the flag files, the switcher above the legend and `Localization`, plus `setTexts` on every component,
     `<html lang>`, the title and the MapLibre labels.
   - Adds the panel caps.
   - The no-overlap E2E gets `.map-language` and English cases.
   - Docs: Glossary `Language` (flag) and Language switcher, README.
3. **Remember the chosen language** (`feat(map): remember the chosen language (#008)`).
   - The choice survives a reload via `localStorage`. At start-up only the stored language's texts are fetched.
   - Docs: README.

## Architecture

Frontend only. Paths, public names and signatures below are binding. Private helpers and test code are left to the
implementation.

### Domain / Application / Infrastructure / Handlers / Commands / Wiring

No change. No Bootstrap change. `ShowMapPageHandler` keeps serving `frontend/index.html` unchanged.

### Frontend

#### Language files (`frontend/languages/`)

Step 1 adds `cs.json` and `en.json`; step 2 adds `cs.svg` and `en.svg`. Each JSON file is one flat object, formatted
by Prettier, with keys in the order of the table. Placeholders are `{parcelNumber}` and `{code}`.

| Key | `en` | `cs` |
|---|---|---|
| `page.title` | Jičín Parcel Map | Mapa parcel okresu Jičín |
| `map.error` | The map could not be loaded. Please try again later. | Mapu se nepodařilo načíst. Zkuste to prosím později. |
| `map.label` | Map | Mapa |
| `map.zoomIn` | Zoom in | Přiblížit |
| `map.zoomOut` | Zoom out | Oddálit |
| `map.zoomHint` | Zoom in to select a parcel. | Pro výběr parcely přibližte mapu. |
| `notice.parcelsMissing` | Parcel data has not been imported yet. Run make data, then reload this page. | Data parcel zatím nebyla importována. Spusťte make data a pak stránku načtěte znovu. |
| `notice.detailsMissing` | Parcel details have not been imported yet. Run make data, then reload this page. | Podrobnosti o parcelách zatím nebyly importovány. Spusťte make data a pak stránku načtěte znovu. |
| `language.label` | Language | Jazyk |
| `legend.title` | Land type | Druh pozemku |
| `legend.unknown` | Unknown land type | Neznámý druh pozemku |
| `panel.label` | Parcel details | Podrobnosti o parcele |
| `panel.heading` | Parcel {parcelNumber} | Parcela {parcelNumber} |
| `panel.close` | Close parcel details | Zavřít podrobnosti o parcele |
| `panel.cadastralArea` | Cadastral area | Katastrální území |
| `panel.municipality` | Municipality | Obec |
| `panel.plotType` | Plot type | Typ parcely |
| `panel.landType` | Land type | Druh pozemku |
| `panel.landUse` | Land use | Způsob využití |
| `panel.area` | Area | Výměra |
| `panel.viewerLink` | Show owners in the ČÚZK cadastre viewer | Zobrazit vlastníky v Nahlížení do katastru nemovitostí |
| `panel.loading` | Loading parcel details… | Načítám podrobnosti o parcele… |
| `panel.notFound` | This parcel was not found. If the data has been updated, reload the page. | Tato parcela nebyla nalezena. Pokud byla data aktualizována, načtěte stránku znovu. |
| `panel.error` | The parcel details could not be loaded. Please try again. | Podrobnosti o parcele se nepodařilo načíst. Zkuste to prosím znovu. |
| `plotType.building` | Building plot | Stavební parcela |
| `plotType.land` | Land plot | Pozemková parcela |
| `landType.unknown` | Unknown land type (code {code}) | Neznámý druh pozemku (kód {code}) |
| `landUse.unknown` | Unknown land use (code {code}) | Neznámý způsob využití (kód {code}) |

The English texts are today's texts unchanged. `map.label`, `map.zoomIn` and `map.zoomOut` are MapLibre's defaults.

Land types (`landType.<code>`, the codes of `LAND_TYPE_COLOURS`):

| Code | `en` | `cs` |
|---|---|---|
| 2 | Arable land | Orná půda |
| 3 | Hop field | Chmelnice |
| 4 | Vineyard | Vinice |
| 5 | Garden | Zahrada |
| 6 | Orchard | Ovocný sad |
| 7 | Permanent grassland | Trvalý travní porost |
| 8 | Permanent grassland | Trvalý travní porost |
| 10 | Forest land | Lesní pozemek |
| 11 | Water area | Vodní plocha |
| 13 | Built-up area and courtyard | Zastavěná plocha a nádvoří |
| 14 | Other area | Ostatní plocha |

Land uses (`landUse.<code>`, codes 1 to 30). English as in today's `landUses.ts`.

| Code | `en` | `cs` |
|---|---|---|
| 1 | Greenhouse, hotbed | Skleník, pařeniště |
| 2 | Tree nursery | Školka |
| 3 | Woody plant plantation | Plantáž dřevin |
| 4 | Forest other than commercial forest | Les jiný než hospodářský |
| 5 | Forest land with a building | Lesní pozemek, na kterém je budova |
| 6 | Fishpond | Rybník |
| 7 | Natural or regulated watercourse bed | Koryto vodního toku přirozené nebo upravené |
| 8 | Artificial watercourse bed | Koryto vodního toku umělé |
| 9 | Natural water reservoir | Vodní nádrž přírodní |
| 10 | Artificial water reservoir | Vodní nádrž umělá |
| 11 | Wetland | Zamokřená plocha |
| 12 | Shared courtyard | Společný dvůr |
| 13 | Demolition site | Zbořeniště |
| 14 | Railway | Dráha |
| 15 | Motorway | Dálnice |
| 16 | Road | Silnice |
| 17 | Other road | Ostatní komunikace |
| 18 | Other transport area | Ostatní dopravní plocha |
| 19 | Green space | Zeleň |
| 20 | Sports and recreation area | Sportoviště a rekreační plocha |
| 21 | Cemetery | Pohřebiště |
| 22 | Cultural and educational area | Kulturní a osvětová plocha |
| 23 | Handling area | Manipulační plocha |
| 24 | Mining area | Dobývací prostor |
| 25 | Landfill | Skládka |
| 26 | Miscellaneous area | Jiná plocha |
| 27 | Unproductive land | Neplodná půda |
| 28 | Water area with a building | Vodní plocha, na které je budova |
| 29 | Photovoltaic power plant | Fotovoltaická elektrárna |
| 30 | Field boundary, slope | Mez, stráň |

Flags (step 2). Hand-written, geometry and colours from the public-domain Commons files (Verified facts):

```svg
<!-- cs.svg: 3:2, blue wedge to half the length -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 20"><path fill="#d7141a" d="M0 0h30v20H0z"/><path fill="#fff" d="M0 0h30v10H0z"/><path fill="#11457e" d="M0 0l15 10L0 20z"/></svg>
```

```svg
<!-- en.svg: Union Jack 2:1, cropped to the 3:2 box at its sides -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 30" preserveAspectRatio="xMidYMid slice"><clipPath id="t"><path d="M30 15h30v15zv15H0zH0V0zV0h30z"/></clipPath><path fill="#012169" d="M0 0h60v30H0z"/><path stroke="#fff" stroke-width="6" d="M0 0l60 30m0-30L0 30"/><path stroke="#c8102e" stroke-width="4" clip-path="url(#t)" d="M0 0l60 30m0-30L0 30"/><path stroke="#fff" stroke-width="10" d="M30 0v30M0 15h60"/><path stroke="#c8102e" stroke-width="6" d="M30 0v30M0 15h60"/></svg>
```

(The comments above are for this file only; the committed SVGs start with `<svg`. Prettier may reformat them, which
is fine.)

#### `vite.config.ts` (step 1)

- `assetFileNames` becomes a function:
  - an asset whose `originalFileNames` has an entry starting with `frontend/languages/` gets
    `languages/[name]-[hash][extname]`;
  - every other asset keeps `[name][extname]`.
- Add a comment: language files are fetched at run time and change with their content, so they are hashed; the
  hand-written HTML references only the fixed names.

#### `frontend/index.html` (step 1)

- `<html lang="cs">`, `<title>Mapa parcel okresu Jičín</title>`.
- After `#map`: `<p class="map-error" role="alert" hidden>Mapu se nepodařilo načíst. Zkuste to prosím později.</p>`.
- These must equal `cs.json` `page.title` and `map.error` (Vitest).

#### `frontend/src/texts.ts` (step 1, new; replaces `text.ts`)

```ts
import type csTexts from '../languages/cs.json';
export type TextKey = keyof typeof csTexts;

export class TextsError extends Error {}

/** The interface texts of one language. */
export class Texts {
  constructor(readonly language: string, entries: Readonly<Record<string, string>>);
  /** The text of a key with its {placeholders} replaced; a key without a text renders as the key itself. */
  text(key: TextKey, values?: Readonly<Record<string, string>>): string;
  landTypeName(code: number): string;   // landType.<code>, else landType.unknown with {code}
  landUseName(code: number): string;    // landUse.<code>, else landUse.unknown with {code}
  formatNumber(value: number): string;  // Intl.NumberFormat(language), created once per Texts
}

/** Throws TextsError unless body is a non-empty plain object whose values are all strings. */
export function parseTexts(language: string, body: unknown): Texts;

/** Throws TextsError when the request fails, answers a non-2xx status, or the body is not valid JSON or not texts. */
export async function loadTexts(fetchFn: typeof fetch, url: string, language: string): Promise<Texts>;
```

- Error messages follow `mapConfiguration.ts` (`{ cause }` where there is one).
- `loadTexts` requests with `Accept: application/json`.
- A placeholder without a value stays as it is.

#### `frontend/src/language.ts` (step 1, new)

```ts
export const DEFAULT_LANGUAGE = 'cs';
/** The default language first, then the others alphabetically. */
export function orderLanguages(languages: readonly string[]): string[];
/** The stored language when it is available, otherwise the default. Step 3 uses it; step 1 adds it with its tests. */
export function chooseLanguage(stored: string | null, available: readonly string[]): string;
/** The language's own name, first letter upper-cased in that language: 'Čeština', 'English'. Falls back to the upper-cased code. */
export function languageName(language: string): string;
```

#### `frontend/src/languageFiles.ts` (step 1, new; the flag part in step 2)

- This is the only module with the `import.meta.glob` calls:
  - `'../languages/*.json'` (step 1);
  - `'../languages/*.svg'` (step 2);
  - both with `{ query: '?url&no-inline', import: 'default', eager: true }`.
- The language code is the file's base name.

```ts
export class UnknownLanguage extends Error {}
/** Every language with a texts file, ordered by orderLanguages. */
export function availableLanguages(): readonly string[];
/** The URL of the language's texts; throws UnknownLanguage for a language without a texts file. */
export function textsUrl(language: string): string;
/** Step 2. The URL of the language's flag; throws UnknownLanguage for a language without a flag file. */
export function flagUrl(language: string): string;
```

#### `frontend/src/landTypes.ts` (step 1)

- `LAND_TYPES` and `LandTypeStyle` are replaced by `export const LAND_TYPE_COLOURS: ReadonlyMap<number, string>`.
  It has the same codes, colours and code-list comment, and keeps the Czech official names as comments.
- `landTypeName` is removed (now `Texts.landTypeName`). `FALLBACK_COLOUR` and `landTypeFillColour()` are unchanged
  in output.

#### `frontend/src/landUses.ts` (step 1)

Removed. Its names are in the language files, and `landUseName` is now `Texts.landUseName`.

#### `frontend/src/parcelFormat.ts` (step 1)

- `formatArea(squareMetres: number, texts: Texts): string` is `${texts.formatNumber(squareMetres)} m²`.
- `plotTypeName(kind: 'building' | 'land', texts: Texts): string`.
- `formatCadastralArea` is unchanged.

#### `frontend/src/legend.ts`

- Step 1:
  - `legendEntries(texts: Texts): readonly LegendEntry[]`: codes of `LAND_TYPE_COLOURS` in order, named by
    `texts.landTypeName`, de-duplicated by name and colour, then `legend.unknown` in `FALLBACK_COLOUR`.
  - `new LegendControl(open: boolean, texts: Texts)`.
- Step 2: `setTexts(texts: Texts): void` rewrites the summary and replaces the list rows. It keeps the `<details>`
  element and its `open` state. The DOM is otherwise as in task 007.

#### `frontend/src/zoomHint.ts`

- Step 1: `showZoomHint(map, isPanelOpen, texts: Texts): { update(): void; setTexts(texts: Texts): void }`.
- `setTexts` only replaces the text (step 2 uses it; step 1 may add it already). The listeners are unchanged.

#### `frontend/src/parcelPanel.ts`

- Step 1: `constructor(texts: Texts, onClose: () => void, onVisibilityChange: () => void)`. Every string comes from
  `texts`.
- Step 2: `setTexts(texts: Texts): void`.
  - The panel remembers its content: `loading`, `detail` (with the `ParcelDetail`), `notFound` or `error`.
  - `setTexts` stores the texts, resets the panel's and the close button's `aria-label` and the viewer link text, and
    re-renders the remembered content.
  - It never changes `hidden` and never calls `onVisibilityChange`.

#### `frontend/src/mapNotice.ts` (step 1, new; replaces `showNotice` in `main.ts`)

```ts
export class MapNotice {
  constructor(key: 'notice.parcelsMissing' | 'notice.detailsMissing', texts: Texts); // appends <p class="map-notice" role="status">
  setTexts(texts: Texts): void;
}
```

#### `frontend/src/mapLabels.ts` (step 1, new)

`export function labelMapControls(map: MapLibreMap, texts: Texts): void`. It sets:
- the canvas `aria-label` to `map.label`;
- the `title` and `aria-label` of `.maplibregl-ctrl-zoom-in` to `map.zoomIn` and of `.maplibregl-ctrl-zoom-out` to
  `map.zoomOut`, searched in `map.getContainer()`.

A missing button is skipped. The comment says why: MapLibre has no runtime locale setter (Decision 10).

#### `frontend/src/documentTexts.ts` (step 1, new)

`export function applyDocumentTexts(target: { documentElement: { lang: string }; title: string }, texts: Texts): void`
sets `lang` to `texts.language` and `title` to `page.title`.

#### `frontend/src/localization.ts` (step 2, new)

```ts
export class Localization {
  constructor(initial: Texts, load: (language: string) => Promise<Texts>);
  current(): Texts;
  onChange(listener: (texts: Texts) => void): void;
  /** Never rejects: a failed load keeps the current language and is reported with console.warn. */
  switchTo(language: string): Promise<void>;
}
```

The semantics are those of Decision 9. The initial texts are cached under their language.

#### `frontend/src/languageSwitcher.ts` (step 2, new)

```ts
export interface LanguageOption {
  readonly code: string;
  readonly name: string;     // languageName(code)
  readonly flagUrl: string;  // flagUrl(code)
}

/** Flag buttons above the legend. Only listener: a click on a button. */
export class LanguageSwitcherControl implements IControl {
  constructor(options: readonly LanguageOption[], texts: Texts, onSelect: (code: string) => void);
  onAdd(): HTMLElement;
  onRemove(): void;
  setTexts(texts: Texts): void; // aria-pressed by texts.language, group aria-label = language.label
}
```

`onAdd` builds this DOM once (texts via `textContent` and attributes, never `innerHTML`):

```html
<div class="maplibregl-ctrl map-language" role="group" aria-label="Jazyk">
  <button type="button" lang="cs" aria-label="Čeština" title="Čeština" aria-pressed="true">
    <img src="/build/languages/cs-<hash>.svg" alt="" width="24" height="16">
  </button>
  <button type="button" lang="en" aria-label="English" title="English" aria-pressed="false">
    <img src="/build/languages/en-<hash>.svg" alt="" width="24" height="16">
  </button>
</div>
```

#### `frontend/src/languagePreference.ts` (step 3, new)

```ts
export const LANGUAGE_STORAGE_KEY = 'parcel-map.language';
export interface LanguageStorage {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
}
/** The stored language, or null when nothing is stored or the storage cannot be read (any error). */
export function storedLanguage(storage: () => LanguageStorage): string | null;
/** Stores the language; a storage error is reported with console.warn and otherwise ignored. */
export function rememberLanguage(storage: () => LanguageStorage, language: string): void;
```

`main.ts` passes `() => window.localStorage`. Reading `window.localStorage` can itself throw, hence the getter.

#### `frontend/src/main.ts`

- **Step 1:**
  - `start()` uses `language = DEFAULT_LANGUAGE`.
  - It runs `Promise.allSettled([loadMapConfiguration(…), loadTexts(fetchFn, textsUrl(language), language)])`.
  - If either is rejected: `console.warn('The map could not be loaded.', reason)`, then `showError(container,
    texts | null)`.
    - It removes the container.
    - When texts are available, it sets the `.map-error` element's text to `map.error`; otherwise it keeps the
      HTML's Czech text.
    - It un-hides the element.
  - Otherwise:
    - `applyDocumentTexts(document, texts)`;
    - the three existing branches, with `texts` passed to the panel, hint, legend and `MapNotice` (`parcels ===
      null` gives `notice.parcelsMissing`, a `null` `parcelUrlTemplate` gives `notice.detailsMissing`);
    - `labelMapControls(map, texts)` right after the `NavigationControl` is added, in every branch.
  - `openLinkedParcel` is unchanged apart from the panel's texts.
- **Step 2:**
  - Creates `new Localization(texts, (code) => loadTexts(fetchFn, textsUrl(code), code))`.
  - In every branch, after the legend (or alone without parcel data), adds
    `new LanguageSwitcherControl(options, texts, (code) => { void localization.switchTo(code); })` to
    `bottom-left`, with options from `availableLanguages()`, `languageName` and `flagUrl`.
  - Registers one `onChange` listener that calls `applyDocumentTexts`, `labelMapControls` and the `setTexts` of
    every component that exists in that branch: panel, hint, legend, notice, switcher.
- **Step 3:**
  - The start language is `chooseLanguage(storedLanguage(() => window.localStorage), availableLanguages())`.
  - One more `onChange` listener calls `rememberLanguage(() => window.localStorage, texts.language)`.

#### `frontend/src/app.css`

- **Step 1:** `.map-error[hidden] { display: none; }`, the same pattern as the panel and the hint.
- **Step 2:**
  - Panel caps: `.parcel-panel { max-height: calc(100% - 19.75rem) }`, and in `@media (max-width: 800px)`
    `calc(100% - 24.25rem)`. The comment says: room for the open legend (≤ 15 rem) and the language switcher above
    it, plus margins.
  - `.map-language`: `display: flex`, `overflow: hidden`, background `rgb(255 255 255 / 0.92)`,
    `border-radius: 6px`, the legend's shadow.
  - `.map-language button`: `display: flex`, `margin: 0`, `padding: 0.25rem 0.4rem`, transparent background,
    `border: 0`, `cursor: pointer`.
  - `.map-language button img`: `display: block`, `width: 1.5rem`, `height: 1rem`,
    `box-shadow: 0 0 0 1px rgb(0 0 0 / 0.25)` (keeps the white of the Czech flag visible).
  - `.map-language button[aria-pressed='true']`: background `#0b5cad`.
  - `.map-language button[aria-pressed='false'] img`: `opacity: 0.75`. On `:hover`: opacity 1 and background
    `#eee`.
  - `.map-language button:focus-visible`: `outline: 2px solid #0b5cad`, `outline-offset: -2px` (inside, because the
    group clips overflow).

## Test scenarios

Vitest runs in Node; the modules that build DOM are covered by E2E. Tests build `Texts` from the committed files with
`parseTexts('cs', …)` / `parseTexts('en', …)` through a small support module in `frontend/tests/support/`.

`text.test.ts` and `landUses.test.ts` are replaced, and the name cases of `landTypes.test.ts` move to the language
files tests. Every assertion they made is kept there (English names, unknown-code wording, heading, hint, notice,
legend strings). Record this in the implementation log.

### Unit (Vitest)

**Step 1**

- `languageFiles.test.ts`, over all `frontend/languages/*.json` via an eager glob:
  - `cs` and `en` exist, and `availableLanguages()` is `['cs', 'en']`;
  - every file is a non-empty flat object of non-empty strings;
  - every file has exactly the keys of `cs.json`;
  - for every key, every language has the same set of `{placeholders}`;
  - the `landType.<n>` keys are exactly the codes of `LAND_TYPE_COLOURS`;
  - the `landUse.<n>` keys are exactly 1 to 30;
  - every land type and land use name of both languages is pinned to the tables above;
  - `textsUrl('cs')` ends with `cs.json` (Vitest URL form), and `textsUrl('de')` throws `UnknownLanguage`.
- `indexHtml.test.ts` (`../index.html?raw`): `lang="cs"`; the `<title>` equals `cs.json` `page.title`; the
  `.map-error` paragraph is `hidden` and its text equals `map.error`.
- `texts.test.ts`:
  - `parseTexts` accepts a flat object of strings. It throws `TextsError` for `null`, an array, a string, an empty
    object, a nested object and a number value.
  - `text('panel.heading', { parcelNumber: 'st. 72' })` gives `Parcela st. 72` / `Parcel st. 72`.
  - A placeholder without a value stays as it is. An unknown key at run time returns the key (cast in the test).
  - `landTypeName(13)` gives `Zastavěná plocha a nádvoří` / `Built-up area and courtyard`; `landTypeName(99)` gives
    `Neznámý druh pozemku (kód 99)` / `Unknown land type (code 99)`.
  - `landUseName(17)` and `landUseName(31)` likewise.
  - `formatNumber(13077)`: `cs` gives `13 077`; `en` gives `13,077`.
  - `loadTexts` with fake fetches:
    - 200 with valid JSON resolves, with `language` set;
    - 404 and 500 throw `TextsError` naming the status;
    - a body that is not JSON throws `TextsError`;
    - a rejected fetch throws `TextsError` with the cause;
    - the request carries `Accept: application/json`.
- `language.test.ts`:
  - `orderLanguages(['en', 'cs'])` gives `['cs', 'en']`; `orderLanguages(['en', 'de', 'cs'])` gives
    `['cs', 'de', 'en']`;
  - `chooseLanguage(null | '' | 'de' | 'EN', ['cs', 'en'])` gives `cs`; `chooseLanguage('en', …)` gives `en`;
  - `languageName('cs')` gives `Čeština`; `languageName('en')` gives `English`.
- `parcelFormat.test.ts`: `formatArea(455, cs)` gives `455 m²`; `formatArea(13077, cs)` gives `13 077 m²`;
  `formatArea(13077, en)` gives `13,077 m²`; `plotTypeName` in both languages.
- `legend.test.ts`:
  - Czech: 11 entries, in order Orná půda, Chmelnice, Vinice, Zahrada, Ovocný sad, Trvalý travní porost, Lesní
    pozemek, Vodní plocha, Zastavěná plocha a nádvoří, Ostatní plocha, Neznámý druh pozemku. "Trvalý travní porost"
    appears once.
  - English: the 007 list.
  - Colours equal `LAND_TYPE_COLOURS`, and the last entry is `#9e9e9e`.
- `landTypes.test.ts`: the codes are exactly 2, 3, 4, 5, 6, 7, 8, 10, 11, 13, 14; the colours and fill expression are
  as before.
- `documentTexts.test.ts`: `applyDocumentTexts` sets `lang` and `title` on a plain object, for both languages.

**Step 2**

- `languageFiles.test.ts`:
  - every `.json` has an `.svg` of the same code and vice versa (globs over both);
  - every SVG starts with `<svg` and has a `viewBox` (`?raw` glob);
  - `flagUrl('en')` ends with `en.svg`, and `flagUrl('de')` throws `UnknownLanguage`.
- `localization.test.ts` (fake `load` with controllable promises):
  - `current()` is the initial texts;
  - switching to `en` loads once, sets `current()` and calls every listener once with the English texts;
  - switching back to `cs` does not call `load` (cached) and notifies;
  - switching to the current language does not load and does not notify;
  - en is pending, then the user picks `cs`: when en resolves, `current()` stays `cs` and listeners are not called
    for en; a later switch to `en` uses the cache;
  - a failed load keeps `current()`, notifies nobody, resolves, and calls `console.warn` (spied);
  - after a failure a new switch to the same language calls `load` again (failures are not cached).

**Step 3**

- `languagePreference.test.ts` (in-memory `LanguageStorage` fakes):
  - `storedLanguage` returns the stored value, or `null` when unset;
  - it returns `null` when the getter throws and when `getItem` throws;
  - `rememberLanguage` writes `parcel-map.language`;
  - when the getter or `setItem` throws, it does not throw and calls `console.warn`.

### Integration / API

None (no backend change).

### E2E (Playwright)

Selectors:
- Switcher buttons: `getByRole('button', { name: 'English' })` / `{ name: 'Čeština' }`.
- Text requests: `page.on('request')` URLs matching `/build/languages/` and ending in `.json`.
- Parcel tile requests: `/tiles/parcels/`.
- OSM tiles: `offlineTiles.requested()`.

**Step 1**

- Existing specs, rewritten to the Czech texts (Decision 15):
  - `map.spec.ts`, `parcels.spec.ts`: the zoom button is `Přiblížit`.
  - `parcel-detail.spec.ts`:
    - region `Podrobnosti o parcele`, headings `Parcela st. 72` / `Parcela 1171`;
    - `Stavební parcela`, `Pozemková parcela`, `Zastavěná plocha a nádvoří`, `Ostatní plocha`, `Ostatní
      komunikace`, labels `Obec` / `Způsob využití`;
    - link `Zobrazit vlastníky v Nahlížení do katastru nemovitostí`, close `Zavřít podrobnosti o parcele` (also where
      the spec uses `name: 'Close'`);
    - error, not-found and hint texts from `cs.json`.
  - `legend.spec.ts`: the Czech names, summary `Druh pozemku`.
  - `layout.spec.ts`: the Czech heading and not-found text.
  - `map-error.spec.ts`: alert `Mapu se nepodařilo načíst. Zkuste to prosím později.`.
  - `no-parcel-data.spec.ts`: the Czech notice, region and hint.
- New `tests/E2E/language.spec.ts`:
  - The default page is Czech: `html[lang="cs"]`, title `Mapa parcel okresu Jičín`, legend summary `Druh pozemku`,
    hint `Pro výběr parcely přibližte mapu.`, zoom button named `Přiblížit`, canvas `aria-label` `Mapa`.
  - Exactly one texts file is requested on load, and it matches `/build/languages/cs-[^/]+\.json$`.
  - The bundle holds no texts.
    - Fetch `/build/app.js` with `page.request` and read every `frontend/languages/*.json` with `node:fs`.
    - No text of 12 or more characters from any language appears in the bundle.
    - The bundle does contain `/build/languages/cs-` (it references the file).
  - A linked parcel shows Czech numbers: `/#parcel=1761441604` shows `13 077 m²` (U+00A0).
- `map-error.spec.ts`, new case: `**/build/languages/*.json` answers 500. The alert shows the Czech fallback from the
  HTML and there is no canvas (`allowedConsoleErrors: [/status of 500/]`).

**Step 2** (`language.spec.ts`)

- **The switcher.**
  - Above the legend: its bottom is above the legend's top, its left edge within 20 px of 0.
  - Two buttons, Czech first, `Čeština` pressed (`aria-pressed="true"`), `English` not.
  - Each has `title` equal to its name and `lang` `cs` / `en`.
  - Each image has `naturalWidth > 0` and a `src` matching `/build/languages/(cs|en)-[^/]+\.svg$`.
  - Group name `Jazyk`.
- **Switching in place with the panel open** (`/#parcel=1761441604`, Czech):
  - Before the switch, set a `window` marker, keep a handle to the canvas element and note the request counts:
    parcel tiles, OSM tiles, flags.
  - Click `English`, then check in place:
    - heading `Parcel 1171`, `Other area`, `Other road`, `13,077 m²`, the link text, the close button
      `Close parcel details`, region `Parcel details`;
    - legend summary `Land type` and English rows, the legend still in the same open state;
    - zoom buttons `Zoom in` / `Zoom out` (name and `title`), canvas `aria-label` `Map`;
    - `html[lang="en"]`, title `Jičín Parcel Map`, `English` pressed, group `Language`;
    - the hash is still `#parcel=1761441604`.
  - After `networkidle`:
    - the marker is still set and the canvas is the same element (`page.evaluate`);
    - no new parcel tile, OSM tile or flag request;
    - exactly one `en` texts request;
    - no `framenavigated` event for the main frame.
- **Switching back** to `Čeština` restores the Czech texts with no new texts request (cs and en each requested
  once).
- **Other states switch too:**
  - the default view: the hint becomes `Zoom in to select a parcel.`;
  - `/#parcel=999`: the not-found text (`allowedConsoleErrors` 404);
  - a stubbed parcel detail 500: the error text (`allowedConsoleErrors` 500);
  - `parcelUrlTemplate: null`: the details notice;
  - `parcels: null`: the switcher is visible without a legend, and the parcel-data notice switches.
- **A failed switch:** `**/build/languages/en-*.json` answers 500. Clicking `English` leaves the page Czech, with
  `Čeština` still pressed (`allowedConsoleErrors` 500).
- **`layout.spec.ts`:**
  - `.map-language` is added to `ELEMENTS`. The 16 existing cases (4 viewports × 4 states, Czech) keep passing.
  - New per viewport: state (a), the details panel, after switching to English. It shows no overlap and the panel
    height is below the viewport height minus 24 px.

**Step 3** (`language.spec.ts`)

- Choose `English`, reload:
  - the page opens in English (`html[lang="en"]`, legend `Land type`, `English` pressed);
  - the only texts request of the reload is `en-…json`.
- Choose `Čeština` again, reload: the page is Czech.
- A stored value that is not a language (`page.addInitScript` sets `parcel-map.language` to `xx`) gives Czech.

## Acceptance criteria

- [ ] The page opens in Czech for a first-time visitor, whatever the browser language. All texts are Czech, with
      proper diacritics, and the area is formatted `13 077 m²`.
- [ ] The texts are not in the JS bundle. They are fetched at run time from
      `/build/languages/<code>-<hash>.json`, only the current language at start-up (E2E).
- [ ] Two flag buttons (Czech, then the UK flag for English) sit above the legend and show "Čeština" / "English"
      on hover. They are reachable by keyboard and named for screen readers.
- [ ] Switching changes every visible text in place: panel in any state, legend, hint, notice, switcher, `<html
      lang>`, title, zoom-button and canvas labels.
- [ ] Switching causes no reload, keeps the same map canvas, requests no tile and no flag, and keeps the hash (E2E).
- [ ] The chosen language survives a reload. Without a valid stored choice the page is Czech.
- [ ] Every language file has the same keys and placeholders. Land type and land use names are complete in both
      languages (Vitest).
- [ ] No overlap at 1280×720, 641×480, 390×844 and 360×640 in all states with the switcher, and in English with the
      details panel (E2E).
- [ ] Smoothness:
  - switching adds no map listener and no `move`/`render` work, and changes no style, layer or source (code review +
    E2E tile counts);
  - the switcher adds only click listeners on its buttons;
  - the texts are fetched in parallel with `/api/map-config`.
- [ ] Manual check in a real browser, recorded in the implementation log (mark "NOT done" if not possible):
  - the flags look right, including on a high-density screen;
  - the tooltip shows on hover;
  - switching with the panel open is instant and the map does not flicker.
- [ ] `make check` passes at every commit (`git rebase --exec "make check" main`).
- [ ] README, CLAUDE.md (Language rule, Glossary) updated as listed below.

## Documentation updates per commit

- **Step 1:**
  - CLAUDE.md:
    - the Language-rule exception of Decision 14, added to the "Language:" bullet under Project;
    - Glossary rows:
      - `| — | Language | A language of the interface, identified by its ISO 639-1 code (`cs`, the default, and `en`); its texts are `frontend/languages/<code>.json`. |`
      - `| — | Texts | The interface strings of one language, keyed by text keys such as `panel.heading`; fetched at run time, never bundled into the JS. |`
  - README:
    - Status and Running: the interface is Czech by default. Keep the quoted messages in English and add once that
      these are the English texts of a Czech-by-default interface.
    - Notes > Decisions: replace "English interface, ready for translations" with "Texts are data": one JSON file
      per language in `frontend/languages/`, emitted as separate content-hashed files, fetched in parallel with the
      map configuration, never in the bundle. Mention that the built-in server sends no cache headers.
    - Frontend section: where to edit the texts.
- **Step 2:**
  - CLAUDE.md Glossary:
    - the `Language` row adds "and its flag `<code>.svg`";
    - new row `| — | Language switcher | The flag buttons above the legend (LanguageSwitcherControl); switching re-renders the texts in place, without reloading. |`
  - README:
    - Running: the switcher, with the name on hover.
    - Notes > Decisions:
      - switching re-renders in place: no reload, no map or tile change, MapLibre labels set through the DOM;
      - flags are hand-written SVG files because Windows has no flag emoji;
      - the attribution is not translated;
      - the panel caps include the switcher.
    - "With more time": a production server should serve `/build/languages/` with immutable caching.
- **Step 3:** README Running: the chosen language is remembered in the browser (`localStorage`), and Czech is used
  otherwise, whatever the browser language.

## Risks and assumptions

- **Smaller panel on short windows** (Decision 12, accepted): 92 px visible at 641×480, 74 px at 844×390.
- **The MapLibre re-labelling** relies on the documented CSS classes `.maplibregl-ctrl-zoom-in/out` and on MapLibre
  setting those labels only once. A MapLibre upgrade could change this; the E2E label assertions catch it.
- **No HTTP caching** with the built-in server: the current language's file (about 4 KB) and the two flags (under
  1 KB) are fetched on every page load. This is negligible next to `app.js`.
- **Czech wording** beyond the strings the developer saw in the draft is the plan's, using official ČÚZK terms. It
  is easy to change in `cs.json`; the tests pin the names, so a change updates the test tables too.
- **Flags stand for countries, not languages.** This is a known UX caveat; the name on hover and the accessible
  names cover it. Touch devices show no tooltip.
- **Fonts differ across systems.** The measurements are from the Playwright image. The caps reserve the legend's
  maximum (15 rem), not its measured height, which leaves margin.
- **`Intl.DisplayNames`** is in all current browsers. `languageName` falls back to the upper-cased code.
- **Prettier formats the language files.** Key order follows the tables. Nothing depends on the order.

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: Czech interface from data files

Red runs (before any production code):

- E2E (`make assets` of the unchanged English app, then `make test-e2e`): 34 failed, 21 passed. The failing ones are
  the new `language.spec.ts` (4 tests: the page was English with `lang="en"`, no `/build/languages/` request, no
  texts in or reference from the bundle), the new `map-error.spec.ts` case (no Czech fallback in the HTML) and every
  existing spec rewritten to Czech (`layout`, `legend`, `map`, `no-parcel-data`, `parcel-detail`, `parcels`,
  `map-error`), which looked for Czech names against the English page (for example `getByRole('button', { name:
  'Přiblížit' })` timed out). That is the expected reason.
- Vitest (`npx vitest run`, tests written before the modules): 8 files failed. `Cannot find module '../src/texts'`,
  `'../src/language'`, `'../src/languageFiles'`, `'../src/documentTexts'` (texts, language, languageFiles,
  documentTexts tests and the shared `support/texts.ts`, which `legend`, `parcelFormat`, `indexHtml` import), and
  `landTypes.test.ts` failed because `LAND_TYPE_COLOURS` is undefined (`Cannot read properties of undefined
  (reading 'get')`).

What was done:

- Language files `cs.json` and `en.json` (69 keys each) were generated from the task file tables by a throw-away
  script and formatted by Prettier. New modules: `texts.ts`, `language.ts`, `languageFiles.ts` (JSON part),
  `documentTexts.ts`, `mapLabels.ts`, `mapNotice.ts`. `text.ts` and `landUses.ts` are removed; `LAND_TYPES` is now
  `LAND_TYPE_COLOURS`; legend, hint, panel and `parcelFormat` take `Texts`.
- `main.ts` fetches the texts and the configuration with `Promise.allSettled`. A failure of either shows the hidden
  `.map-error` paragraph of `index.html` (in the loaded language if the texts arrived). The page now throws at
  start-up when `.map-error` is missing, like it does for `#map`.
- `vite.config.ts` hashes files from `frontend/languages/` into `languages/[name]-[hash][extname]`.
- Replaced tests (every assertion kept, now in language-file tests):
  - `text.test.ts` became `texts.test.ts` (heading, unknown codes, hint, details notice, legend strings, English
    `map.error`/`notice.parcelsMissing` are asserted for both languages; the `UI_LOCALE` test went with the constant)
    plus `languageFiles.test.ts`.
  - `landUses.test.ts` (codes 1 to 30, names) is covered by `languageFiles.test.ts`, which pins all 30 land use and
    all 11 land type names in both languages (stronger than the six names pinned before).
  - `landTypes.test.ts`: name cases moved to `languageFiles.test.ts`; colour and fill-expression cases now use
    `LAND_TYPE_COLOURS`.
  - `legend.test.ts`, `parcelFormat.test.ts`: now take `Texts` and cover Czech and English.
- Changed existing E2E expectations (English to Czech default, Decision 15): `layout`, `legend` (also asserts the
  summary `Druh pozemku`), `map`, `map-error`, `no-parcel-data`, `parcel-detail`, `parcels` specs. In
  `parcel-detail.spec.ts` the area is now `13\u00a0077 m²`.
- `tests/Api/MapPageTest.php` (not listed in the task file) pins the page title; its expectation changed from `Jičín
  Parcel Map` to `Mapa parcel okresu Jičín` (Czech default, same Decision 15 reasoning). First `make check` failed on it.
- Decisions below the plan's level:
  - `MapNotice.setTexts` exists already in step 1 (stores the key), because lint rejects a class that has only a
    constructor. It is first exercised by E2E in step 2.
  - `languageFiles.ts` parses the code from the glob path with `/([^/]+)\.[a-z]+$/`, which step 2 reuses for flags.
  - `showError` takes the `.map-error` element looked up at start-up.

## Review

Filled in during the review loop (implement agent), one entry per round with findings.
