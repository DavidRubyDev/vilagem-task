# 009: Search bar

- **Status:** in-progress
- **Created:** 2026-11-24
- **Branch:** task/009-search-bar
- **Request:** "lets plan another task. A search bar. It should search by the real location as maps do. But also by the
  code of the parcel"

  Answers to the plan's questions, verbatim: "Q1: What would it take to make it a full map search. Outside our data it
  would only search the addresses. Not our data. Q2 - Q5: as recommended. Q6: keep it that way. Make it separate from
  the side panel. But same width and style with a space between them. Q7 - Q8: as recommended". After the options for
  a full map search, verbatim: "lets have the whole republic search out of the scope and keep only the original Jicin
  area". On the gap between the search box and the panel, and approval, verbatim: "12 px is fine, approved".

  They mean:
  - Q1: the search covers the covered area (the Jičín district) only, from an offline index built by the import from
    the RÚIAN exports that are already downloaded. A Czech Republic-wide address search and an external geocoder are
    out of scope (possible later tasks, see Out of scope).
  - Q2: five kinds of places are searched: municipalities, cadastral areas, parts of municipalities, streets and
    addresses.
  - Q3: picking an address selects the parcel its building stands on (panel, highlight, hash, fit). Picking any other
    place moves the map to it and closes an open parcel panel.
  - Q4: equal matches are ordered by distance from the map centre.
  - Q5: the parcel number grammar of Decision 6: "72" finds land 72 and building st. 72; "st." means building plots
    only; numbers match exactly; a 6-digit token narrows to that cadastral area code; no search by RÚIAN parcel id.
  - Q6: the search box is at the top left on every width. It is a separate element, not part of the panel, with the
    panel's width and style. The panel sits below it with a 12 px gap. Between 641 and 800 px the bottom-left corner
    loses its lift while the panel is open (Decision 13).
  - Q7: the import may take about 30 s longer.
  - Q8: the five steps below.

## Goal

A search box at the top left finds, as you type, places and parcels of the covered area: municipalities, cadastral
areas, parts of municipalities, streets and addresses ("Valdštejnovo náměstí 58", "Robousy 239"), and parcels by their
number as people write it ("st. 72", "80/2", "867/121 Robousy"). Matching ignores case and diacritics ("jicin" finds
Jičín). Picking a parcel selects it exactly like a link does; picking an address selects the parcel its building stands
on; picking another place moves the map there. Everything is answered by a **search index** that the import builds
from the RÚIAN data it already downloads and publishes with the tileset, so no query leaves the server, and typing
costs no map work.

## Scope

**In scope:**

- Import: a search index per tileset version (`data/search/{version}.sqlite`): parcel numbers (step 1), places
  (step 4); a place converter (one GDAL run per municipality); the publisher publishes three files atomically.
- Domain `ParcelMap\Domain\Search`: `SearchText`, `SearchQuery`, `ParcelNumberQuery`, `ParcelMatch`, `Place`,
  `PlaceKind`, `Address`, `HouseNumber`, `HouseNumberKind`, `DistinctPlaces`, `SearchIndexRepository`.
- `GET /api/search/{version}?q=…&near=lon,lat`; `searchUrl` in `/api/map-config`; query parameters on `Request`.
- Frontend: the search box (ARIA combobox, debounce, keyboard, results grouped into places and parcels), picking, the
  layout change (search box, panel below it, caps, the 641–800 px rule), texts in `cs.json` and `en.json`.
- Fixtures: the Jičín fixture zip gains place layers (step 4); the fixture data dir is regenerated (steps 2 and 4).
- `make perf` gains a search latency spec.
- README, `tests/Fixtures/README.md`, CLAUDE.md Glossary.

**Out of scope:**

- **Search of the whole Czech Republic** (developer's decision on Q1; a possible later task). Notes from planning:
  - Source: the ČÚZK address CSV `https://vdp.cuzk.gov.cz/vymenny_format/csv/<date>_OB_ADR_csv.zip`: 63.4 MB zipped,
    354.7 MB unpacked, 6,258 per-municipality CSVs (cp1250, `;`, 19 columns, S-JTSK Y/X), about 2.95 million address
    points, CC BY 4.0, published monthly with the same date as the municipality exports. ST_UADS (91.4 MB) has no
    coordinates in its documented structure.
  - Measured: 3 million points S-JTSK → WGS84 with ogr2ogr 11.9 s; a synthetic index of 2.97 million places built in
    18 s, 369 MB; p95 per query at most 24.9 ms when addresses sit in their own full-text table and are searched only
    when the query contains a number. Estimated import +50 to 70 s, about 370 MB per index version.
  - Outside the coverage it would find municipalities, parts, streets and addresses only, move the map there (OSM
    only), and tag such results "outside the covered area".
- **An external geocoder and points of interest** (a possible later task). It needs an exception to the CLAUDE.md
  data rule, and query text leaves the server. Nominatim forbids autocomplete; Mapy.com needs an API key, credits and
  its branding; the public Photon instance gives no guarantee; a self-hosted Czech Photon needs a Java 21 service and
  its dump (1 GB) dates from 2025-07-21; worldwide Photon needs about 95 GB of disk. If chosen later: an Application
  port `PlaceGeocoder` with an HTTP adapter, called by the backend only, with a short timeout and local fallback.
- Postcodes, search by RÚIAN parcel id, prefix matching of parcel numbers ("80" does not suggest "80/2").
- Places or the map position in the URL; search history; typo tolerance (no fuzzy matching).
- A notice when the search index is missing (Decision 2): the search box is simply not shown.
- A marker for a picked place.
- Speeding up the existing parcel conversion with the multi-layer GDAL run.
- Editing the `done` task files 001 to 008.

## Decisions

1. **Offline index, covered area only** (Q1). The data rule of CLAUDE.md (downloaded ahead of time, preprocessed),
   privacy (queries stay on our server), tests without network, and latency (4 ms) decide it. A query about anything
   outside the district finds nothing and the list says so.
2. **A new versioned file, the search index: `data/search/{version}.sqlite`.**
   - Built by the import from the converted parcels (step 1) and the converted places (step 4), published with the
     tileset and the parcel store under the same version.
   - The parcel store is not changed. The index has its own slim `parcels` table, so search depends on nothing else.
   - Search is available exactly when the file of the current version exists (`is_file`, as the parcel store in 006).
     Data imported before this task has no index: `/api/map-config` sends `searchUrl: null` and the search box is not
     shown. No notice: a notice at the top centre would overlap the panel at 641 px, and the state ends with the next
     `make data`.
3. **Publication** (extends task 006 Decision 2). The tileset rename stays the single commit point. The publisher
   places the store and then the index under `{version}.sqlite`, renames the tileset, removes both placed files if
   that rename fails, and afterwards prunes both directories to the new and the previously live version.
4. **Two GDAL passes per municipality.** The existing parcel conversion stays as it is. A new `PlaceConverter` runs
   `ogr2ogr` once per municipality and writes the four layers `CastiObci`, `Ulice`, `StavebniObjekty`, `AdresniMista`
   into one SQLite file (`-oo READ_MODE=SEQUENTIAL_LAYERS`, layers in file order, `-lco FORMAT=WKT`): 26.6 s for the
   district. **Joins are done in SQL by the index builder (`ATTACH`), never by GDAL**: GDAL joins over the GML files
   are unusable (Verified facts).
5. **What gets indexed** (step 4).
   - An address is indexed only when its building's identifying parcel (`IdentifikacniParcelaId`) is in the index's
     `parcels` table, i.e. covered; its result carries that parcel id. Addresses without a point are skipped.
   - A part of a municipality or a street is indexed only when at least one of its addresses is indexed. Bounds: a
     street from its line geometry; a part from its indexed address points.
   - A cadastral area's bounds are the union of its parcels' bounds; a municipality's bounds are the union of its
     covered areas' bounds.
   - **One place per name**: within one municipality, a municipality, a cadastral area and a part with the same
     `SearchText` key are one place to a user. Only the first in the order municipality, cadastral area, part is kept.
     "Jičín" gives the municipality only (not also the cadastral area Jičín); "Robousy" gives the cadastral area (real
     bounds), not the part (points only).
   - Context: a municipality has none; a cadastral area, a part and a street have their municipality's name; an
     address has its municipality's name, prefixed by "<part>, " when it has a street and the part's key differs from
     the municipality's key ("Staré Město, Jičín").
6. **Query grammar** (`SearchQuery`, Q5).
   - The text is normalised by `SearchText` (lower case, diacritics removed, everything except `a-z`, `0-9` and `/`
     becomes a space).
   - The first token matching `^[0-9]{1,5}(/[0-9]{1,4})?$` with base ≥ 1 and subdivision ≥ 1 is the **parcel
     number**. A token `st` directly before it restricts the search to building plots and is not a word.
   - The first token matching `^[0-9]{6}$` that is a valid `CadastralAreaCode` narrows the parcels to that area.
   - The tokens `pc` and `parc` and every single-letter alphabetic token (`p`, `c` from "p. č.", "č.p.") are ignored.
   - **Parcels** are searched when there is a parcel number. The number matches exactly (base and subdivision; no
     subdivision means none). Without `st` both kinds match. Every remaining word must be the start of a word of the
     cadastral area's name or its municipality's name.
   - **Places** are searched (step 4) when a word of at least two characters containing a letter remains. Every
     remaining token, split at `/`, must be the start of a word of the place's name or context (full-text prefix
     match): "husova 1" finds "Husova 1", "Husova 127".
   - Queries are 1 to 100 characters after trimming; otherwise 400.
7. **Ranking and limits.**
   - Places: exact key first, then key prefix, then kind (municipality, cadastral area, part, street, address), then
     distance from `near`, then key, then id.
   - Parcels: distance from `near`, then land before building, then area name, then id.
   - Distance is the squared distance between centres with longitude scaled by cos²(latitude of `near`). Without
     `near` the distance term is left out.
   - At most 8 places and 8 parcels per response.
8. **Labels follow Czech address notation and are not translated** (like `st.` in task 006 Decision 10): `Husova 624`,
   `Husova 624/3`, `Husova 624/3a`, `Husova č. ev. 12`, and without a street `Robousy 239`, `Robousy č. ev. 5`. The
   backend stays language-neutral; kind names are translated in the frontend.
9. **API.** `GET /api/search/{version}?q=…&near=lon,lat`, versioned and cached like the parcel details
   (`Cache-Control: public, max-age=31536000, immutable`); a stale page gets 404 and is told to reload. `near` is the
   map centre.
10. **Search as you type.** A request starts 200 ms after the last keystroke, when the trimmed text has at least two
    characters or contains a digit. A new request aborts the one in flight; the last one wins. While a request is
    pending, the results shown so far stay.
