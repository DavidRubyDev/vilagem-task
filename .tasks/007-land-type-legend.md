# 007: Land type legend

- **Status:** done
- **Created:** 2026-11-23
- **Branch:** task/007-land-type-legend
- **Request:** "lets add a legend to the bottom left of the map. And let's shrink the side panel, so it does not
  colide or overlap". It was asked while task 006 was open; 006 was merged before planning finished, so this is a
  new task. On the 006 follow-ups (hardening the weak tests, adding the missing tests, a final review of the 006
  code): "separate refactor later. Push it down the list as nice to have".

  Answers to the plan's questions, verbatim: "Q2: hide it but don't move it. Q3: yes. Q4: yes. Q5: yes. Commits:
  yes... Approved". They mean:
  - Q2: the zoom hint stays at the bottom centre and is hidden while the parcel panel is open.
  - Q3: the legend has an "Unknown land type" row in the fallback grey.
  - Q4: the legend is collapsed by default up to 640 px width and open on wider screens.
  - Q5: only the panel's height shrinks; its width stays 20 rem.
  - Commits: three commits in the order of Steps.

## Goal

The map gets a land type legend in its bottom-left corner. It uses the colours and English names of the existing
frontend catalogue, and it is collapsible.

The side panel shrinks to its content. The panel, the legend, the zoom hint, the notices, the attribution and the
zoom buttons never overlap, from a 1280×720 desktop down to a 360×640 phone. The legend is static DOM and adds no
map listener, so it costs nothing while panning or zooming.

## Scope

**In scope:**

- The side panel fits its content, with a height cap and scrolling inside. The width is unchanged.
- The zoom hint is hidden while the panel is open. It stays at the bottom centre.
- The legend:
  - a MapLibre control in the bottom-left corner, built from `LAND_TYPES`;
  - codes 7 and 8 shown as one row, plus an "Unknown land type" row;
  - collapsible with `<details>`, collapsed by default on narrow screens.
- The layout rules that keep every map element apart, checked by E2E bounding-box tests at four viewports.
- README and CLAUDE.md (Glossary) updates.

**Out of scope:**

- **Follow-ups from task 006** (developer: "separate refactor later … nice to have"): hardening the weak tests,
  adding the missing tests, and a review of the final 006 code (review round 2 of 006 never ran). They become a
  later refactor task.
- A legend for land use, outlines or the selected parcel.
- Translations and a language switch; the new strings only go into `text.ts`.
- Moving the zoom hint (developer: "don't move it"), and a map position in the URL.
- Any backend change.
- Editing the `done` task files 001 to 006.

## Decisions

1. **The legend is a MapLibre control** (`LegendControl implements IControl`), added with
   `map.addControl(…, 'bottom-left')`.
   - MapLibre positions it in its control corner (10 px margins), next to the attribution and zoom-button corners.
   - It is static DOM. Collapsing uses the native `<details>` / `<summary>`, so there is no JavaScript listener and
     no map listener.
2. **Rows come from `LAND_TYPES`.**
   - Entries are de-duplicated by name and colour, in code order, keeping the position of the first code. Codes 7
     and 8 (same Czech name "trvalý travní porost", same colour) become one "Permanent grassland" row.
   - 10 rows, then "Unknown land type" in the fallback grey `#9e9e9e` (Q3). It is the only explanation of that grey,
     which is close to "Other area" (`#bdbdbd`).
   - Each swatch uses the map's fill opacity, a shared constant `PARCEL_FILL_OPACITY = 0.45`, so it looks like the
     map.
3. **When the legend shows.**
   - Whenever `parcels` is not `null`, at every zoom. No zoom listener is needed: parcels are drawn from zoom 9, and
     below that only the base map shows.
   - Also when parcel details are not imported, because the tiles are still drawn.
   - Not when there is no parcel data.