11. **Picking** (Q3).
    - A parcel → `ParcelSelection.select(id, { fit: true })`: panel, highlight, `#parcel=`, fit, exactly as a
      `hashchange` does.
    - An address → the same with its `parcelId`. (Every indexed address has one; a result without one is treated as a
      place without bounds.)
    - Another place → the parcel selection is cleared (panel closes, hash removed), then the map is fitted to the
      place's bounds (`maxZoom` 17, padding clear of the search box) or, without bounds, eased to its centre at zoom 17.
    - After a pick the input shows the result's name, the list closes and the input loses focus (the phone keyboard
      goes away).
12. **Smoothness.** No map listener is added. The map centre is read with `map.getCenter()` only when a request is
    sent. Typing changes only the search box's DOM; a pick makes one camera move (`fitBounds`, `easeTo` or the
    selection's fit). The list has at most 16 options plus two headings.
13. **Layout** (Q6, measured, Verified facts).
    - The search box is a separate element appended to `body` (like the panel), at `top: 0.75rem; left: 0.75rem`,
      20 rem wide, 2.75 rem (44 px) high, with the panel's white background, 6 px radius, shadow and font. Up to
      640 px wide it stretches to `right: 3.5rem` (the zoom buttons stay free).
    - The panel moves down to `top: 4.25rem`: the box, then a **12 px gap** (0.75 rem, the outer margin).
    - Panel height caps: above 800 px `calc(100% - 23.25rem)`; 641–800 px `calc(100% - 23.25rem)`; up to 640 px
      `calc(100% - 27.75rem)`.
    - **641–800 px:** while the panel is open, the bottom-left corner (legend and language switcher) is not lifted
      (`bottom: 0` instead of `4.75rem`). The lift exists for the zoom hint row, and the hint is hidden while the panel
      is open (task 007). A body class `parcel-panel-open` follows the panel's visibility. Up to 640 px the lift stays,
      because it also keeps the open legend off the attribution there (measured).
    - Visible panel (st. 72, legend open): 1280×720 the whole content (269 px); 641×480 about 108 px (92 today; 40
      with the lift kept); 390×844 the whole content; 360×640 about 196 px (252 today; it scrolls today too).
    - The results list hangs below the input inside the search box, overlays the map and the panel while open
      (`z-index` above the panel), and stays inside the viewport.
14. **Accessibility.** The ARIA 1.2 combobox pattern: the input has `role="combobox"`, `aria-expanded`,
    `aria-controls`, `aria-autocomplete="list"` and `aria-activedescendant`; the list is a `listbox` with one `group`
    per kind of result. Arrow Down/Up move the active option (wrapping), Enter picks the active option (or the first),
    Escape with the list open closes it and stops the event (the panel stays open); with the list closed Escape is not
    handled by the box (the panel closes as today). A `role="status"` line reports searching, no results and errors.
15. **Texts** in `frontend/languages/cs.json` and `en.json` (keys under Frontend); a language switch re-renders the box
    and an open list in place.
16. **Fixtures.** The fixture data dir is regenerated in step 2 (the index appears) and in step 4 (places). The Jičín
    fixture zip is re-trimmed in step 4 from the cached real export; its parcels stay byte for byte the same, so the
    tiles, bounds and parcel facts do not change.
17. **No logging** beyond the existing pattern (task 006 Decision 15): build warnings go to stderr, the frontend logs
    failed searches with `console.warn`.

## Verified facts

Verified on 2026-11-24 against `main` at f09161e in throwaway containers of the `parcel-map-app` image (PHP 8.6.0RC7,
SQLite 3.46.1, GDAL 3.10.3) and the Playwright image, unless noted. Scripts are not committed.

**SQLite in the app image**

- `PRAGMA compile_options` lists `ENABLE_FTS5`. `CREATE VIRTUAL TABLE t USING fts5(a, tokenize="unicode61
  remove_diacritics 2")` works, and `MATCH 'jicin'` finds "Jičín Nová Ulice".
- No `intl` extension (`php -m`), so the normalisation is an explicit character map in PHP.
- `DETACH` inside an open transaction fails ("database m is locked"): attach, begin, commit, then detach.

**RÚIAN municipality export** (`data/cache/ruian/20261031_OB_572659_UKSH.xml.zip`, GML driver)

- Layers: `Obce`, `SpravniObvody`, `Mop`, `Momc`, `CastiObci`, `KatastralniUzemi`, `Zsj`, `Ulice`, `Parcely`,
  `StavebniObjekty`, `AdresniMista`. In the XML the collections come in the order `vf:Obce`, `vf:CastiObci`,
  `vf:KatastralniUzemi`, `vf:Zsj`, `vf:Ulice`, `vf:Parcely`, `vf:StavebniObjekty`, `vf:AdresniMista`.
- Element ids: `vf:CastObce gml:id="CO.<code>"`, `vf:Ulice gml:id="UL.<code>"` (inside the collection `vf:Ulice`, same
  tag name), `vf:StavebniObjekt gml:id="SO.<code>"`, `vf:AdresniMisto gml:id="AD.<code>"`.
- `AdresniMista` fields: `Kod`, `CisloDomovni`, `CisloOrientacni`, `CisloOrientacniPismeno`, `Psc`,
  `StavebniObjektKod`, `UliceKod`, geometry `AdresniBod` (Point; also `Zachranka`, `Hasici`). No part of municipality.
- `StavebniObjekty` fields used: `Kod`, `TypStavebnihoObjektuKod`, `CastObceKod`, `IdentifikacniParcelaId`.
- `Ulice`: `Kod`, `Nazev`, `ObecKod`, geometry `DefinicniCara` (MultiLineString). `CastiObci`: `Kod`, `Nazev`,
  `ObecKod`, geometry `DefinicniBod` (Point).
- Code list `CS_TYP_STAVEBNIHO_OBJEKTU` (`https://services.cuzk.cz/sestavy/cis/CS_TYP_STAVEBNIHO_OBJEKTU.zip`, cp1250):
  1 "budova s číslem popisným", 2 "budova s číslem evidenčním", 3 "budova bez č.p. /č.ev.".

**District 3604, export of 2026-10-31** (111 municipalities)

- 31,860 address points: 4 without a point; 11,877 with a street; 4 with an orientation number, none with a letter;
  number type 1 on 30,702, 2 on 1,158; every one has a building and every building a part of municipality.
- 31,824 address points have a building whose `IdentifikacniParcelaId` is in the parcel store of the current import
  (2,607 of them on land plots).
- 670 streets (all MultiLineString), 12 without an address; 85 street names occur in more than one municipality
  (Tyršova 10×, Husova 7×). 296 parts; the names Chloumek and Březka occur in two municipalities.
- Names contain Czech letters, `ü`, digits, `.`, `-` and spaces (e.g. "17. listopadu", "Nábř. Kpt. Jaroše").

**GDAL**

- OGR SQL cannot chain joins ("Field s.CastObceKod in JOIN clause does not correspond to the primary table nor the
  joint (secondary) table"). Two joins from the primary table on the smallest export hit a 120 s timeout and emitted
  duplicate rows. The SQLite dialect join ran for more than 25 minutes on one file.
- One run per municipality, layers in file order:
  `ogr2ogr -f SQLite <out>.sqlite /vsizip/<zip>/<entry> CastiObci Ulice StavebniObjekty AdresniMista -t_srs EPSG:4326
  -oo READ_MODE=SEQUENTIAL_LAYERS -lco FORMAT=WKT`: 26.6 s for all 111 exports (`STANDARD`: 32.2 s; four separate
  `-sql` runs to GeoJSONSeq: 48.5 s). Counts complete: 31,860 / 45,479 / 670 / 296.
- `READ_MODE=INTERLEAVED_LAYERS` writes no features (exit 0): never use it.
- Output tables `castiobci`, `ulice`, `stavebniobjekty`, `adresnimista` with lower-case column names (`kod`, `nazev`,
  `obeckod`, `definicnibod`, `definicnicara`, `cislodomovni`, `cisloorientacni`, `cisloorientacnipismeno`,
  `stavebniobjektkod`, `ulicekod`, `adresnibod`, `typstavebnihoobjektukod`, `castobcekod`, `identifikacniparcelaid`).
  Geometry is WKT text in lon/lat order: `POINT (15.3411922338067 50.4333034823058)`,
  `MULTILINESTRING ((15.3507080251577 50.4348579013769,15.3506383139981 50.4348575010941,…))`.
- An export without streets (530735) and the trimmed Běchary fixture (no place elements at all) both exit 0 and give
  all four tables (empty): the layers come from GDAL's VFR template.

**Prototype index** (district, PHP in the app image, converted parcels + the 111 place files + coverage)

- Parcels: 272,011 rows (outer-ring bounds from the GeoJSON) in 1.9 s; places: 33,020 rows (31,822 addresses) plus
  FTS5 (`content=''`, `detail=none`, `prefix='1 2 3'`) in 0.6 s; file 24.1 MB; peak memory 4 MB.
- Queries, read-only connection opened per request, 50 runs each: places ("ji", "jic", "jicin", "husova", "husova 1",
  "robousy 1", "nadrazni", "na", "tyrsova 12", "nova paka", "sobotka", "a") median 0.4 to 3.2 ms, p95 at most
  4.0 ms; parcels ("80/2", "st. 72", "1171", "867/121" with an area name filter, "1") p95 at most 0.9 ms.
  `EXPLAIN QUERY PLAN`: `SEARCH p USING INDEX parcels_by_number (base_number=? AND subdivision=?)`.
- Without the name rule of Decision 5, "jicin" lists municipality Jičín and cadastral area Jičín, and "na" lists
  Nadslav twice (part and cadastral area).

**Fixture** (real export, joined)

- 21 address points stand on fixture parcels, all on buildings of number type 1:

  | Address code | Building | Label | Part | Street | Parcel |
  |---|---|---|---|---|---|
  | 17523311 | 17401771 | Valdštejnovo náměstí 60 | 410217 Staré Město | 164372 | 1753297604 |
  | 17523320 | 17401780 | Palackého 61 | 410217 | 164844 | 1753298604 |
  | 17523338 | 17401798 | Palackého 62 | 410217 | 164844 | 1753292604 |
  | 17523419 | 17401879 | Palackého 72 | 410217 | 164844 | 1753309604 |
  | 17523427 | 17401887 | Palackého 73 | 410217 | 164844 | 1753299604 |
  | 17523435 | 17401895 | Valdštejnovo náměstí 76 | 410217 | 164372 | 64343200010 |
  | 17523443 | 17401909 | Valdštejnovo náměstí 77 | 410217 | 164372 | 1753303604 |
  | 17523451 | 17401917 | Čelakovského 78 | 410217 | 164232 | 1753305604 |
  | 17523460 | 17401925 | Čelakovského 79 | 410217 | 164232 | 1753306604 |
  | 25344943 | 24875422 | Valdštejnovo náměstí 57 | 410217 | 164372 | 1753294604 |
  | 25344951 | 24875431 | **Valdštejnovo náměstí 58** | 410217 | 164372 | **1753295604 (st. 72)** |
  | 25344960 | 24875449 | Valdštejnovo náměstí 99 | 410217 | 164372 | 1753293604 |
  | 26067994 | 25571061 | Palackého 71 | 410217 | 164844 | 1753310604 |
  | 26921952 | 26407507 | Valdštejnovo náměstí 59 | 410217 | 164372 | 1753296604 |
  | 26921979 | 26407523 | Valdštejnovo náměstí 74 | 410217 | 164372 | 1753300604 |
  | 26921987 | 26407531 | Valdštejnovo náměstí 75 | 410217 | 164372 | 1753301604 |
  | 27241840 | 26723832 | Čelakovského 80 | 410217 | 164232 | 1753304604 |
  | 30292531 | 17401780 | Valdštejnovo náměstí 61 | 410217 | 164372 | 1753298604 |
  | 30292549 | 17401909 | Čelakovského 77 | 410217 | 164232 | 1753303604 |
  | 77797566 | 91816165 | Valdštejnovo náměstí 114 | 410217 | 164372 | 64343201010 |
  | 85970085 | 146100000 | **Robousy 239** | 140228 Robousy | — | **95522753010** |

  Point of Valdštejnovo náměstí 58: `POINT (15.351065973216 50.4373768326378)`.
- Address 17482674 (Robousy 1, building 17361141, part 140228) stands on parcel 1860073604, which is not in the
  fixture: the case "not covered, not indexed".
- Street 164143 "17. listopadu" has none of the kept addresses: the case "street without an indexed address".

**Layout** (Chromium, the running app, st. 72 panel, legend open, injected CSS: 44 px box, 8 px gap; visible/content px)

| Viewport | Today | Box, lift kept | Box, lift dropped while open (all widths ≤ 800) |
|---|---|---|---|
| 1280×720 | 269/269 | 269/269, no overlap | 269/269, no overlap |
| 641×480 | 92/269 | 40/269, no overlap | 112/269, no overlap |
| 390×844 | 269/269 | 269/269, no overlap | `.map-legend` overlaps `.maplibregl-ctrl-attrib` |
| 360×640 | 252/269 | 200/269, no overlap | `.map-legend` overlaps `.maplibregl-ctrl-attrib` |

English: content 229 px at 1280 and 641. With the 12 px gap every capped value is 4 px lower (Decision 13).

**External geocoders** (public documentation only; no API was called)

- Nominatim usage policy: autocomplete is "unacceptable use"; at most 1 request/s; an identifying User-Agent.
- Mapy.com: a suggest endpoint exists; API key, tariff and credits, Mapy.com branding, use by the application's owner.
- Photon: public instance "be fair", throttled, no guarantee; self-hosting needs Java 21, a planet database of about
  95 GB, 64 GB RAM recommended; Czech dump `photon-db-cz-250720.tar.bz2` 1 GB, dated 2025-07-21.

## Steps

The first commit on the branch is this task file (`docs(tasks): add 009 Search bar`). Each step below is one commit,
and every commit passes `make check`. Write the outer test of each step first (CLI/HTTP API test or E2E) and watch it
fail for the right reason.

1. **The import publishes a search index of parcel numbers**
   (`feat(import): publish a search index with each tileset (#009)`).
   `make data` also writes `data/search/{version}.sqlite` (tables `metadata`, `cadastral_areas`, `parcels`), published
   with the tileset and the parcel store. Adds `SearchText`, the port `SearchIndexBuilder`, `BuiltSearchIndex`,
   `SearchIndexBuildFailed`, the adapter, the publisher's third file, the progress line and the new last line. README
   Data section; Glossary (`SearchIndex`, `SearchText`).
2. **Find parcels by number over HTTP** (`feat(search): find parcels by number (#009)`).
   `GET /api/search/{version}?q=…&near=…` answers `{"places": [], "parcels": [...]}`; `/api/map-config` `parcels`
   gains `searchUrl`. Adds `SearchQuery`, `ParcelNumberQuery`, `ParcelMatch`, `SearchIndexRepository` (with
   `isPublished` and `findParcels`), the use case, the handler and route, query parameters on `Request`,
   `Response::badRequest()`. The fixture data dir is regenerated (Decision 16). `tests/Perf/search.spec.ts` with the
   parcel queries. Glossary.
3. **A search box finds parcels** (`feat(map): search parcels by number (#009)`).
   The box (combobox, debounce, keyboard, status line), the parcels group, picking a parcel, the layout of
   Decision 13 with the body class, texts, the extended no-overlap E2E.
4. **Places in the index and the API** (`feat(search): find places and addresses (#009)`).
   The port `PlaceConverter` and its adapter, the place part of the builder (FTS5), `Place`, `PlaceKind`, `Address`,
   `HouseNumber`, `HouseNumberKind`, `DistinctPlaces`, `findPlaces`, the `places` member of the response, the
   import's place counts. The Jičín fixture zip is re-trimmed and the fixture data dir regenerated. The perf spec
   gains the place queries. Glossary.
5. **The search box shows places** (`feat(map): move the map to a found place (#009)`).
   The places group with kind names, picking a place (fit or ease, panel closed), picking an address (its parcel is
   selected), the final placeholder text.

## Architecture

Paths and namespaces are binding. Method bodies, private helpers and test code are left to the implementation.
Conventions from earlier tasks apply: value objects are `final readonly` with `equals()`; exceptions are
`final class … extends \InvalidArgumentException` (invalid values) or `\RuntimeException` (failures) with named
constructors that build the message; `Pdo\Sqlite` with `Pdo\Sqlite::ATTR_OPEN_FLAGS => Pdo\Sqlite::OPEN_READONLY` for
reading.

### Domain

New namespace **`ParcelMap\Domain\Search`**. It uses `Domain\Map\Coordinates`, `Bounds`, `TilesetVersion` and
`Domain\Cadastre\ParcelId`, `ParcelKind`, `ParcelNumber`, `CadastralArea`, `CadastralAreaCode`, `MunicipalityCode`.

| Class | Step | Constructor / factories | Invariants and behaviour |
|---|---|---|---|
| `SearchText` | 1 | private constructor; `static of(string $text): self`; `public string $normalized` | Normalisation: `mb_strtolower`, then the private constant map `á→a č→c ď→d é→e ě→e í→i ň→n ó→o ř→r š→s ť→t ú→u ů→u ý→y ž→z ä→a ö→o ü→u ľ→l ĺ→l ŕ→r ô→o` (`strtr`), then every run of characters other than `a-z`, `0-9`, `/` becomes one space, then trim. `tokens(): list<string>` (split at spaces; `[]` for empty). `terms(): list<string>`: the tokens split at `/`, without empty parts and without single-letter tokens that are a letter. `key(): string` = `implode(' ', terms())`. `isEmpty(): bool`. `equals()` compares `normalized`. Examples: `of('Valdštejnovo náměstí 58')->key()` = `valdstejnovo namesti 58`; `of('Husova č. ev. 12')->key()` = `husova ev 12`; `of('Husova 624/3a')->key()` = `husova 624 3a`; `of('st. 80/2')->tokens()` = `['st', '80/2']`. Class comment: one normalisation for the index and the query, so they always agree. |
| `ParcelNumberQuery` | 2 | `__construct(public ?ParcelKind $kind, public int $baseNumber, public ?int $subdivision)` | `1 ≤ baseNumber ≤ 99999` → `InvalidParcelNumberQuery::baseNumberOutOfRange(int)`; subdivision null or `1 ≤ s ≤ 9999` → `::subdivisionOutOfRange(int)`. `kind` null = any kind. `matches(ParcelNumber $number): bool` (base and subdivision equal, kind equal unless null). `equals()`. |
| `SearchQuery` | 2, 4 | private constructor; `static parse(string $text): self` | Trimmed text empty → `InvalidSearchQuery::empty()`; `mb_strlen` > 100 → `InvalidSearchQuery::tooLong(int $length)`. Parsing per Decision 6 over `SearchText::of($text)->tokens()`. Accessors: `parcelNumber(): ?ParcelNumberQuery`; `cadastralAreaCode(): ?CadastralAreaCode`; `nameTerms(): list<string>` (the tokens that are not the parcel number, the area code, the `st` marker, `pc` or `parc`, split at `/`, without single-letter alphabetic parts); `placeTerms(): list<string>` (step 4: every token except the `st` marker, `pc` and `parc`, split at `/`, without empty parts and single-letter alphabetic parts, at most the first 8); `placeKey(): string` = `implode(' ', placeTerms())`; `searchesParcels(): bool` = a parcel number was found; `searchesPlaces(): bool` (step 4) = some place term has ≥ 2 characters and contains a letter. Every term consists of `[a-z0-9]` only (say so in a comment: the repository relies on it). |
| `ParcelMatch` | 2 | `__construct(public ParcelId $id, public ParcelNumber $number, public CadastralArea $cadastralArea)` | A parcel found by its number. `equals()` compares the id. |
| `PlaceKind` | 4 | `enum PlaceKind: int { case Municipality = 1; case CadastralArea = 2; case MunicipalityPart = 3; case Street = 4; case Address = 5; }` | The values are the ranking order (comment). |
| `HouseNumberKind` | 4 | `enum HouseNumberKind: int { case Descriptive = 1; case Registration = 2; }`; `static fromCode(int $code): self` | Comment: `TypStavebnihoObjektuKod`, 1 číslo popisné, 2 číslo evidenční; any other code → `InvalidHouseNumber::unknownKind(int)`. |
| `HouseNumber` | 4 | `__construct(public HouseNumberKind $kind, public int $number, public ?int $orientationNumber, public ?string $orientationLetter)` | `number ≥ 1` → `InvalidHouseNumber::notPositive(int)`; orientation null or ≥ 1 → `::orientationNotPositive(int)`; letter null or one letter `^[A-Za-z]$` → `::invalidLetter(string)`; a letter without an orientation number → `::letterWithoutOrientation()`. `toString()`: `624`, `624/3`, `624/3a` (descriptive); `č. ev. 12`, `č. ev. 12/3` (registration). `equals()`. |
| `Address` | 4 | `__construct(public ?string $street, public string $municipalityPart, public string $municipality, public HouseNumber $number, public Coordinates $point)` | `street` null or non-blank, `municipalityPart` and `municipality` non-blank → `InvalidAddress::blankName(string $field)`. `label()`: `"{street ?? municipalityPart} {number}"`. `context()`: Decision 5. `equals()` compares all fields. |
| `Place` (entity) | 4 | `__construct(public PlaceKind $kind, public int $code, public MunicipalityCode $municipality, public string $name, public string $context, public Coordinates $center, public ?Bounds $bounds, public ?ParcelId $parcel)`; `static fromAddress(int $code, MunicipalityCode $municipality, Address $address, ParcelId $parcel): self` (name = label, context = context, center = point, bounds null) | `code ≥ 1` → `InvalidPlace::codeNotPositive(int)`; `trim(name) !== ''` → `::blankName(int $code)`; `parcel` only for `Address` → `::parcelOnlyForAddresses(int $code)`; when bounds are given the center lies inside them → `::centerOutsideBounds(int $code)`. Identity: kind + code; `equals()`. `key(): string` = `SearchText::of($name)->key()`. |
| `DistinctPlaces` | 4 | `final class` with `static keep(array $places): array` (`@param list<Place>`, `@return list<Place>`) | Decision 5: among places of the kinds municipality, cadastral area and part with the same municipality and the same `key()`, only the one with the lowest kind value stays; streets and addresses always stay; the input order is kept otherwise. Pure, no state. |

Repository (step 2, extended in step 4):

```php
interface SearchIndexRepository
{
    public function isPublished(TilesetVersion $version): bool;

    /**
     * Parcels whose number matches the query, ranked per Decision 7; [] when the query has no parcel number.
     *
     * @return list<ParcelMatch>
     * @throws SearchIndexNotPublished
     * @throws SearchIndexUnreadable
     */
    public function findParcels(TilesetVersion $version, SearchQuery $query, ?Coordinates $near, int $limit): array;

    /**
     * Step 4. Places matching the query's place terms, ranked per Decision 7; [] when the query searches no places.
     *
     * @return list<Place>
     * @throws SearchIndexNotPublished
     * @throws SearchIndexUnreadable
     */
    public function findPlaces(TilesetVersion $version, SearchQuery $query, ?Coordinates $near, int $limit): array;
}
```

Domain exceptions: `InvalidSearchQuery`, `InvalidParcelNumberQuery`, `InvalidHouseNumber`, `InvalidAddress`,
`InvalidPlace` (`\InvalidArgumentException`); `SearchIndexNotPublished::forVersion(string $version)`,
`SearchIndexUnreadable::because(string $reason)` (`\RuntimeException`; the latter becomes a 500 through the kernel).

### Application

**Import** (`ParcelMap\Application\Import`)

Step 1:

```php
interface SearchIndexBuilder
{
    /**
     * Builds the search index of all converted parcels; area and municipality names come from the coverage.
     * Step 4 adds the places of every municipality (one ConvertedPlaces per municipality, in coverage order).
     *
     * @param non-empty-list<ConvertedParcels> $parcels
     * @param non-empty-list<ConvertedPlaces> $places   (step 4)
     * @throws SearchIndexBuildFailed
     */
    public function build(array $parcels, /* step 4: array $places, */ Coverage $coverage): BuiltSearchIndex;
}
```

- Step 1: `final readonly class BuiltSearchIndex(public string $path)` (path opaque to the use case). Step 4 adds
  `public IndexedPlaceCounts $places`.
- `SearchIndexBuildFailed extends ImportFailed` with `::because(string $reason)`,
  `::invalidParcel(string $path, int $line, string $reason)` (message `"<path> line <n>: <reason>"`) and, step 4,
  `::invalidPlace(string $path, string $kind, int $code, string $reason)` (message
  `"<path>: <kind> <code>: <reason>"`).
- `ParcelDataPublisher::publish(BuiltTileset $tileset, BuiltParcelStore $store, BuiltSearchIndex $index,
  TilesetVersion $version, ExportDate $exportDate): void` (the index is a new third parameter; doc comment: readers
  see either the old or the new parcel data, never a tileset without its store and index).
- `ImportProgress::searchIndexBuildStarted(int $parcelCount): void` (step 1); step 4 changes it to
  `searchIndexBuildStarted(int $parcelCount, int $municipalityCount): void`.
- `ImportParcelsUseCase`: constructor gains `SearchIndexBuilder $searchIndexBuilder` after `ParcelStoreBuilder
  $storeBuilder` (step 1) and `PlaceConverter $placeConverter` after `ParcelConverter $converter` (step 4). After the
  store is built: `searchIndexBuildStarted(...)`, `$index = $searchIndexBuilder->build(...)`, version from the clock,
  `publish($tileset, $store, $index, $version, $exportDate)`. An over-budget tileset still builds nothing further
  and publishes nothing.

Step 4:

```php
interface PlaceConverter
{
    /**
     * Extracts the places of one municipality (parts, streets, buildings, address points) from its export.
     *
     * @throws ConversionFailed
     */
    public function convert(DownloadedExport $export): ConvertedPlaces;
}
```

- `final readonly class ConvertedPlaces(public MunicipalityCode $municipality, public string $path)` (path opaque).
- The use case converts the places of each municipality right after its parcels, in the same loop; the progress line
  `municipalityConverted` is unchanged.
- `final readonly class IndexedPlaceCounts(public int $municipalities, public int $cadastralAreas, public int
  $municipalityParts, public int $streets, public int $addresses)`, each ≥ 0 →
  `InvalidIndexedPlaceCounts::negative(string $kind, int $count)` (`InvalidArgumentException`, this namespace).
- `ImportParcelsOutput` gains a last parameter `public IndexedPlacesOutput $indexedPlaces` with
  `final readonly class IndexedPlacesOutput(int $municipalities, int $cadastralAreas, int $municipalityParts, int
  $streets, int $addresses)`.

**Search** (`ParcelMap\Application\Search`, new, step 2, extended in step 4)

`final readonly class SearchMapUseCase`:

- Constructor `(ParcelTilesetRepository $tilesets, SearchIndexRepository $index)`.
- `execute(SearchMapInput $input): SearchMapOutput`; `@throws SearchNotAvailable`, `@throws InvalidSearchRequest`.
- `private const int PLACE_LIMIT = 8; private const int PARCEL_LIMIT = 8;`
- Logic, in this order:
  1. `$tilesets->current()`: `ParcelDataNotImported` → `SearchNotAvailable::noDataImported()`.
  2. `new TilesetVersion($input->version)`: invalid, or not equal to the current version →
     `SearchNotAvailable::unknownVersion(string)`.
  3. `SearchQuery::parse($input->query)`: `InvalidSearchQuery` → `InvalidSearchRequest::invalidQuery(string $reason)`.
  4. Near: both coordinates null → null; exactly one null → `InvalidSearchRequest::invalidNear('…')`;
     `new Coordinates(lon, lat)` throwing `InvalidCoordinates` → `invalidNear(message)`.
  5. Parcels = `searchesParcels()` ? `findParcels(version, query, near, PARCEL_LIMIT)` : `[]`.
     Places = step 2: `[]`; step 4: `searchesPlaces()` ? `findPlaces(version, query, near, PLACE_LIMIT)` : `[]`.
     `SearchIndexNotPublished` → `SearchNotAvailable::indexNotPublished(string $version)`;
     `SearchIndexUnreadable` and `ParcelTilesetUnreadable` propagate (500).
  6. Map to the output.

DTOs:

- `final readonly class SearchMapInput(public string $version, public string $query, public ?float $nearLongitude,
  public ?float $nearLatitude)`.
- `final readonly class SearchMapOutput(public array $places, public array $parcels)` with
  `@param list<PlaceResultOutput> $places` and `@param list<ParcelResultOutput> $parcels` (ranked order).
- `final readonly class ParcelResultOutput(public int $id, public string $parcelNumber /* "st. 72" */, public string
  $kind /* 'building'|'land' */, public int $cadastralAreaCode, public string $cadastralAreaName, public int
  $municipalityCode, public string $municipalityName)`.
- Step 4: `final readonly class PlaceResultOutput(public string $kind /* 'municipality'|'cadastralArea'|
  'municipalityPart'|'street'|'address' */, public int $code, public string $name, public string $context, public
  float $centerLongitude, public float $centerLatitude, public ?float $west, public ?float $south, public ?float
  $east, public ?float $north, public ?int $parcelId)` (all four bounds null or all set).
- `SearchNotAvailable extends \RuntimeException` and `InvalidSearchRequest extends \InvalidArgumentException` with the
  named constructors above.

**Map** (`ParcelMap\Application\Map`, step 2)

- `GetMapConfigurationUseCase` constructor gains `SearchIndexRepository $searchIndex` (fourth parameter).
- `ParcelLayerOutput` gains a last parameter `public bool $searchAvailable` = `detailsAvailable &&
  $searchIndex->isPublished($tileset->version)`.

### Infrastructure

**`Infrastructure\Import\SearchIndexBuilderUsingSqlite(string $workDir)`** implements `SearchIndexBuilder`

- Output `$workDir/search.sqlite`; an existing file is deleted first; the directory is created if missing. Written as
  the parcel store builder does: `journal_mode = OFF`, `synchronous = OFF` (same comment), one transaction for the
  inserts. On any failure the partial file is deleted.
- Step 1 schema:

  ```sql
  CREATE TABLE metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL);
  CREATE TABLE cadastral_areas (
      code INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      municipality_code INTEGER NOT NULL,
      municipality_name TEXT NOT NULL,
      search_words TEXT NOT NULL      -- SearchText key of the name, a space, SearchText key of the municipality name
  );
  CREATE TABLE parcels (
      id INTEGER PRIMARY KEY,         -- RÚIAN Id
      cadastral_area_code INTEGER NOT NULL REFERENCES cadastral_areas (code),
      kind INTEGER NOT NULL,          -- DruhCislovaniKod: 1 building, 2 land
      base_number INTEGER NOT NULL,
      subdivision INTEGER,            -- NULL when the number has none
      west REAL NOT NULL, south REAL NOT NULL, east REAL NOT NULL, north REAL NOT NULL   -- outer ring bounds, WGS84
  );
  -- after the inserts:
  CREATE INDEX parcels_by_number ON parcels (base_number, subdivision, kind);
  ```

- `cadastral_areas`: one row per coverage area.
- Parcels: every non-blank line of every converted file, decoded with `JSON_THROW_ON_ERROR`; required integer
  properties `id`, `areaCode`, `kind`, `baseNumber`, optional `subdivision` (GDAL omits nulls); geometry `Polygon`.
  Built as domain objects: `ParcelId`, `$coverage->area(new CadastralAreaCode(…))`, `ParcelNumber(ParcelKind::fromCode
  …)`, and the bounds from `ParcelBoundary::fromRings([outer ring as Coordinates])->bounds()`. Reuse
  `Infrastructure\Import\InvalidParcelLine` for malformed lines. Any invalid line or duplicate id →
  `SearchIndexBuildFailed::invalidParcel(path, line, reason)`; other failures → `SearchIndexBuildFailed::because(…)`.
- Class comment: the index keeps its own slim parcel table, so search needs neither the parcel store nor the tiles.
- **Step 4 additions:**

  ```sql
  CREATE TABLE places (
      id INTEGER PRIMARY KEY,
      kind INTEGER NOT NULL,                -- PlaceKind value
      code INTEGER NOT NULL,                -- RÚIAN code of that kind
      municipality_code INTEGER NOT NULL,
      name TEXT NOT NULL,
      context TEXT NOT NULL,
      search_key TEXT NOT NULL,             -- SearchText key of the name
      center_longitude REAL NOT NULL,
      center_latitude REAL NOT NULL,
      west REAL, south REAL, east REAL, north REAL,   -- all NULL when the place has no bounds
      parcel_id INTEGER,                    -- addresses: the building's identifying parcel
      UNIQUE (kind, code)
  );
  CREATE VIRTUAL TABLE places_fts USING fts5(text, content='', detail=none, prefix='1 2 3', tokenize='unicode61');
  -- rowid = places.id, text = SearchText::of(name . ' ' . context)->key()
  -- after the inserts: INSERT INTO places_fts (places_fts) VALUES ('optimize');
  ```

  - For each `ConvertedPlaces` (outside a transaction: `ATTACH` the file as `m`; then begin, insert, commit;
    then `DETACH`):
    - Addresses:

      ```sql
      SELECT a.kod, a.adresnibod, a.cislodomovni, a.cisloorientacni, a.cisloorientacnipismeno,
             s.typstavebnihoobjektukod, s.identifikacniparcelaid, c.kod AS part_code, c.nazev AS part_name,
             u.kod AS street_code, u.nazev AS street_name
      FROM m.adresnimista a
      JOIN m.stavebniobjekty s ON s.kod = a.stavebniobjektkod
      JOIN m.castiobci c ON c.kod = s.castobcekod
      LEFT JOIN m.ulice u ON u.kod = a.ulicekod
      JOIN main.parcels p ON p.id = s.identifikacniparcelaid
      WHERE a.adresnibod IS NOT NULL
      ORDER BY a.kod
      ```

      Each row → `HouseNumber`, `Address` (municipality name from the coverage, by the `ConvertedPlaces`
      municipality), `Place::fromAddress`. The point is parsed from `POINT (lon lat)`.
    - Streets with at least one indexed address: `SELECT kod, nazev, definicnicara FROM m.ulice`; bounds = min/max of
      every `lon lat` pair in the WKT; center = bounds midpoint; context = municipality name.
    - Parts with at least one indexed address: name from `m.castiobci`; bounds = min/max of their indexed address
      points; center = midpoint; context = municipality name.
    - A bounds rectangle with zero width or height is stored as no bounds (center only).
  - Cadastral areas (from the coverage) and municipalities: bounds from
    `SELECT cadastral_area_code, MIN(west), MIN(south), MAX(east), MAX(north) FROM parcels GROUP BY cadastral_area_code`;
    a municipality's bounds are the union of its areas'; center = midpoint. Context: area → municipality name;
    municipality → `''`.
  - `DistinctPlaces::keep()` over the places of each municipality, then insert in the order kind, code.
  - All coordinates are rounded to 7 decimals (as the parcel conversion) before building the domain objects.
  - A domain exception or an unparsable WKT → `SearchIndexBuildFailed::invalidPlace(path, kind, code, reason)`.
  - Returns `BuiltSearchIndex($path, new IndexedPlaceCounts(…))` with the counts of inserted places per kind.

**`Infrastructure\Import\PlaceConverterUsingOgr2ogr(ProcessRunner $runner, string $workDir)`** implements
`PlaceConverter` (step 4)

- Output `$workDir/places/{municipality code}.sqlite`; the directory is created and an existing file deleted first.
- Command (Verified facts): `ogr2ogr -f SQLite <output> /vsizip/<export path>/<entry> CastiObci Ulice StavebniObjekty
  AdresniMista -t_srs EPSG:4326 -oo READ_MODE=SEQUENTIAL_LAYERS -lco FORMAT=WKT` (entry = `basename(path, '.zip')`,
  as the parcel converter).
- Exit code ≠ 0 → delete the output, `ConversionFailed::because(sprintf('ogr2ogr failed for %s: %s', …))`.
- Class comment: one run per municipality, layers in file order, so the GML driver reads the file in one sequential
  pass; GDAL joins are unusable on these files, so the index builder joins; never `INTERLEAVED_LAYERS` (writes
  nothing).

**`Infrastructure\Import\ParcelDataPublisherUsingSqlite(string $tilesetFile, string $parcelStoreDirectory, string
$searchIndexDirectory)`** (step 1)

1. Stamp `metadata` of the tileset, the store and the index (`parcel_map_version`, `parcel_map_export_date`).
2. Read the live version (unchanged).
3. Move the store to `{store dir}/{version}.sqlite`; move the index to `{search dir}/{version}.sqlite` (on failure
   remove the moved store, then throw).
4. Rename the tileset (commit point); on failure remove both moved files, then throw.
5. Prune both directories to `{version}.sqlite` and `{live version}.sqlite` (failures ignored, as today).

Update the class comment. `tests/Fake/ParcelDataPublisherUsingMemory` records the index path as well.

**`Infrastructure\Search\SearchIndexRepositoryUsingSqlite(string $directory)`** implements `SearchIndexRepository`
(step 2, extended in step 4)

- File `{directory}/{version}.sqlite`; `isPublished()` = `is_file`. Missing file → `SearchIndexNotPublished`. Opened
  read-only per call (a re-import replaces files).
- `findParcels()` (returns `[]` without a parcel number):

  ```sql
  SELECT p.id, p.kind, p.base_number, p.subdivision, a.code, a.name, a.municipality_code, a.municipality_name
  FROM parcels p JOIN cadastral_areas a ON a.code = p.cadastral_area_code
  WHERE p.base_number = :base AND p.subdivision IS :subdivision
    [AND p.kind = 1]                                   -- query kind Building
    [AND p.cadastral_area_code = :area]                -- 6-digit code
    [AND (' ' || a.search_words) LIKE :term1 ...]      -- one per name term, value '% ' || term || '%'
  ORDER BY [((p.west + p.east) / 2 - :lon) * ((p.west + p.east) / 2 - :lon) * :k
            + ((p.south + p.north) / 2 - :lat) * ((p.south + p.north) / 2 - :lat),]
           p.kind DESC, a.name, p.id
  LIMIT :limit
  ```

  `:k` = cos²(latitude of `near`). Terms are `[a-z0-9]` only (domain guarantee), so they contain no `LIKE` wildcard.
- `findPlaces()` (step 4; returns `[]` when the query searches no places): `MATCH` expression = the place terms, each
  as `"term"*`, joined with ` AND `.

  ```sql
  SELECT p.kind, p.code, p.municipality_code, p.name, p.context, p.center_longitude, p.center_latitude,
         p.west, p.south, p.east, p.north, p.parcel_id
  FROM places_fts f JOIN places p ON p.id = f.rowid
  WHERE places_fts MATCH :match
  ORDER BY (p.search_key = :key) DESC, (substr(p.search_key, 1, length(:key)) = :key) DESC, p.kind,
           [(p.center_longitude - :lon) * (p.center_longitude - :lon) * :k
            + (p.center_latitude - :lat) * (p.center_latitude - :lat),]
           p.search_key, p.id
  LIMIT :limit
  ```

  `:key` = `placeKey()`.
- Rows become domain objects; a `PDOException` or a domain exception → `SearchIndexUnreadable::because(…)`.

### Handlers (HTTP)

**`Handler\Http\Request`** (step 2): a fifth constructor parameter `public array $queryParameters = []`
(`array<string, string>`). `fromServer()` parses the query of `REQUEST_URI` with `parse_str` and keeps string keys
with string values only (`q[]=x` is dropped). `queryParameter(string $name): ?string`. `withPathParameters()` keeps the
query parameters.

**`Handler\Http\Response::badRequest(): self`** (step 2): 400 `{"error":"bad_request"}`.

**`Handler\SearchRoute`** (step 2): `final class`, private constructor, `public const string PATTERN =
'/api/search/{version}'`, `public static function url(string $version): string`.

**`GET /api/search/{version}?q=<text>&near=<lon>,<lat>`** → `SearchMapHandler(SearchMapUseCase)` (step 2)

- `q` missing → 400. `near` optional; present but not matching `^-?[0-9]{1,3}(\.[0-9]{1,8})?,-?[0-9]{1,2}(\.[0-9]{1,8})?$`
  → 400. Then `SearchMapInput(version, q, lon, lat)`.
- Results:

  | Case | Status | Headers | Body |
  |---|---|---|---|
  | found (possibly empty lists) | 200 | `Content-Type: application/json`, `Cache-Control: public, max-age=31536000, immutable` | results JSON |
  | `InvalidSearchRequest`, missing `q`, malformed `near` | 400 | `Content-Type: application/json` | `{"error":"bad_request"}` |
  | `SearchNotAvailable` | 404 | `Content-Type: application/json` | `{"error":"not_found"}` |
  | other method | 405 | `Allow: GET` | `{"error":"method_not_allowed"}` |
  | `SearchIndexUnreadable` / `ParcelTilesetUnreadable` | 500 (kernel) | | `{"error":"internal_error"}` |

- Body for `q=Valdštejnovo náměstí 58` on the step 4 fixture (bounds and centres of non-address places are elided
  here; `bounds` is `null` or an object with all four members; `places` is always `[]` in steps 2 and 3):

  ```json
  {
    "places": [
      {
        "kind": "address",
        "code": 25344951,
        "name": "Valdštejnovo náměstí 58",
        "context": "Staré Město, Jičín",
        "center": { "longitude": 15.351066, "latitude": 50.4373768 },
        "bounds": null,
        "parcelId": 1753295604
      }
    ],
    "parcels": []
  }
  ```

  For `q=st. 72`:

  ```json
  {
    "places": [],
    "parcels": [
      {
        "id": 1753295604,
        "parcelNumber": "st. 72",
        "kind": "building",
        "cadastralArea": { "code": 659541, "name": "Jičín" },
        "municipality": { "code": 572659, "name": "Jičín" }
      }
    ]
  }
  ```

  A municipality: `{"kind":"municipality","code":572659,"name":"Jičín","context":"","center":{…},"bounds":{"west":…,
  "south":…,"east":…,"north":…},"parcelId":null}`.

**`GET /api/map-config`** (step 2): `parcels` gains `"searchUrl": "/api/search/<version>"` (after
`parcelUrlTemplate`), or `null` when `searchAvailable` is false. Everything else is unchanged.

**Route table** (Container): step 2 adds `GET SearchRoute::PATTERN`.

### Commands (CLI)

**`bin/console parcels:import`**

- Description (step 1): `Download the RÚIAN export and build the parcel tileset, parcel store and search index.`
- Progress line after the parcel store line (`ImportProgressUsingConsole::searchIndexBuildStarted`):
  - step 1: `Building the search index from 25 parcels.`
  - step 4: `Building the search index from 25 parcels and the places of 1 municipality.` (`municipalities` for more
    than one).
- Step 4: after the per-zoom statistics, one stdout line
  `Indexed places: municipalities 1, cadastral areas 1, parts of municipalities 1, streets 3, addresses 21.`
- Last stdout line (step 1): `Published tileset, parcel store and search index 20261124120000 with 25 parcels.` The
  existing API test regex is updated deliberately (log it).
- Exit codes unchanged.

### Frontend

**`frontend/src/mapConfiguration.ts`** (step 3): `ParcelLayerConfiguration` gains `readonly searchUrl: string | null`;
a string must start with `/`.

**`frontend/src/searchQuery.ts`** (step 3), pure:

- `const SEARCH_MAX_LENGTH = 100`.
- `shouldSearch(text: string): boolean`: the trimmed text has 2 to 100 characters, or 1 character that is a digit.
- `searchRequestUrl(searchUrl: string, text: string, center: { lng: number; lat: number }): string` →
  `<searchUrl>?q=<encodeURIComponent(trimmed)>&near=<lng.toFixed(3)>,<lat.toFixed(3)>`.

**`frontend/src/debounce.ts`** (step 3), pure: `debounce<A extends unknown[]>(fn: (...args: A) => void, delayMs:
number): { call(...args: A): void; cancel(): void }` (trailing call with the last arguments). `SEARCH_DEBOUNCE_MS =
200` lives in `mapSearch.ts`.

**`frontend/src/searchResults.ts`** (step 3, extended in step 5)

- Types (read-only members): `ParcelResult { id, parcelNumber, kind: 'building' | 'land', cadastralArea {code, name},
  municipality {code, name} }`; step 5: `PlaceResult { kind: 'municipality' | 'cadastralArea' | 'municipalityPart' |
  'street' | 'address'; code; name; context; center {longitude, latitude}; bounds {west, south, east, north} | null;
  parcelId: number | null }`; `SearchResults { places: PlaceResult[]; parcels: ParcelResult[] }`.
- `type SearchOutcome = { readonly kind: 'found'; readonly results: SearchResults } | { readonly kind: 'outdated' }`.
- `loadSearchResults(fetchFn: typeof fetch, url: string, signal: AbortSignal): Promise<SearchOutcome>`: 404 →
  `outdated`; another non-2xx, a network failure, invalid JSON or the wrong shape → throws `class SearchError extends
  Error`; an `AbortError` is rethrown unchanged. Step 3 validates `parcels` and requires `places` to be an array; step 5
  validates the place members (finite numbers, known kind, `parcelId` null or a positive safe integer).

**`frontend/src/searchOptions.ts`** (step 3, extended in step 5), pure:

- `interface SearchOption { readonly id: string; readonly name: string; readonly context: string; readonly target:
  PickTarget }` and `interface SearchGroup { readonly key: 'places' | 'parcels'; readonly label: string; readonly
  options: SearchOption[] }`.
- `type PickTarget = { readonly kind: 'parcel'; readonly id: number } | { readonly kind: 'place'; readonly center:
  [number, number]; readonly bounds: [number, number, number, number] | null }`.
- `searchGroups(results: SearchResults, texts: Texts): SearchGroup[]`: places first, then parcels; empty groups are
  left out; option ids `map-search-option-<n>` numbered across groups.
  - Parcel option: name `texts.text('search.parcel', { parcelNumber })` ("Parcela st. 72"), context
    `formatCadastralArea(area name, code)`, plus `, <municipality name>` when it differs from the area name; target
    parcel.
  - Place option (step 5): name = `name`; context = kind name (`place.<kind>`), plus ` · <context>` when not empty;
    target: an address with a `parcelId` → parcel; otherwise place.
- `nextActive(current: number | null, count: number, direction: 1 | -1): number | null`: wraps; `null` when `count`
  is 0; from `null`, down gives 0 and up gives the last.

**`frontend/src/fitPadding.ts`** (step 5): `searchFitPadding(searchBox: { bottom: number }): Padding` → top =
`bottom + 24`, the other sides 40.

**`frontend/src/searchBox.ts`** (step 3): `class SearchBox`, which creates and owns this DOM, appended to `body`:

```html
<div class="map-search" role="search" aria-label="Hledat na mapě">
  <input class="map-search-input" type="search" role="combobox" aria-label="Hledat na mapě"
         aria-autocomplete="list" aria-expanded="false" aria-controls="map-search-list"
         autocomplete="off" spellcheck="false" enterkeyhint="search" placeholder="…">
  <div class="map-search-list" id="map-search-list" role="listbox" aria-label="Výsledky hledání" hidden>
    <div role="group" aria-labelledby="map-search-group-places">
      <div class="map-search-group" id="map-search-group-places" role="presentation">Místa</div>
      <div class="map-search-option" id="map-search-option-0" role="option" aria-selected="false">
        <span class="map-search-option-name">Valdštejnovo náměstí 58</span>
        <span class="map-search-option-context">Adresa · Staré Město, Jičín</span>
      </div>
    </div>
    <div role="group" aria-labelledby="map-search-group-parcels">…</div>
  </div>
  <p class="map-search-status" role="status" hidden></p>
</div>
```

- Constructor `(texts: Texts, callbacks: { onInput(text: string): void; onPick(target: PickTarget, name: string):
  void })`.
- Methods: `showGroups(groups: SearchGroup[])` (an empty list shows `search.noResults`), `showSearching()` (status
  `search.loading` only when no options are shown), `showError()`, `showOutdated()`, `close()` (list and status
  hidden, `aria-expanded="false"`), `setTexts(texts)` (labels, placeholder, and the open list re-rendered),
  `element(): HTMLElement`.
- Behaviour per Decision 11 and 14: `input` events call `onInput`; Arrow keys use `nextActive` and set
  `aria-selected`, `aria-activedescendant` and the active class; Enter picks the active option or the first one;
  Escape with the list open closes it with `preventDefault()` and `stopPropagation()`; `pointerdown` on the list is
  `preventDefault()`ed (the input keeps the focus) and `click` on an option picks it; `focusout` to outside the box
  closes the list. Picking sets the input value to the option's name, closes the list, blurs the input and calls
  `onPick`.
- Texts are set with `textContent`, never `innerHTML`.

**`frontend/src/mapSearch.ts`** (step 3, extended in step 5): `class MapSearch` wires the box to the API and the map.

- Constructor `(map: MapLibreMap, selection: ParcelSelection, fetchFn: typeof fetch, searchUrl: string, texts:
  Texts)`. It creates the `SearchBox` (passing its own `onInput` and `onPick`) and owns the debounce and the
  `AbortController` of the request in flight.
- `onInput(text)`: `shouldSearch` false → cancel the debounce, abort the request, close the list; otherwise debounce
  (200 ms), then abort the previous request, `showSearching()`, read `map.getCenter()`, `loadSearchResults`, and show
  the outcome (`found` → `showGroups(searchGroups(…))`, `outdated` → `showOutdated()`, error → `console.warn('The
  search failed.', error)` and `showError()`; an aborted request shows nothing).
- `onPick(target)`: parcel → `selection.select(id, { fit: true })`; place (step 5) → `selection.clear()`, then
  `map.fitBounds([[w, s], [e, n]], { maxZoom: 17, padding: searchFitPadding(box rect) })` or, without bounds,
  `map.easeTo({ center, zoom: 17 })`.
- `setTexts(texts)` forwards to the box.

**`frontend/src/main.ts`** (step 3): only in the full state (`parcelUrlTemplate !== null`) and when `searchUrl !==
null`, a `MapSearch` is created after the selection; the localization `onChange` listener calls its `setTexts`. The
panel's `onVisibilityChange` callback also sets `document.body.classList.toggle('parcel-panel-open',
panel.isOpen())`. Nothing is shown in the other states.

**`frontend/src/app.css`** (step 3): Decision 13. `.map-search` (`z-index: 3`, above the panel's 2); the input
`height: 2.75rem`, `padding: 0 1.25rem`, no border, a `:focus-visible` outline like the language buttons; the list
`max-height: min(24rem, calc(100vh - 5rem))`, `overflow: auto`, a 1 px `#ddd` top border; the active option and
`:hover` with a `#eef4fb` background; the context in `#666`. The panel `top: 4.25rem` and the caps of Decision 13;
`@media (min-width: 641px) and (max-width: 800px) { body.parcel-panel-open .maplibregl-ctrl-bottom-left { bottom: 0; } }`;
up to 640 px `.map-search { right: 3.5rem; width: auto; }`. Comment every new number with its reason.