4. **Collapsed by default up to 640 px width, open above** (Q4). This uses
   `window.matchMedia('(max-width: 640px)').matches`, read once at start-up, with no listener. Open, the legend
   would cover about a third of a phone screen. The 640 px breakpoint is the panel's existing one
   (`fitPadding.ts`, `app.css`).
5. **The panel fits its content; the width stays 20 rem** (Q5).
   - `bottom: auto` and a `max-height`, with `overflow: auto` (scrolling inside).
   - Step 1 caps it at the viewport (`calc(100% - 1.5rem)`).
   - Step 3 reserves the legend's corner:
     - `calc(100% - 17.5rem)` above 800 px;
     - `calc(100% - 22rem)` up to 800 px, where the legend is lifted (Decision 7).
   - The narrow variant keeps its top anchoring with `right: 3.5rem`, so the zoom buttons stay free. Its old 50 %
     cap is replaced by the same rules.
   - `fitPadding` is unchanged: it already pads by the panel's rectangle.
6. **The zoom hint is hidden while the panel is open** (Q2), whatever the panel shows: loading, details, not found
   or error.
   - "Panel open" is used rather than "a parcel is selected" because a link to an unknown parcel opens the panel
     (not found) without a selection.
   - Panel and hint never show together, so they cannot collide.
   - The hint stays a bottom-centre pill at `bottom: 2.5rem`; its position is unchanged (developer: "don't move
     it").
7. **Narrow-window collision of hint and legend, resolved by stacking the bottom row.** This is within the
   developer's decision: the hint stays where it is.
   - **The problem** (measured, Verified facts): the hint (188 px) and an open legend (193 px, up to 14 rem) cannot
     sit side by side below about 660 px width. On phones even the closed legend reaches the hint.
   - **Up to 800 px width**, the bottom-left control corner is lifted above the hint's row:
     `.maplibregl-ctrl-bottom-left { bottom: 4.75rem }`.
     - The hint occupies 2.5 rem to about 4.3 rem from the bottom; the legend starts at 4.75 rem + 10 px.
     - 800 px rather than 660 px leaves room for font differences.
   - **The hint gets `white-space: nowrap`.** At 360 px it otherwise wraps to two lines (48 px tall) and reaches
     the lifted legend. On one line it is 188 px, which fits down to 320 px.
   - **The attribution** (bottom-right, 20 px tall) is below both, so nothing touches it.
   - **Cost on phones and small windows** (≤ 800 px):
     - The legend floats about 86 px above the bottom edge, even while the hint is hidden. It stays static, so it
       never jumps.
     - The panel's cap reserves room for the *open* legend even when it is closed, so on small screens the panel
       scrolls sooner:
       - 360×640: 288 of 335 px visible;
       - 641×480: 128 of 295 px visible.
     - Above 800 px nothing is lifted.
   - **Rejected alternatives:**
     - moving the hint (the developer said not to);
     - hiding the hint while the legend is open (couples two unrelated controls and needs a `toggle` listener);
     - reserving space only while the legend is open via CSS `:has()` (the panel would change height when the
       legend is toggled, and every state would need its own tests).
8. **No overlap is a tested property.**
   - The E2E bounding-box test runs at 1280×720, 641×480, 390×844 and 360×640.
   - Each viewport covers four states: panel open with details, panel open with not found, default view with the
     hint, and details not imported with the notice. The legend is opened first.
   - Checked elements: panel, legend, attribution, zoom buttons, hint, notice. Each visible one must lie inside the
     viewport, and no two may intersect (touching is allowed).
9. **English strings in `text.ts`**: `legendTitle: 'Land type'` and `legendUnknown: 'Unknown land type'`, ready for
   the later translation work (task 006 Decision 10).
10. **No logging**, as in task 006 Decision 15: the legend adds no output.

## Verified facts

Verified on 2026-11-23 with headless Chromium (`mcr.microsoft.com/playwright:v1.65.1-noble`) against the running
dev app with the 006 frontend. `/api/map-config` and `/api/parcels/…` were stubbed in the browser and OSM was
blocked. A mock legend was inserted into `.maplibregl-ctrl-bottom-left`: 11 rows, `font: 0.8rem/1.3 system-ui`,
padding `0.4rem 0.6rem`, 0.9 rem swatches. The scripts are not committed.

**Today's layout** (task 006). Boxes are `[left, top, right, bottom]` in px.

| Element | 1280×720 | 641×480 | 390×844 | 360×640 |
|---|---|---|---|---|
| Attribution (bottom-right, never compact) | 1031, 700, 1280, 720 | 392, 460, 641, 480 | 141, 824, 390, 844 | 111, 620, 360, 640 |
| Zoom buttons | 1241, 10, 1270, 68 | 602, 10, 631, 68 | 351, 10, 380, 68 | 321, 10, 350, 68 |
| Zoom hint (zoom 12) | 546, 651, 734, 680 | 227, 411, 414, 440 | 101, 775, 289, 804 | wraps to 2 lines, 48 px tall |
| Panel, today | 12, 12, 332, 708 (full height) | 12, 12, 332, 468 | 12, 12, 334, 267 | 12, 12, 304, 287 |
| Panel content alone | 295 px tall | 295 | 275 | 320–335 |

**Legend (mock).**

- 193 × 217 px open (13.6 rem), 87 × 29 px closed. The longest name is "Built-up area and courtyard".
- An open legend in the corner overlaps the attribution at 390 px (x 141–203, y 824–834) and the hint.
- A closed legend ends 1 px from the hint at 390 and 360 px.

**Existing collision in 006.** Between about 641 and 860 px width, the full-height panel and the bottom-centre hint
overlap when a parcel is selected and the map is zoomed out below 14. At 641 px: hint x 226–414, panel x 12–332.

**Proposed layout, measured with injected CSS.** The panel uses `bottom: auto` and the caps of Decision 5; up to
800 px the corner is lifted by 4.75 rem; the hint is hidden while the panel is open and has `white-space: nowrap`.

- No overlap in any combination of:
  - 1280×720, 641×480, 390×844, 360×640, 320×568;
  - default view (hint) or `#parcel=…` (panel);
  - legend open or closed.
- Sample boxes:
  - 1280×720: legend open 10, 493, 201, 710; panel 12, 12, 332, 307.
  - 360×640:
    - legend open 10, 337, 201, 554;
    - hint 86, 571, 274, 600;
    - attribution 111, 620, 360, 640;
    - panel 12, 12, 304, 300, scrolling 288 of 335 px.
  - 641×480: panel 128 of 295 px visible.

**MapLibre 6.19.0** (`node_modules/maplibre-gl/dist/maplibre-gl.d.ts`, `maplibre-gl.css`)

- `IControl` has `onAdd(map): HTMLElement`, `onRemove(map): void` and an optional `getDefaultPosition`.
- The control corners are absolutely positioned. `.maplibregl-ctrl-bottom-left` is at `bottom: 0; left: 0`, and
  controls inside get `margin: 0 0 10px 10px`.
- The attribution control is `.maplibregl-ctrl-attrib`. The app creates it with `compact: false`, so it never
  collapses.

## Steps

The first commit on the branch is this task file (`docs(tasks): add 007 Land type legend`). Each step is one
commit, and every commit passes `make check`. Write the E2E test of each step first and watch it fail for the right
reason.

1. **The panel fits its content** (`feat(map): fit the parcel panel to its content (#007)`).
   - The panel is as tall as its content, at most the viewport minus margins, and scrolls inside.
   - Adds the no-overlap E2E helper. With it: panel, attribution and zoom buttons at the four viewports, panel open
     from `/#parcel=1753295604`.
2. **The hint hides while the panel is open** (`feat(map): hide the zoom hint while the parcel panel is open (#007)`).
   - This includes the not-found panel of a link.
   - Adds the pure `zoomHintVisible`, and the panel tells the hint when it opens or closes.
3. **Land type legend** (`feat(map): show a land type legend (#007)`).
   - Adds the legend control and its CSS, the legend-aware panel caps, the lifted corner up to 800 px, and the
     non-wrapping hint.
   - Extends the no-overlap E2E to all elements and states.
   - Also README and the Glossary.

## Architecture

Frontend only. Paths and public names are binding; private helpers and test code are left to the implementation.

### Domain / Application / Infrastructure / Handlers / Commands

No change.

### Frontend

**`frontend/src/mapOptions.ts`** (step 3): `export const PARCEL_FILL_OPACITY = 0.45`, used by the `parcels-fill`
layer (no change in value) and by the legend swatches.

**`frontend/src/landTypes.ts`** (step 3): `export` the existing `FALLBACK_COLOUR` (`#9e9e9e`) and the
`LandTypeStyle` interface. Nothing else changes.

**`frontend/src/text.ts`** (step 3): add `legendTitle: 'Land type'` and `legendUnknown: 'Unknown land type'`.

**`frontend/src/legend.ts`** (step 3, new)

```ts
export interface LegendEntry {
  readonly name: string;
  readonly colour: string;
}

/** The rows of the legend: LAND_TYPES de-duplicated by name and colour, in code order, then the unknown fallback. */
export function legendEntries(): readonly LegendEntry[];

/** The land type key in the bottom-left corner of the map. Static: no map or DOM listeners. */
export class LegendControl implements IControl {
  constructor(open: boolean);
  onAdd(map: MapLibreMap): HTMLElement;
  onRemove(map: MapLibreMap): void;
}
```

`onAdd` builds this DOM once, with texts set by `textContent`; `onRemove` removes it:

```html
<details class="maplibregl-ctrl map-legend" open>        <!-- open only when constructed with open = true -->
  <summary>Land type</summary>
  <ul class="map-legend-entries">
    <li><span class="map-legend-swatch" aria-hidden="true" style="background-color: #f2d16b; opacity: 0.45"></span>Arable land</li>
    …                                                       <!-- 10 land type rows -->
    <li><span class="map-legend-swatch" aria-hidden="true" style="background-color: #9e9e9e; opacity: 0.45"></span>Unknown land type</li>
  </ul>
</details>
```

The swatch opacity comes from `PARCEL_FILL_OPACITY`, set as an inline style.

**`frontend/src/zoomHint.ts`** (step 2)

- New pure `export function zoomHintVisible(zoom: number, panelOpen: boolean): boolean`. It is true only when
  `zoom < COMPLETE_PARCELS_MIN_ZOOM` and the panel is closed.
- `showZoomHint(map: MapLibreMap, isPanelOpen: () => boolean): { update(): void }`:
  - sets `hint.hidden = !zoomHintVisible(map.getZoom(), isPanelOpen())`;
  - runs on `load` and `zoomend` as today, and whenever `update()` is called;
  - adds no other listener.

**`frontend/src/parcelPanel.ts`** (step 2): the constructor becomes
`constructor(onClose: () => void, onVisibilityChange: () => void)`.

- `onVisibilityChange` is called after every change of `hidden`: from hidden to shown in `showLoading`,
  `showDetail`, `showNotFound` and `showError`, and from shown to hidden in `hide`.
- `isOpen()` is unchanged.

**`frontend/src/main.ts`**

- Step 2:
  - Creates the panel with an `onVisibilityChange` that calls `hint.update()`. It uses the same closure pattern as
    `onClose`: a variable assigned once the hint exists, because the panel is created before the map.
  - Creates the hint with `showZoomHint(map, () => panel.isOpen())`.
  - The panel of a link (loading or not found) is already open when the hint is created, so the hint's first
    `update()` hides it.
- Step 3: when `parcels !== null`, in both branches (details available, details not imported), it adds
  `map.addControl(new LegendControl(!window.matchMedia('(max-width: 640px)').matches), 'bottom-left')`. With
  `parcels === null` there is no legend.

**`frontend/src/app.css`**

- **Step 1, `.parcel-panel`:**
  - replace `bottom: 0.75rem` with `bottom: auto`;
  - add `max-height: calc(100% - 1.5rem)` (`overflow: auto` stays);
  - in `@media (max-width: 640px)`, replace `max-height: 50%` with `max-height: calc(100% - 1.5rem)`. On narrow
    screens the panel is content-sized too: measured 275–335 px.
- **Step 3:**
  - `.parcel-panel { max-height: calc(100% - 17.5rem) }`, and in `@media (max-width: 800px)`
    `.parcel-panel { max-height: calc(100% - 22rem) }`. The 640 px block no longer sets `max-height`.
  - Comment: room for the open legend (≤ 15 rem) in its corner, plus margins, lifted on small windows.
  - `@media (max-width: 800px) { .maplibregl-ctrl-bottom-left { bottom: 4.75rem } }`, with a comment that it lifts
    the legend above the zoom hint's row (Decision 7).
  - `.map-hint { white-space: nowrap }`.
  - `.map-legend`:
    - `max-width: 14rem`, `max-height: 15rem`, `overflow: auto`, `box-sizing: border-box`;
    - padding `0.4rem 0.6rem`, `font: 0.8rem/1.3 system-ui, sans-serif`;
    - background `rgb(255 255 255 / 0.92)`, `border-radius: 6px`, the panel's shadow.
  - `summary`: bold, `cursor: pointer`.
  - `.map-legend-entries`: no list style, margin-top 0.3 rem, no padding.
  - `li`: flex, gap 0.4 rem, centred.
  - `.map-legend-swatch`: 0.9 rem square, `flex: none`, `border: 1px solid #3d3d3d`.

### Wiring

No Bootstrap change.

## Test scenarios

### Unit (Vitest)

- **Step 2, `zoomHint.test.ts`:** `zoomHintVisible` at zoom 12 with the panel closed → true; zoom 12 with the panel
  open → false; zoom 14 and 18 → false either way.
- **Step 3, `legend.test.ts`:**
  - 11 entries;
  - the names in order: Arable land, Hop field, Vineyard, Garden, Orchard, Permanent grassland, Forest land, Water
    area, Built-up area and courtyard, Other area, Unknown land type;
  - "Permanent grassland" once;
  - every colour equals the colour of its code in `LAND_TYPES`;
  - the last entry is `#9e9e9e`.
- **Step 3, `mapOptions.test.ts`:** the `parcels-fill` opacity equals `PARCEL_FILL_OPACITY` (0.45).

### Integration / API

None (no backend change).

### E2E (Playwright)

**Helper** `tests/E2E/support/layout.ts` (step 1): `expectNoOverlap(page: Page, selectors: readonly string[])`.

- It takes the bounding box of every matching *visible* element.
- It asserts each lies inside the viewport and that no two intersect with positive width and height (touching is
  allowed).
- The failure message names both elements and their boxes.

**Viewports:** 1280×720 (project default), 641×480, 390×844, 360×640 (`test.use({ viewport })` per `describe`).

**Element selectors:** `.parcel-panel`, `.map-legend`, `.maplibregl-ctrl-attrib`,
`.maplibregl-ctrl-top-right .maplibregl-ctrl-group`, `.map-hint`, `.map-notice`.

**Gotcha:** select the legend toggle with `.map-legend summary`, not by the text "Land type", which is also a row
label in the panel.

- **Step 1, new `tests/E2E/layout.spec.ts`:** at each viewport, open `/#parcel=1753295604` and wait for the panel
  heading `Parcel st. 72`.
  - The panel, attribution and zoom buttons do not overlap.
  - The panel's height is less than the viewport height minus 24 px.
  - At 1280×720, the panel bottom is above 400 px (content-sized, not full height).
- **Step 2, `parcel-detail.spec.ts`:**
  - `/#parcel=999` (not found, default zoom 12): the hint is hidden while the not-found panel is open. After the
    close button, the hint is visible. This needs `allowedConsoleErrors: [/status of 404/]`.
  - The existing hint tests keep passing (visible at zoom 12, hidden at zoom 18).
- **Step 3, new `tests/E2E/legend.spec.ts`:**
  - At 1280×720 the legend is visible and open, at the bottom left: its left edge within 20 px of 0, its bottom
    within 20 px of the viewport bottom. It lists the 11 names, and "Permanent grassland" appears once.
  - At 390×844 the legend starts closed: the summary is visible and the entries are not. Clicking
    `.map-legend summary` opens it.
  - The legend is visible when `parcelUrlTemplate` is `null` (rewritten map-config).
  - `no-parcel-data.spec.ts` (`parcels: null`) additionally asserts that there is no `.map-legend`.
  - No console errors (fixture).
- **Step 3, `layout.spec.ts` extended:** at each of the four viewports, open the legend first if it is closed, then
  check all selectors with `expectNoOverlap` in each state:
  - (a) `/#parcel=1753295604`, panel with details;
  - (b) `/#parcel=999`, not-found panel (`allowedConsoleErrors` for the 404);
  - (c) `/`, default view, hint visible;
  - (d) `/` with `parcelUrlTemplate: null`, notice and legend, no hint.

## Acceptance criteria

- [ ] The legend sits in the bottom-left corner: open on a 1280×720 window, collapsed on a phone-sized one, and
      opening on a click on "Land type". It shows 10 land types and "Unknown land type", with swatches that match
      the map fill.
- [ ] The panel is only as tall as its content and scrolls inside when space runs out. Its width is unchanged
      (20 rem).
- [ ] The zoom hint is never visible while the panel is open, and stays at the bottom centre.
- [ ] No element overlaps another at 1280×720, 641×480, 390×844 and 360×640 in the four states (E2E).
- [ ] Smoothness:
  - the legend adds no map listener and no DOM listener (code review);
  - the hint still updates only on `load`, `zoomend` and panel open/close;
  - the parcel layers are unchanged (fill opacity 0.45).
- [ ] Manual check in a real browser, recorded in the implementation log (mark "NOT done" if not possible): the
      legend's colours look like the map at zoom 12 over Jičín; nothing overlaps when the window is resized from
      wide to narrow.
- [ ] `make check` passes at every commit (`git rebase --exec "make check" main`).
- [ ] README and the CLAUDE.md Glossary are updated as listed below.

## Documentation updates per commit

- **Step 1:** none. README text on the panel does not mention its size.
- **Step 2:** README Running: in the sentence about the hint, add "while no parcel panel is open". It currently
  ends "…a hint at the bottom says "Zoom in to select a parcel." until zoom 14".
- **Step 3:**
  - README:
    - Running: the land type legend in the bottom-left corner, collapsed on narrow screens.
    - Notes > Decisions: the legend is a static MapLibre control built from the frontend catalogue.
    - Notes > Decisions: on windows up to 800 px the legend is lifted above the zoom hint so nothing overlaps.
  - CLAUDE.md Glossary, new row:
    `| — | Legend | The land type key in the bottom-left corner of the map (LegendControl), built from the frontend land type catalogue. |`

## Risks and assumptions

- **Fonts differ across systems.** The measurements are from the Playwright image. The legend's cap
  (`max-height: 15rem`, `max-width: 14rem`), the 800 px breakpoint and the non-wrapping hint leave margin. The E2E
  test catches a regression in the test browser only, so the manual check covers real browsers.
- **Smaller panel on small windows.** The panel reserves room for the open legend even when it is closed (Decision
  7), so on small windows it scrolls sooner (641×480: 128 px visible). This was accepted to keep the layout static.
- **The lifted legend on phones** floats about 86 px above the bottom edge, even when the hint is hidden.
- **The `<details>` element** is used for collapsing; it is supported by all current browsers and needs no
  JavaScript.
- **Follow-ups from 006** (test hardening, a final review) are deferred, so weaknesses there remain until that
  refactor task.

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: the panel fits its content

- Added `tests/E2E/support/layout.ts` (`expectNoOverlap`) and `tests/E2E/layout.spec.ts` (four viewports, panel open from `/#parcel=1753295604`; panel, attribution and zoom buttons do not overlap; panel height < viewport - 24 px; at 1280 wide the panel height is below 388 px).
- Red run (E2E, before the CSS change): `at 1280x720 > the open parcel panel fits its content and overlaps nothing` failed on `toBeLessThan(height - 24)` (panel was full height, 696 px); `at 641x480 > ...` failed with `Expected: < 456, Received: 456`. The 390x844 and 360x640 cases passed already, because the narrow variant was top-anchored and content-sized before this task (expected; they guard the new `max-height` rule).
- CSS: `.parcel-panel` gets `bottom: auto` and `max-height: calc(100% - 1.5rem)`; the 640 px block loses `bottom: auto` (now inherited) and the 50 % cap. Green after: 34 E2E tests pass.
- Decision below the plan: the 1280 assertion uses `height < 388` (400 - the 12 px top offset) so the bottom edge is above 400 px.

### Step 2: the hint hides while the panel is open

- Tests first: `frontend/tests/zoomHint.test.ts` (3 tests) and the E2E `hides the hint while the not-found panel of a link is open, and shows it again after closing` in `parcel-detail.spec.ts`.
- Red runs: Vitest, all 3 `zoomHintVisible` tests failed with `TypeError: zoomHintVisible is not a function`. E2E: the new test failed at `expect(getByText(HINT)).toBeHidden()` with `unexpected value "visible"` (the hint stayed visible beside the not-found panel).
- Implemented `zoomHintVisible`, `showZoomHint(map, isPanelOpen)` returning `{ update }`, `ParcelPanel` `onVisibilityChange`, and the `main.ts` wiring (`let hint` assigned after the map exists, same closure pattern as `selection`). README sentence updated.
- Below the plan's level: `ParcelPanel` calls `onVisibilityChange` only when `hidden` actually changes (a `setHidden` helper), so `showDetail` after `showLoading` does not trigger a redundant update.

### Step 3: land type legend

- Tests first: `legend.test.ts` (5), a `mapOptions.test.ts` test for `PARCEL_FILL_OPACITY`, a `text.test.ts` test for the legend strings, `tests/E2E/legend.spec.ts` (3 tests), the `.map-legend` count assertion in `no-parcel-data.spec.ts`, and `layout.spec.ts` extended to all elements and four states at four viewports (16 tests; the legend is opened first).
- Red runs: Vitest: `legend.test.ts` failed with `Cannot find module '../src/legend'`; `shares the fill opacity with the legend swatches` failed with `expected undefined to be 0.45` (no export); `words the legend` failed with `expected undefined to be 'Land type'`. E2E: 19 failed, 31 passed: all 16 `layout.spec.ts` tests and all 3 `legend.spec.ts` tests failed at `expect(locator).toBeVisible()` because there was no `.map-legend`.
- Could not be made red: the new `.map-legend` `toHaveCount(0)` assertion in `no-parcel-data.spec.ts` passes before and after, as the legend did not exist before; it guards `parcels === null` against a future regression.
- Green after: all 136 Vitest tests and 50 E2E tests passed on the first implementation run (no CSS iteration needed; the no-overlap checks pass at all four viewports in all four states).
- Below the plan's level: in `main.ts` a small `newLegend()` helper reads the media query once and is used by both branches; the `parcels === null` branch now keeps the `Map` in a local to add the legend only when `parcels !== null`. Since that branch is reached with `parcels !== null` only when details are not imported, the check is explicit rather than implied.
- `LegendControl.onAdd`/`onRemove` declare no parameters (ESLint rejects unused `_map`; a shorter signature still implements `IControl`).
- Manual check in a real browser: NOT done (headless Chromium only, through the E2E tests).

## Review

Filled in during the review loop (implement agent), one entry per round with findings.

### Round 1: head 4b2e329, 2026-11-23

No findings.