**Texts** (`frontend/languages/cs.json` and `en.json`)

| Key | Czech | English | Step |
|---|---|---|---|
| `search.label` | Hledat na mapě | Search the map | 3 |
| `search.placeholder` | Číslo parcely, např. st. 72 | Parcel number, e.g. st. 72 | 3 (step 5 changes it) |
| `search.placeholder` | Adresa, místo nebo číslo parcely | Address, place or parcel number | 5 |
| `search.results` | Výsledky hledání | Search results | 3 |
| `search.parcels` | Parcely | Parcels | 3 |
| `search.parcel` | Parcela {parcelNumber} | Parcel {parcelNumber} | 3 |
| `search.loading` | Hledám… | Searching… | 3 |
| `search.noResults` | Nic nenalezeno. Mapa pokrývá okres Jičín. | Nothing found. The map covers the Jičín district. | 3 |
| `search.error` | Vyhledávání se nezdařilo. Zkuste to prosím znovu. | The search failed. Please try again. | 3 |
| `search.outdated` | Data byla aktualizována. Načtěte stránku znovu. | The data has been updated. Reload the page. | 3 |
| `search.places` | Místa | Places | 5 |
| `place.municipality` | Obec | Municipality | 5 |
| `place.cadastralArea` | Katastrální území | Cadastral area | 5 |
| `place.municipalityPart` | Část obce | Part of a municipality | 5 |
| `place.street` | Ulice | Street | 5 |
| `place.address` | Adresa | Address | 5 |

### Documentation

**CLAUDE.md Glossary** (rows added in the step that introduces the type):

| Czech (source data) | Code name | Notes | Step |
|---|---|---|---|
| — | `SearchIndex` | The search index of one tileset version (`data/search/{version}.sqlite`): parcel numbers and places of the covered area. | 1 |
| — | `SearchText` | Text normalised for search: lower case, no diacritics, only `a-z`, `0-9`, `/` and single spaces. | 1 |
| — | `SearchQuery` | A parsed search text: parcel number, cadastral area code, name terms, place terms. | 2 |
| — | `ParcelNumberQuery` | A parcel number as searched: base, optional subdivision, optional kind (`st.`). | 2 |
| — | `ParcelMatch` | A parcel found by its number: id, number, cadastral area. | 2 |
| — | `Place` / `PlaceKind` | A search result that is a location: municipality, cadastral area, part of a municipality, street or address. | 4 |
| část obce | `PlaceKind::MunicipalityPart` | Part of a municipality (`CastiObci`). | 4 |
| ulice | `PlaceKind::Street` | `Ulice`. | 4 |
| adresní místo | `Address` / `PlaceKind::Address` | An address point (`AdresniMista`); its building's identifying parcel (`IdentifikacniParcelaId`) links it to a `Parcel`. | 4 |
| číslo popisné / evidenční | `HouseNumber`, `HouseNumberKind::Descriptive` / `::Registration` | Written `624` / `č. ev. 12`; `TypStavebnihoObjektuKod` 1 / 2. | 4 |
| číslo orientační | orientation number (part of `HouseNumber`) | Written after a slash: `624/3a`. | 4 |

**README:**

- Data section (step 1; step 4 adds the place conversion and the measured import time): what `make data` writes
  (`data/search/{version}.sqlite`), how it is published, the import duration.
- A Search section (step 2, extended in steps 3 to 5): what is searched, the query grammar in short, the endpoint.
- Smoothness evidence (step 2, extended in step 4): `tests/Perf/search.spec.ts` and its budget.
- Notes > Decisions (step 2): offline search of the covered area only, and why no external geocoder.
- Notes > With more time (step 4): a Czech Republic-wide address search from the ČÚZK address CSV, an external
  geocoder with points of interest, typo tolerance (the facts under Out of scope).

**`tests/Fixtures/README.md`** (steps 2 and 4): the regenerated data dir with `search/<version>.sqlite` and its
facts; the re-trimmed Jičín zip (step 4) with the kept parts, streets, buildings and addresses.

### Wiring

`Bootstrap\Container`:

- Step 1: `searchIndexDirectory()` = data dir + `/search`; `ImportParcelsUseCase` gets
  `new SearchIndexBuilderUsingSqlite($dataDir . '/build')`; `ParcelDataPublisherUsingSqlite` gets the search
  directory.
- Step 2: `new SearchIndexRepositoryUsingSqlite($this->searchIndexDirectory())` for `GetMapConfigurationUseCase`
  (fourth argument) and for the new route `GET SearchRoute::PATTERN` →
  `SearchMapHandler(new SearchMapUseCase(new ParcelTilesetRepositoryUsingMbtiles(…), …))`.
- Step 4: `new PlaceConverterUsingOgr2ogr($runner, $dataDir . '/build')` for `ImportParcelsUseCase`.

Fakes (`tests/Fake`): `SearchIndexBuilderUsingMemory` (step 1), `SearchIndexRepositoryUsingMemory` (step 2: preset
parcels and places, returns them filtered by `ParcelNumberQuery::matches` and cut to the limit, records every call's
arguments, can be unpublished), `PlaceConverterUsingMemory` (step 4).

## Test scenarios

### Unit

- `SearchText`: lower case and diacritics (`Jičín` → `jicin`, `ŘEPA` → `repa`, `Müller` → `muller`); punctuation to
  single spaces (`17. listopadu` → `17 listopadu`); `/` kept in tokens and split in terms; single letters dropped from
  terms (`Popovice u Jičína` → key `popovice jicina`); empty and whitespace-only text; `equals`.
- `ParcelNumberQuery`: range invariants; `matches` with and without kind and subdivision.
- `SearchQuery::parse`:
  - `st. 72` → building 72, no place search; `72` → any kind; `80/2`; `st 80/2`; `p. č. 80/2` → `p`, `c` ignored;
    `parc. č. 80/2`;
  - `867/121 Robousy` → number and name term `robousy`; `Robousy 867/121` (order free); `867/121 740225` → area code;
  - `Husova 12` → parcel 12, name term `husova`, place terms `husova`, `12`, searches places;
  - `st` alone and `stará paka` → no parcel number, place terms; `Husova č.p. 12` → place key `husova 12`;
  - `0`, `0/1`, `100000` (6 digits, so an area code, not a parcel number) and `80/0` give no parcel number; a second
    parcel-number-like token is a name term; `740225` is the area code;
  - more than 8 place terms keep the first 8; empty → `InvalidSearchQuery::empty`; 101 characters → `tooLong`;
    100 characters accepted.
- `HouseNumber::toString` and invariants; `HouseNumberKind::fromCode(3)` fails.
- `Address::label` and `context` (street with a part differing from the municipality; street with part equal by key;
  no street); blank names rejected.
- `Place`: invariants; `fromAddress`; `equals` by kind and code.
- `DistinctPlaces::keep`: municipality and area with the same key → municipality; area and part → area; same name in
  two municipalities → both; streets and addresses never dropped; order kept.
- `SearchMapUseCase` (with `ParcelTilesetRepositoryUsingMemory` and `SearchIndexRepositoryUsingMemory`): no data,
  wrong or malformed version, unpublished index → `SearchNotAvailable`; invalid query, one-sided near, out-of-range near
  → `InvalidSearchRequest`; a parcel query calls only `findParcels` with limit 8 and the near coordinates; a word-only
  query calls only `findPlaces` (step 4); `Robousy 12` calls both; outputs mapped (kind strings, bounds null or four
  numbers, parcel id).
- `GetMapConfigurationUseCase`: `searchAvailable` false without an index, false without details, true with both.
- `ImportParcelsUseCase`: the index is built after the store from the same converted parcels and (step 4) one
  `ConvertedPlaces` per municipality in coverage order; published with the tileset and store; an over-budget tileset
  builds no index; progress `searchIndexBuildStarted`; the output carries the place counts (step 4).
- `ImportProgressUsingConsole`: the new lines, singular and plural.

### Integration

- `SearchIndexBuilderUsingSqliteTest` (fixture GeoJSON and coverage; step 4 also the place file converted from the
  fixture zip in a temporary directory):
  - schema, `cadastral_areas` with `search_words`, one `parcels` row per line with outer-ring bounds (st. 72 bounds
    W 15.3509216 S 50.4372305 E 15.3511404 N 50.437745), the index exists after the build;
  - invalid line, missing property, duplicate id → `invalidParcel` and no output file; an existing output is replaced;
  - step 4: 27 places for the fixture coverage: municipality Jičín (bounds = union of parcel bounds), cadastral area
    Robousy (context Jičín), part Staré Město, streets Palackého, Valdštejnovo náměstí, Čelakovského, 21 addresses;
    not indexed: cadastral area Jičín, part Robousy, street 17. listopadu, address 17482674; Valdštejnovo náměstí 58
    has parcel 1753295604, center (15.351066, 50.4373768), context `Staré Město, Jičín`; Robousy 239 has parcel
    95522753010 and context `Jičín`; both Palackého 61 and Valdštejnovo náměstí 61 point to 1753298604; counts
    1/1/1/3/21.
- `PlaceConverterUsingOgr2ogrTest`: the fixture zip gives the four tables with the expected counts (step 4 fixture:
  2 parts, 4 streets, 20 buildings, 22 addresses); the Běchary fixture gives four empty tables; an invalid zip →
  `ConversionFailed` and no output.
- `ParcelDataPublisherUsingSqliteTest`: the index lands at `{search dir}/{version}.sqlite` with the stamped metadata;
  a failed tileset rename removes the placed store and index; pruning keeps the new and the previously live index;
  the existing cases still hold.
- `SearchIndexRepositoryUsingSqliteTest` (indexes built in a temporary directory from small purpose-made GeoJSON and
  place files, plus the fixture index):
  - parcels: exact number, no subdivision means none, `st` restricts the kind, land before building at equal
    distance, nearest area first with `near`, area name order without `near`, name terms on area and municipality
    words (`rob` finds Robousy, `obou` does not), area code filter, limit, `[]` without a number;
  - places (step 4): prefix terms across name and context, exact key before prefix before kind, nearest first among
    equals, limit, `[]` for an empty match, diacritics-free match (`valdstejnovo` finds Valdštejnovo náměstí);
  - unpublished version → `SearchIndexNotPublished`; a corrupt file → `SearchIndexUnreadable`; `isPublished`.

### API (HTTP and CLI)

- `ImportParcelsCommandTest`: the import writes `search/<version>.sqlite` next to the store; the progress line; step 4
  the `Indexed places:` line; the new last line (regex updated deliberately).
- `MapConfigurationTest`: `searchUrl` is `/api/search/<fixture version>`; null when the index file is missing
  (temporary data dir copy without `search/`).
- `SearchTest` (fixture data):
  - `st. 72` → one parcel with the body above and `Cache-Control: public, max-age=31536000, immutable`;
  - `st. 80/2` → 1753304604; `1171` → 1761441604; `867/121 rob` → 99225194010 with area Robousy and municipality
    Jičín; `867/121 659541` → no parcels; `jicin` → no parcels;
  - step 4: `Valdštejnovo náměstí 58` → the address above first; `valdstejnovo 5` without `near` → exactly the
    addresses 57, 58, 59 in key order; `jicin` → first place municipality Jičín with bounds and no cadastral area named Jičín; `robousy` → the
    cadastral area Robousy and no part Robousy; `stare` → part Staré Město; `17. listopadu` → no places;
    `robousy 239` → the address with parcel 95522753010 and parcels []; `Robousy 1` → no address 17482674;
  - missing `q`, blank `q`, 101 characters, `near=abc`, `near=200,50`, `near=15.35` → 400 `bad_request`;
  - unknown version, malformed version → 404; POST → 405.

### Frontend (unit and E2E)

- Vitest:
  - `searchQuery`: `shouldSearch` thresholds (``, ` a `, `ab`, `1`, ` 7 `, 100 and 101 characters);
    `searchRequestUrl` encodes diacritics, `/` and spaces and rounds `near` to 3 decimals.
  - `debounce` (fake timers): one trailing call with the last arguments; `cancel`.
  - `searchResults`: a valid body; 404 → outdated; 500, invalid JSON, wrong shapes (missing members, unknown kind,
    bad bounds, `parcelId` 0) → `SearchError`; `AbortError` rethrown.
  - `searchOptions`: group order and omission, option names and contexts in both languages, address with parcel →
    parcel target, place without bounds → `bounds: null`; `nextActive` wrapping and empty lists.
  - `fitPadding`: `searchFitPadding`.
  - `mapConfiguration`: `searchUrl` string and null accepted, other values rejected.
  - Language files: every new key in both files (existing consistency test).
- E2E (`tests/E2E/search.spec.ts`, fixture app, offline tiles):
  - step 3: typing `st. 72` shows the option "Parcela st. 72" with "Jičín (659541)"; Enter opens the panel "Parcela
    st. 72", the hash is `#parcel=1753295604` and the highlight is drawn; `867/121 rob` + click opens "Parcela
    867/121"; Arrow Down/Up move `aria-activedescendant`; Escape closes the list and leaves an open panel open; a
    query with no match shows "Nic nenalezeno. Mapa pokrývá okres Jičín."; a stubbed 500 shows the error and a
    stubbed 404 the outdated text; typing requests no tile (the tile log does not grow while typing at a settled
    view) and only one search request per pause; switching to English re-renders the placeholder and an open list
    ("Parcel st. 72"); with `searchUrl: null` (stubbed map-config) there is no search box; without parcel data there
    is no search box.
  - step 5: `jicin` shows "Jičín" with "Obec" first; picking it closes an open panel and changes the view;
    `Valdštejnovo náměstí 58` opens the panel "Parcela st. 72"; `robousy 239` sets `#parcel=95522753010`; `stare`
    shows "Staré Město" with "Část obce · Jičín"; English kind names after a switch.
  - `layout.spec.ts`: `.map-search` joins the checked elements in every existing case at all four viewports; a new
    case per viewport with the results list open over an open panel: the list lies inside the viewport and overlaps
    nothing but the panel; at 641×480 the open panel is at least 100 px high; at 1280×720 it still fits its content.
  - `no-parcel-data.spec.ts`: no search box.

## Acceptance criteria

- [ ] Typing `st. 72` and pressing Enter opens the st. 72 panel with highlight and hash, as a link does.
- [ ] `867/121 rob` finds 867/121 in Robousy; `80/2`-style numbers match exactly; `st.` restricts to building plots.
- [ ] `jicin` finds the municipality Jičín once; `Valdštejnovo náměstí 58` opens the st. 72 panel; an address whose
      building is outside the coverage is not found.
- [ ] Keyboard: Arrow keys, Enter and Escape behave per Decision 14; the combobox is announced correctly.
- [ ] A language switch re-renders the box and an open list.
- [ ] Without a search index of the current version the search box is not shown and the map works as before.
- [ ] No overlap at 1280×720, 641×480, 390×844 and 360×640, in both languages, with the panel open, closed and showing
      not found, and with the list open (overlapping only the panel); the open panel is at least 100 px high at
      641×480.
- [ ] **Smoothness:** no map event listener is added (`move`, `render` and the like); typing causes no tile request;
      one pick makes one camera move.
- [ ] **Latency:** `make perf` (`tests/Perf/search.spec.ts`, real data, running app): every query of the fixed list
      (`ji`, `jicin`, `nova paka`, `husova`, `husova 1`, `robousy 1`, `nadrazni`, `na`, `80/2`, `st. 72`, `1171`,
      `867/121 robousy`; place queries from step 4) answers 200, with a p95 response time of at most 30 ms over HTTP
      (20 requests per query); the report goes to `var/perf/search-perf.json`.
- [ ] **Import:** `make data` from the cache takes at most 40 s longer than before this task (record both times in the
      log); the search index of the district is at most 40 MB.
- [ ] `make check` passes at every commit.

## Risks and assumptions

- **GDAL behaviour is version-specific.** `READ_MODE=SEQUENTIAL_LAYERS`, the template layers and the WKT output were
  verified with GDAL 3.10.3 only. The converter's integration test pins them.
- **Every indexed address has a parcel.** Addresses whose building parcel is not covered are dropped on purpose
  (36 in the district, plus the 4 without a point). If ČÚZK leaves `IdentifikacniParcelaId` empty, the address is
  not found.
- **One name, one place** may hide a cadastral area whose name equals its municipality's; searching that name fits
  the whole municipality instead. Accepted (Decision 5).
- **Ranking is simple** (exact, prefix, kind, distance). No typo tolerance: "jicn" finds nothing.
- **Fixture zip re-trim** (step 4) uses the cached real export `data/cache/ruian/20261031_OB_572659_UKSH.xml.zip`. If
  it is missing, download it (listed until about February 2027) into its own directory. Keep the XML prolog, the
  root element with all namespaces, `vf:Hlavicka`, and in `vf:Data`, in source order: `vf:CastiObci` with only
  `CO.410217` and `CO.140228`; `vf:Ulice` with only `UL.164143`, `UL.164232`, `UL.164372`, `UL.164844`; `vf:Parcely`
  byte for byte as in the current fixture; `vf:StavebniObjekty` with the buildings of the Verified facts table plus
  `SO.17361141` (19 distinct buildings in the table, 20 in total); `vf:AdresniMista` with the 21 addresses of the
  table plus `AD.17482674` (22). Whole elements are kept. A
  one-off script (Python with `-I`, outside the repository, input in its own directory) does it; it is not committed;
  `tests/Fixtures/README.md` documents the rule. After the re-trim the parcel converter output must be unchanged
  (compare with `tests/Fixtures/geojson/572659.geojsonl`); if it changes, stop and report.
- **Fixture regeneration** (steps 2 and 4), as task 006 Decision 17: run `bin/console parcels:import
  --export-date=2026-10-31` with `PARCEL_MAP_COVERAGE_FILE=tests/Fixtures/coverage/fixture.csv`, a temporary
  `PARCEL_MAP_DATA_DIR` whose `cache/ruian/` holds a copy of the fixture zip, and
  `RUIAN_EXPORT_BASE_URL=http://127.0.0.1:9/`; copy `tiles/parcels.mbtiles`, `parcels/<version>.sqlite` and
  `search/<version>.sqlite` into `tests/Fixtures/data` and delete the old version's files; update
  `FixtureTileset::VERSION`, `FIXTURE_VERSION` in `tests/E2E/support/fixture.ts` and the README. Bounds and tile facts
  must not change; if they do, stop and report.
- **The layout numbers** were measured with injected CSS on the running app, not with the final CSS. The E2E
  assertions (no overlap; at least 100 px at 641×480) are the binding check.
- **The 641–800 px body class** moves the legend down while the panel is open and back up when it closes. This is a
  DOM change only, but visible. Accepted (Q6).
- **Disk:** the publisher keeps two versions of three files (about 2 × (56 + 46 + 24) MB for the district).

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: `feat(import): publish a search index with each tileset (#009)`

Red runs (each seen failing before the production code):

- Outer, `tests/Api/ImportParcelsCommandTest` (`it_imports_the_fixture_export_and_publishes_the_tileset`,
  `it_keeps_the_previous_parcel_store_...`, `it_prints_the_tile_statistics_...`,
  `it_reports_the_progress_of_the_downloads_and_conversions`): 4 failures, "Published tileset and parcel store ..."
  did not match the new regex, and no `search/` directory existed ("actual size 0 matches expected size 2").
- `SearchTextTest` (20 tests): `Class "ParcelMap\Domain\Search\SearchText" not found`.
- Unit `ImportParcelsUseCaseTest`, `ImportProgressUsingConsoleTest`, `ImportParcelsCommandTest`: fatal, the new fakes
  and the port did not exist yet (the suite died with "Premature end of PHP process").
- `SearchIndexBuilderUsingSqliteTest` (14 tests): `Class ...SearchIndexBuilderUsingSqlite not found`.
- `ParcelDataPublisherUsingSqliteTest`: fatal (`ArgumentCountError`, the publisher had no index parameter).

Deliberately changed existing tests:

- `tests/Api/ImportParcelsCommandTest`: the regexes for the last line now say "Published tileset, parcel store and
  search index"; the ordering assertion "parcel store line before the published line" became "search index line before
  the published line" (it is stricter: the index line follows the store line); the "keeps the previous store" test also
  counts two index files.
- `tests/Unit/Command/ImportParcelsCommandTest`: the same last line.
- `tests/Unit/Application/Import/ImportParcelsUseCaseTest`: the publisher record gained the index path; the constructor
  gains the builder fake; the progress events list gains `search index from 30 parcels`.
- `tests/Integration/.../ParcelDataPublisherUsingSqliteTest`: every `publish()` call passes an index; the
  constructor gets the third directory. New cases: index stamped and moved, index pruning, a failed index move removes
  the placed store, an unreadable index.

Decisions below the plan's level: `SearchIndexBuilderUsingSqlite` has its own small parcel-line parser (id, areaCode,
kind, baseNumber, subdivision, outer ring) instead of sharing code with `ParcelStoreBuilderUsingSqlite`, which stays
untouched; both reuse `InvalidParcelLine`. `SearchText::terms()` drops a part when it is one ASCII letter.

### Step 2: `feat(search): find parcels by number (#009)`

Red runs (each seen failing before the production code):

- Outer, `tests/Api/SearchTest` + `MapConfigurationTest` (20 tests run, 16 failures): every `GET /api/search/...` answered
  404 where 200, 400 or 405 was expected ("Failed asserting that 404 is identical to 200"), and the map configuration
  had no `searchUrl`.
- Unit: the whole suite died with missing classes (`SearchQuery`, `SearchMapUseCase`, ...); after the fakes existed
  `RequestTest` failed with "Undefined property: Request::$queryParameters" (3 errors) and the use case, handler,
  route, `Response::badRequest` and map configuration tests with missing classes/methods (79 errors, 13 failures in
  total).
- `SearchIndexRepositoryUsingSqliteTest` (17 tests): `Class ...SearchIndexRepositoryUsingSqlite not found`. One test
  was first written wrongly: it expected "5 r%" to find nothing, but `SearchText` turns "%" into a separator, so the
  letter "r" is ignored and the parcel is found. I corrected the test (renamed to
  `it_strips_like_wildcards_from_the_text_before_matching`) and added the cases that really test wildcards.
- `MapConfigurationTest::it_sends_no_search_url_when_the_search_index_of_the_current_version_is_missing` was written
  after the production code (the unit tests of the same behaviour were red first), so it was never red. I checked that
  it can fail: with the index copied into its temporary data dir it fails.
- `tests/Perf/search.spec.ts` first used Playwright's request context and failed on every query with a p95 of 41 to
  51 ms. Measured: PHP answers in 2 ms (PHP client in the app container), Node `fetch` in 3 ms, but Playwright's
  request context and Chromium need about 40 ms for any response of the built-in server, even a 404 and also against
  the dev app. The spec now uses Node's `fetch` (documented in the spec and README); p95 3.4 to 5.0 ms on the fixture.

Deliberately changed existing tests and support code:

- `GetMapConfigurationUseCaseTest`, `GetMapConfigurationHandlerTest`: the use case has a fourth constructor argument.
- `MapConfigurationTest`: the expected `parcels` gains `searchUrl`.
- `RequestTest`, `ResponseTest`: new cases only.
- `tests/Support/LocalHttpServer::start()` gained an optional `$environment` (merged into the environment of the
  server process); used by the new map-config case that serves a data dir without `search/`.
- `FixtureTileset::VERSION` and `FIXTURE_VERSION` in `tests/E2E/support/fixture.ts` changed to `20261124141357`.

Fixture data dir regenerated with the 006 procedure (temporary data dir in `var/`, fixture zip in its cache,
`RUIAN_EXPORT_BASE_URL=http://127.0.0.1:9/`): new version `20261124141357`; the old parcel store was deleted, the new
`parcels/`, `search/` files and the tileset copied. Checked: the tile data of all 22 tiles (md5 over zoom/x/y/data) and
the bounds, zoom range and export date in the metadata are identical to the old tileset; only the version differs.

Decisions below the plan's level: `SearchQuery` finds the parcel number first (the first token that is a valid
number), then the `st` marker only when the token directly before it is `st`; a second number-like token is a name
term. `SearchMapOutput::$places` is typed `list<never>` in this step (`PlaceResultOutput` arrives in step 4).
`SearchMapUseCase` calls the repository only for a text with a parcel number, as the task says, so a word-only query
against an unpublished index answers 200 with empty lists in this step (step 4's place search will throw
`SearchNotAvailable`). The `near` pattern in the handler is the task's regex; range errors come from `Coordinates`.


## Review

Filled in during the review loop (implement agent), one entry per round with findings.
