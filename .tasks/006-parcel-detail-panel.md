# 006: Parcel detail panel

- **Status:** done
- **Created:** 2026-11-23
- **Branch:** task/006-parcel-detail-panel
- **Request:** "task 006, the parcel detail panel — clicking a parcel on the map shows its details", the
  remaining explicit requirement of the assignment ("clicking a parcel shows its information"). The developer's
  decisions before planning, verbatim: "Lets keep the legent to another task. Decisions: 1: separate SQLite file.
  2: sure. 3: Lets keep in english for now. Later we will add a language switch and translations. 4: side panel,
  highlight the selected parcel, choose the minimum zoom. It makes sense, put the selected parcel in the URL. 5: out
  of scope for now". These mean:
  - a separate SQLite file of parcel attributes, written by `parcels:import`; attributes are not added to the tiles;
  - the panel shows the open-data attributes plus a link to the ČÚZK cadastre viewer for ownership;
  - English UI, designed so that a language switch can come later without rework;
  - a side panel, a highlight, a minimum zoom for selecting, and the selected parcel in the URL;
  - the legend, search, orthophoto, scheduled refresh and the `ProcessNotStarted` follow-up are out of scope.

  Answers to the plan's questions, verbatim: "1. OK, 2. yes, 3. alternative, 4. ok."
  - Q1: municipality names come from a new `municipality_name` column in `config/coverage.csv`.
  - Q2: the API returns RÚIAN codes; frontend catalogues map them to English names.
  - Q3 (alternative): an unknown land type or land use code does not fail the import. It is stored, and the panel
    shows "Unknown land type (code N)" / "Unknown land use (code N)".
  - Q4: `#parcel=<id>` with `history.replaceState`.

  Approval, verbatim: "one task approved. Just mention that in this version nothing is logged and some things are
  printed to stderr. In the production version, there would be a logger, logging every error and any other
  meaningful information".

## Goal

At zoom 14 and above, clicking a parcel opens a side panel with its details and highlights the parcel. The panel
shows:

- the parcel number (`st. 72`, `867/121`) and the plot type;
- the cadastral area (name and code) and the municipality;
- the land type and land use as English names;
- the area in m²;
- a link to the parcel in the ČÚZK cadastre viewer, which shows the owners and the ownership sheet.

The selected parcel is kept in the URL, so a link reopens it.

The details come from a **parcel store**: a SQLite file per tileset version, written by the import and published
together with the tileset. The tiles stay as they are (feature id + `landType`), so selecting a parcel costs one
small request and nothing in rendering.

## Scope

**In scope:**

- Coverage list with municipality names: `coverage:generate` output, `config/coverage.csv`, the domain
  `Municipality`.
- Domain model of a parcel: `ParcelId`, `ParcelKind`, `ParcelNumber`, `LandType`, `LandUse`, `Area`,
  `ParcelBoundary`, `Parcel`, `ParcelRepository`.
- Import:
  - the converter reads the extra attributes;
  - new port `ParcelStoreBuilder`;
  - `TilesetPublisher` becomes `ParcelDataPublisher`, which publishes the tileset and the parcel store atomically;
  - progress line, unknown-code warnings, new published line.
- `GET /api/parcels/{version}/{id}` with immutable caching; `parcelUrlTemplate` in `/api/map-config`.
- Frontend:
  - side panel with loading, not-found and error states;
  - highlight of the selected parcel;
  - ČÚZK cadastre viewer link;
  - `#parcel=<id>` in the URL;
  - zoom hint below zoom 14;
  - notice when parcel details are not imported.
- Fixtures: coverage CSVs, the converted GeoJSON fixture, and the fixture data dir (tileset + parcel store)
  regenerated.
- README, `config/README.md`, `tests/Fixtures/README.md` and CLAUDE.md (Glossary) updates.

**Out of scope:**

- The colour legend (a later task; the land type names added here are reused there).
- A language switch and translations: no i18n machinery is built.
- Search, orthophoto, scheduled refresh, the `ProcessNotStarted` adapter follow-up from task 005.
- Ownership data and LV numbers: they are not in the open data, so the panel links to the ČÚZK viewer instead.
- Hover effects (pointer cursor, hover highlight).
- Pushing a browser history entry per selection; the map position in the URL.
- **Logging.** In this version nothing is logged. The import prints its warnings (unknown codes) to stderr, and the
  HTTP server keeps reporting uncaught errors to stderr as before. A production version would have a logger that
  records every error and any other meaningful information (see Decisions 15).
- Editing the `done` task files 001 to 005.

## Decisions

1. **The parcel store is a separate SQLite file per tileset version**: `data/parcels/{version}.sqlite`
   (developer's decision 1). The tiles keep only the feature id (RÚIAN parcel id) and `landType`. Putting the
   attributes into the tiles would grow every tile, and smoothness is the main requirement.
2. **The tileset rename stays the single commit point of a publication.**
   - `ParcelDataPublisher` first places the store at `data/parcels/{version}.sqlite`. Nobody reads that path until
     a tileset carries the version.
   - It then renames the tileset onto `data/tiles/parcels.mbtiles` (atomic, as today). If that rename fails, it
     removes the store file it just placed.
   - Afterwards it deletes every store except the new one and the store of the previously live tileset. Requests in
     flight may still be reading the previous one.
   - So a reader never sees tiles without their store. The protocol lives in one adapter, because it is about files.
3. **Parcel details are served for the current tileset version only**: `GET /api/parcels/{version}/{id}`.
   - This is consistent with the tile endpoint: a page opened before a re-import gets 404 and is told to reload.
   - Responses are immutable for their version: `Cache-Control: public, max-age=31536000, immutable`.
   - One primary-key lookup per click (measured median 0.26 ms).
4. **The store is written by an import port, like the tileset.**
   - `ParcelStoreBuilder` (`ParcelStoreBuilderUsingSqlite`) builds the file from the converted GeoJSON. Paths stay
     opaque to the use case, as in task 002.
   - The read side is the domain repository `ParcelRepository` (`ParcelRepositoryUsingSqlite`).
   - The builder constructs a domain `Parcel` for every line, so invalid source data fails the import, not a click.
5. **Selection minimum zoom 14, the same constant as the outline layer.**
   - Below zoom 14 tiles leave out the smallest parcels (`--drop-smallest-as-needed`), and parcels are a pixel or
     two wide, so a click would hit the wrong parcel or nothing.
   - Below 14 a click does nothing and a hint says "Zoom in to select a parcel."
   - An existing selection stays (panel and highlight) when the user zooms out below 14.
6. **The highlight is drawn from the parcel boundary in the API response.**
   - It is a one-feature GeoJSON source `selected-parcel` with a white casing and a magenta line, above the parcel
     layers.
   - It is not done with feature-state or `setFilter` on the tile layers. Feature-state expressions would make the
     paint data of every parcel feature-dependent, and `setFilter` re-lays out every visible tile of the source.
   - The overlay works at every zoom and for a parcel opened from a link that is not yet in view.
   - Boundaries are stored zlib-compressed (district: 46 MB instead of 110 MB).
7. **The API returns RÚIAN codes, not names** (Q2). `landTypeCode` and `landUseCode` are numbers. The frontend maps
   them to English names in `landTypes.ts` and `landUses.ts`, keyed by code like the tile colours.
   - One catalogue serves later translations and the legend task.
   - The backend stays language-neutral.
8. **Unknown codes are kept** (Q3).
   - `LandType` and `LandUse` are value objects holding any positive RÚIAN code. `isKnown()` tells whether the code
     is in the published code list (2026-11-03).
   - A non-positive or missing code is invalid and fails the import.
   - The import counts unknown codes and prints one warning per code to stderr. The exit code stays 0.
   - The panel shows "Unknown land type (code N)" / "Unknown land use (code N)"; the fill uses the existing
     fallback colour.
   - `ParcelKind` (building / land, 2 values) stays strict: an unknown kind fails the import.
9. **The two known-code lists are pinned separately.** The PHP lists (in `LandType` / `LandUse`) and the frontend
   catalogues are each pinned by their own test to the verified code lists. Nothing cross-checks them, so a
   code-list change must update both by hand. README "With more time" notes that one source could generate the
   other.
10. **English UI, ready for translations.**
    - Every panel string lives in `frontend/src/text.ts`; number formatting uses the locale constant `UI_LOCALE`
      there.
    - Land type and land use names live in their catalogues.
    - A later language switch replaces these lookups and does not rewrite the components.
    - Czech official names appear only as code comments next to codes, in the Glossary and in this file.
    - The parcel number keeps the official `st.` notation, which is cadastral notation rather than a translatable
      word.
    - The existing strings in `main.ts` (error, no-data notice) are not moved.
11. **Municipality names come from the coverage list** (Q1).
    - `config/coverage.csv` gains `municipality_name`, generated from the ST_UZSZ `Obce` layer. This costs nothing
      at import time.
    - The alternative, reading `Obce` from each municipality export, re-parses 111 files of about 38 MB each.
    - `CadastralArea` holds a `Municipality` (code + name).
12. **URL.**
    - The selected parcel is `#parcel=<id>` in the hash, written as key=value pairs, so a later `map=` position can
      sit beside it.
    - It is updated with `history.replaceState`, so no history entry per click (Q4).
    - The link carries the id only, not the tileset version, so it survives re-imports. RÚIAN ids stay stable while
      a parcel exists.
    - Opening a link fetches the parcel before the map is created, and the map opens fitted to the parcel with no
      zoom-12 tiles loaded first.
    - An unknown id opens the panel with the not-found message on the default view.
    - A `hashchange` selects the new parcel; closing the panel removes the hash.
    - Without parcel data or without parcel details the hash is ignored and left in the URL.
13. **Interaction.**
    - Clicking another parcel (zoom ≥ 14) switches the selection. Clicking the selected parcel again does nothing.
    - Clicking where there is no parcel (zoom ≥ 14) clears the selection, as do the close button and Escape.
    - A click while a request is in flight aborts it.
    - No hover cursor: MapLibre's `mouseenter` on a layer queries rendered features on every mouse move.
    - The only map and DOM listeners added are `click`, `zoomend`, `hashchange`, `keydown` and the close button;
      still no `move` or `render` listener.
    - Clicking a parcel does not move the map. Opening by link or `hashchange` fits the map to the parcel.
14. **Panel placement.**
    - Wide screens: a panel on the left, 20 rem wide, full height minus margins.
    - Screens up to 640 px wide: at the top, leaving the zoom buttons free (`right: 3.5rem`), at most half the
      height.
    - The panel overlays the map. It never resizes the map container, so selecting causes no map resize.
15. **No logging in this version** (developer's note). Warnings and errors go to stderr: the import's unknown-code
    warnings (new) and the HTTP kernel's `StderrErrorReporter` (existing). A production version would have a
    logger that records every error and any other meaningful information. Step 2 adds this sentence to the README
    Notes.
16. **When parcel details are not imported** (a tileset from before this task, or a deleted store):
    - `/api/map-config` sends `parcelUrlTemplate: null`;
    - the page shows "Parcel details have not been imported yet. Run make data, then reload this page.";
    - clicking selects nothing and no hint is shown.

    Without any parcel data, the existing notice stays, and the hash is ignored.
17. **The fixture data dir is regenerated once (step 3)** by the import, as task 002 describes. This gives a new
    fixture version, so the E2E version constant moves into one support module, and the PHP constant stays in
    `FixtureTileset`. Bounds and tile facts must not change (same input). If they do, update the constants and log
    it.

## Verified facts

Verified on 2026-11-23 with real data in throwaway containers of the `parcel-map-app` image (GDAL 3.10.3,
tippecanoe 2.53.0), unless noted. Facts of tasks 002 and 003 still hold.

**ČÚZK cadastre viewer (Nahlížení do katastru nemovitostí)**

- Parcel page by RÚIAN parcel id: `https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id={Id}`.
  - A real request for `id=1753295604` answered 200 "Informace o pozemku | Nahlížení do katastru nemovitostí"
    with "Parcelní číslo: st. 72, Obec: Jičín [572659], Katastrální území: Jičín [659541], Číslo LV: 3651,
    Výměra [m2]: 455, Druh pozemku: zastavěná plocha a nádvoří".
  - The owners appear after "se přihlaste nebo vyřešte CAPTCHA" (log in or solve a CAPTCHA).
  - `id=64343200010` answered "st. 79/1".
- ČÚZK publishes no documentation page for this URL. The evidence is the live service and pages in exactly this
  format indexed on ČÚZK's domain (e.g.
  `https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=36449754010`).
- Scripted requests soon get a 302 to a Radware bot check (`validate.perfdrive.com`), and the old domain
  `nahlizenidokn.cuzk.cz` does the same. **Tests never call the viewer**; they assert the URL only.

**RÚIAN export fields** (`ogrinfo` of `20261031_OB_572659_UKSH.xml.zip`, layer `Parcely`)

- `Id` Integer64, `KmenoveCislo` Integer, `PododdeleniCisla` Integer, `VymeraParcely` Integer64,
  `ZpusobyVyuzitiPozemku` Integer (one value), `DruhCislovaniKod` Integer, `DruhPozemkuKod` Integer,
  `KatastralniUzemiKod` Integer. Geometry `OriginalniHranice` is typed **Polygon** (`OriginalniHraniceOmpv` is a
  separate MultiPolygon column that is not used).
- `ogr2ogr -f GeoJSONSeq` **omits null properties**: the key is missing, not `null`. The properties follow the
  order of the SELECT. With the step 2 query, a line for st. 72 is
  `{ "type": "Feature", "properties": { "id": 1753295604, "landType": 13, "areaCode": 659541, "kind": 1, "baseNumber": 72, "area": 455 }, "geometry": { "type": "Polygon", … } }`
  (no `subdivision`, no `landUse`). The prototype used the alias `numbering` for `kind`; the values were the same.
- Conversion of all 111 cached municipality files with the extended query
  (`SELECT Id AS id, DruhPozemkuKod AS landType, KatastralniUzemiKod AS areaCode, DruhCislovaniKod AS kind,
  KmenoveCislo AS baseNumber, PododdeleniCisla AS subdivision, ZpusobyVyuzitiPozemku AS landUse,
  VymeraParcely AS area, OriginalniHranice FROM Parcely …`): 23.8 s (22 s before), 148 MB GeoJSON (131 MB
  before).

**District 3604, export of 2026-10-31** (272,011 parcels)

- Ids 1,676,640,604 to 106,079,970,010, all unique, all below 2^53 − 1.
- Parcels by kind: 44,052 building and 227,959 land.
- Base number 1 to 4,708; subdivision 1 to 417, missing on 83,446 parcels.
- Area 1 to 2,010,093 m², never missing.
- Land use missing on 203,427 parcels.
- (cadastral area, kind, base, subdivision) is unique.
- Land types present: 2, 4, 5, 6, 7, 10, 11, 13, 14.
- Land uses present: every code 1 to 30 except 15 and 24.
- Geometry: all Polygons; 11,260 have holes; at most 1,131 vertices; at most 26,909 bytes of coordinate JSON;
  82.8 MB of coordinate JSON in total.

**Code lists** (`https://vdp.cuzk.gov.cz/vymenny_format/specialni/20261103_ST_UCIS.xml.zip`, 64,581 bytes; later
daily files up to 2026-11-23 answer 404)

- `DruhyPozemku` (land type, `DruhPozemkuKod`): 2 orná půda, 3 chmelnice, 4 vinice, 5 zahrada, 6 ovocný sad,
  7 trvalý travní porost, 8 trvalý travní porost, 10 lesní pozemek, 11 vodní plocha, 13 zastavěná plocha a nádvoří,
  14 ostatní plocha.
- `ZpusobyVyuzitiPozemku` (land use):
  - 1 skleník, pařeniště; 2 školka; 3 plantáž dřevin; 4 les jiný než hospodářský;
    5 lesní pozemek, na kterém je budova;
  - 6 rybník; 7 koryto vodního toku přirozené nebo upravené; 8 koryto vodního toku umělé;
    9 vodní nádrž přírodní; 10 vodní nádrž umělá; 11 zamokřená plocha;
  - 12 společný dvůr; 13 zbořeniště; 14 dráha; 15 dálnice; 16 silnice; 17 ostatní komunikace;
    18 ostatní dopravní plocha;
  - 19 zeleň; 20 sportoviště a rekreační plocha; 21 pohřebiště; 22 kulturní a osvětová plocha;
  - 23 manipulační plocha; 24 dobývací prostor; 25 skládka; 26 jiná plocha; 27 neplodná půda;
  - 28 vodní plocha, na které je budova; 29 fotovoltaická elektrárna; 30 mez, stráň.
- `DruhyCislovaniParcel`: 1 Stavební, 2 Pozemková.

**Parcel store prototype** (PHP 8.6 in the app image, the 111 converted files)

- One transaction, `journal_mode = OFF`, `synchronous = OFF`, a `Coordinates`-like object built per vertex:
  - boundaries as zlib (`gzcompress`) BLOB: 5.8 s, 45.9 MB;
  - as JSON text: 3.8 s, 109.8 MB;
  - peak memory 2 MB.
- Lookup per request (open read-only with `Pdo\Sqlite`, `SELECT … WHERE id = ?`, `gzuncompress`, `json_decode`),
  1,000 random ids: median 0.256 ms, p99 0.466 ms, max 2.2 ms. `EXPLAIN QUERY PLAN`:
  `SEARCH parcels USING INTEGER PRIMARY KEY (rowid=?)`.

**Coverage with municipality names**

- `SELECT Kod, Nazev FROM Obce WHERE OkresKod = 3604` on `20261031_ST_UZSZ.xml.zip` prints the header `Kod,Nazev`
  and 111 rows (e.g. `549312,Kacákova Lhota`). No name contains a comma or a quote.
- The same query on the fixture ST_UZSZ gives `572659,Jičín` and `572675,Běchary`.
- `config/coverage.csv` with the 4th column `municipality_name` (format of task 003 Decision 3) has 241 lines and
  **sha256 `333b92724b79dd4749078928769198f812d1b1432ce2e56a1e535e35145bef98`**. Sample rows:
  `601101,Bašnice,572667,Bašnice`, `601462,Běchary,572675,Běchary`, `659541,Jičín,572659,Jičín`,
  `740225,Robousy,572659,Jičín`.

**Fixture parcels** (`tests/Fixtures/ruian/…572659…zip` and `…572675…zip`; WGS84 from the converter output)

| Id | Area | Kind | Number | Land type | Land use | m² | Note |
|---|---|---|---|---|---|---|---|
| 1753295604 | 659541 | 1 | st. 72 | 13 | — | 455 | 1 ring, 32 positions, first `[15.3509779, 50.4377444]`; bounds W 15.3509216, S 50.4372305, E 15.3511404, N 50.437745; definition point lon 15.3510535, lat 50.4373542 (tile 14/8890/5525, 16/35562/22101) |
| 1761441604 | 659541 | 2 | 1171 | 14 | 17 | 13077 | 3 rings, outer 113 positions; definition point lon 15.3517724, lat 50.4368209 |
| 2654592604 | 659541 | 2 | 2162 | 14 | 26 | 15 | |
| 1753304604 | 659541 | 1 | st. 80/2 | 13 | — | 183 | |
| 99225194010 | 740225 | 2 | 867/121 | 14 | 26 | 725 | Robousy |
| 97329743010 | 601462 | 2 | 1198/3 | 14 | 17 | 43 | Běchary (572675) |

The fixture has no unknown code; the unknown-code tests use temporary files.

**MapLibre 6.19.0** (`node_modules/maplibre-gl/dist/maplibre-gl.d.ts`)

- `MapOptions.bounds` "overrides `center` and `zoom`"; `MapOptions.fitBoundsOptions` applies to it.
- `Map#queryRenderedFeatures(point, { layers })` returns `MapGeoJSONFeature` with `id`.
- `GeoJSONSource#setData(data)`; `clickTolerance` defaults to 3 px.

## Steps

The first commit on the branch is this task file (`docs(tasks): add 006 Parcel detail panel`). Each step below is
one commit, and every commit passes `make check`. Write the outer test of each step first (CLI/HTTP API test or
E2E) and watch it fail for the right reason.

1. **Coverage with municipality names** (`feat(coverage): record the municipality name of each covered area (#006)`).
   The operator runs `make coverage` and gets `code,name,municipality_code,municipality_name`.
   - Adds the domain `Municipality`; `CadastralArea` holds it.
   - Updates the fixture CSVs.
   - Regenerates `config/coverage.csv`:
     `docker compose run --rm --no-deps app bin/console coverage:generate --district=3604 --export-date=2026-10-31`.
     The cached `data/cache/ruian/20261031_ST_UZSZ.xml.zip` is used if present, otherwise it is downloaded (listed
     until about February 2027). The sha256 must be `333b9272…bef98`; if it differs, stop and report.
   - Updates `config/README.md`.
2. **The import publishes a parcel store** (`feat(import): publish a parcel store with each tileset (#006)`).
   `make data` writes `data/parcels/{version}.sqlite` next to the tileset under the same version.
   - It prints "Building the parcel store from N parcels.", warns about unknown codes on stderr, and ends with
     "Published tileset and parcel store V with N parcels."
   - Includes the domain parcel types, `Coverage::area()`, the builder, and the publisher rename to
     `ParcelDataPublisher`.
   - The converter reads the extra attributes; the converted GeoJSON fixture is regenerated.
   - README Data section and Notes (logging sentence, Decision 15); Glossary.
3. **Parcel details endpoint** (`feat(parcel): serve the details of a parcel (#006)`).
   `GET /api/parcels/{version}/{id}` returns the details JSON (with the boundary); `/api/map-config` `parcels`
   gains `parcelUrlTemplate`.
   - Repository, use case, handler, route.
   - The fixture data dir is regenerated (Decision 17).
4. **Side panel** (`feat(map): show the details of a clicked parcel in a side panel (#006)`).
   - Clicking a parcel at zoom ≥ 14 opens the panel: loading, details, not found, error.
   - Close button, Escape, a click on no parcel and a click on another parcel behave as in Decision 13.
   - The land type and land use catalogues and `text.ts` are added here.
5. **Highlight** (`feat(map): highlight the selected parcel (#006)`). The selected parcel is outlined from its
   boundary at every zoom.
6. **Cadastre viewer link** (`feat(parcel): link a parcel to the ČÚZK cadastre viewer (#006)`).
   `Parcel::cadastreViewerUrl()` feeds the API field `cadastreViewerUrl` and the panel link.
7. **Selected parcel in the URL** (`feat(map): keep the selected parcel in the URL (#006)`).
   - Adds `#parcel=<id>`, link opening fitted to the parcel, and `hashchange` handling.
   - The API gains `bounds` (`ParcelBoundary::bounds()`).
8. **When selection is not possible** (`feat(map): explain when parcels cannot be selected (#006)`).
   - The zoom hint below zoom 14.
   - `ParcelRepository::isPublished()`; `parcelUrlTemplate: null` when the store of the current version is missing;
     the details-not-imported notice.

## Architecture

Paths and namespaces are binding. Method bodies, private helpers and test code are left to the implementation.
Conventions from earlier tasks apply:

- value objects are `final readonly` with `equals()`;
- exceptions are `final class … extends \InvalidArgumentException` (invalid values) or `\RuntimeException`
  (failures), with named constructors that build the message;
- `Pdo\Sqlite` with `Pdo\Sqlite::ATTR_OPEN_FLAGS => Pdo\Sqlite::OPEN_READONLY` for reading, never the deprecated
  `PDO::SQLITE_*` constants.

### Domain

All new classes are in **`ParcelMap\Domain\Cadastre`**. Some use `Domain\Map\Coordinates`, `Bounds` and
`TilesetVersion`.

| Class | Step | Constructor / factories | Invariants and behaviour |
|---|---|---|---|
| `Municipality` | 1 | `__construct(public MunicipalityCode $code, public string $name)` | `trim(name) !== ''` → `InvalidMunicipality::emptyName(int $code)`. `equals()` compares the code. |
| `CadastralArea` (changed) | 1 | `__construct(public CadastralAreaCode $code, public string $name, public Municipality $municipality)` | unchanged otherwise |
| `Coverage` (changed) | 1, 2 | unchanged | Step 1: two areas with the same municipality code must have the same municipality name → `InvalidCoverage::inconsistentMunicipality(int $code)`. `municipalities()` still returns `non-empty-list<MunicipalityCode>` (from `$area->municipality->code`); `areasOf(MunicipalityCode)` compares `$area->municipality->code`. Step 2: `area(CadastralAreaCode $code): CadastralArea` throws `CadastralAreaNotCovered::code(int)` (`InvalidArgumentException`, like `MunicipalityNotCovered`). |
| `ParcelId` | 2 | `__construct(public int $value)` | `1 ≤ value ≤ 9007199254740991` → `InvalidParcelId::outOfRange(int)`. The id is a vector tile feature id and a JSON number in the browser; both are exact only up to 2^53 − 1 (say so in a class comment). `equals()`. |
| `ParcelKind` | 2 | `enum ParcelKind: int { case Building = 1; case Land = 2; }`; `static fromCode(int $code): self` | unknown code → `InvalidParcelKind::unknownCode(int)` (comment: `DruhCislovaniKod`, 1 stavební, 2 pozemková). |
| `ParcelNumber` | 2 | `__construct(public ParcelKind $kind, public int $baseNumber, public ?int $subdivision)` | `baseNumber ≥ 1` → `InvalidParcelNumber::baseNumberNotPositive(int)`; `subdivision` null or `≥ 1` → `InvalidParcelNumber::subdivisionNotPositive(int)`. `toString()`: `st. 72`, `st. 80/2` (building), `1171`, `867/121` (land). `equals()`. |
| `LandType` | 2 | `__construct(public int $code)` | `code ≥ 1` → `InvalidLandType::notPositive(int)`. `isKnown(): bool`: the code is in the private constant list `[2, 3, 4, 5, 6, 7, 8, 10, 11, 13, 14]` (each with its Czech official name as a comment). `equals()`. Class comment: `DruhPozemkuKod`; unknown positive codes are kept (Decision 8). |
| `LandUse` | 2 | `__construct(public int $code)` | `code ≥ 1` → `InvalidLandUse::notPositive(int)`. `isKnown()`: codes 1 to 30 (each with its Czech name as a comment). `equals()`. Comment: `ZpusobyVyuzitiPozemku`. |
| `Area` | 2 | `__construct(public int $squareMetres)` | `≥ 1` → `InvalidArea::notPositive(int)`. `equals()`. |
| `ParcelBoundary` | 2, 7 | private constructor; `static fromRings(array $rings): self` with `@param list<list<Coordinates>> $rings` (outer ring first, then holes, WGS84) | at least one ring → `InvalidParcelBoundary::noRings()`; every ring has ≥ 4 positions → `::ringTooShort(int $ring)`; first equals last → `::ringNotClosed(int $ring)`. `rings(): non-empty-list<non-empty-list<Coordinates>>`. Step 7: `bounds(): Bounds` = min/max of the outer ring. |
| `Parcel` (entity) | 2, 6 | `__construct(public ParcelId $id, public CadastralArea $cadastralArea, public ParcelNumber $number, public LandType $landType, public ?LandUse $landUse, public Area $area, public ParcelBoundary $boundary)` | `equals()` compares the id. Step 6: `cadastreViewerUrl(): string` = `sprintf(self::CADASTRE_VIEWER_URL, $this->id->value)` with `private const string CADASTRE_VIEWER_URL = 'https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=%d'` (comment: ČÚZK's public viewer shows the owners and the LV number, which are not in the open data). |

Repository (step 3, extended in step 8):

```php
interface ParcelRepository
{
    /**
     * @throws ParcelNotFound          no parcel with this id in the store of this version
     * @throws ParcelStoreNotPublished there is no store for this version
     * @throws ParcelStoreUnreadable
     */
    public function get(TilesetVersion $version, ParcelId $id): Parcel;

    /** Step 8. */
    public function isPublished(TilesetVersion $version): bool;
}
```

Domain exceptions (step 3, all `\RuntimeException`):

- `ParcelNotFound::withId(int $id)`;
- `ParcelStoreNotPublished::forVersion(string $version)`;
- `ParcelStoreUnreadable::because(string $reason)` (becomes a 500 through the kernel).

### Application

**Import** (`ParcelMap\Application\Import`, step 2)

New port and handles:

```php
interface ParcelStoreBuilder
{
    /**
     * Builds the parcel store of all converted parcels; areas and municipality names come from the coverage.
     *
     * @param non-empty-list<ConvertedParcels> $parcels
     * @throws ParcelStoreBuildFailed
     */
    public function build(array $parcels, Coverage $coverage): BuiltParcelStore;
}
```

- `final readonly class BuiltParcelStore(public string $path, public array $unknownCodes)`, with
  `@param list<UnknownCode> $unknownCodes`. The path is opaque to the use case.
- `enum CodeList: string { case LandType = 'land type'; case LandUse = 'land use'; }`.
- `final readonly class UnknownCode(public CodeList $list, public int $code, public int $parcelCount)`.
  `parcelCount ≥ 1` → `InvalidUnknownCode::noParcels(int $code)` (`InvalidArgumentException`, in this namespace).
- `ParcelStoreBuildFailed extends ImportFailed` with `::because(string $reason)` and
  `::invalidParcel(string $path, int $line, string $reason)`. The latter's message is
  `"<path> line <n>: <reason>"`.

Renamed port (the tileset publisher becomes the publisher of parcel data):

| Old | New |
|---|---|
| `Application\Import\TilesetPublisher` | `Application\Import\ParcelDataPublisher` |
| `Application\Import\TilesetPublishFailed` | `Application\Import\ParcelDataPublishFailed` (`::because(string)`) |
| `Infrastructure\Import\TilesetPublisherUsingMbtiles` | `Infrastructure\Import\ParcelDataPublisherUsingSqlite` |
| `tests/Fake/TilesetPublisherUsingMemory` | `tests/Fake/ParcelDataPublisherUsingMemory` |
| `tests/Integration/Infrastructure/Import/TilesetPublisherUsingMbtilesTest` | `…/ParcelDataPublisherUsingSqliteTest` |

```php
interface ParcelDataPublisher
{
    /**
     * Publishes the tileset and the parcel store of one import under one version. Readers see either the old or the
     * new parcel data, never a tileset without its store.
     *
     * @throws ParcelDataPublishFailed
     */
    public function publish(BuiltTileset $tileset, BuiltParcelStore $store, TilesetVersion $version, ExportDate $exportDate): void;
}
```

`ImportProgress` gains `parcelStoreBuildStarted(int $parcelCount): void`.

`ImportParcelsUseCase`:

- Constructor: `CoverageRepository $coverage, RuianExportDownloader $downloader, ParcelConverter $converter,
  TilesetBuilder $builder, TilesetInspector $inspector, ParcelStoreBuilder $storeBuilder,
  ParcelDataPublisher $publisher, Clock $clock, TileSizeBudget $budget`.
- After the budget gate (an over-budget tileset still builds no store and publishes nothing):
  1. `parcelStoreBuildStarted($totalParcels)`;
  2. `$store = $storeBuilder->build($converted, $coverage)`;
  3. version from the clock;
  4. `$publisher->publish($tileset, $store, $version, $exportDate)`.
- Step 1 ripple: `parcelCount()` compares `$parcels->municipality` with `$area->municipality->code`.
- `ImportParcelsOutput` gains a last constructor parameter `public array $unknownCodes`, with
  `@param list<UnknownCodeOutput> $unknownCodes` in the builder's order.
- `final readonly class UnknownCodeOutput(public string $codeList /* 'land type' | 'land use' */, public int $code, public int $parcelCount)`.

**Parcel** (`ParcelMap\Application\Parcel`, new, step 3)

Use case `final readonly class GetParcelDetailUseCase`:

- Constructor: `(ParcelTilesetRepository $tilesets, ParcelRepository $parcels)`.
- `execute(ParcelDetailInput $input): ParcelDetailOutput`, `@throws ParcelDetailNotFound`.
- Logic:
  1. `$tilesets->current()`: `ParcelDataNotImported` → `ParcelDetailNotFound::noDataImported()`.
  2. `new TilesetVersion($input->version)`: invalid, or not equal to the current version →
     `ParcelDetailNotFound::unknownVersion(string)`.
  3. `new ParcelId($input->parcelId)`: invalid → `ParcelDetailNotFound::unknownParcel(int)`.
  4. `$parcels->get($tileset->version, $id)`:
     - `ParcelNotFound` → `unknownParcel(int)`;
     - `ParcelStoreNotPublished` → `ParcelDetailNotFound::detailsNotImported(string $version)`;
     - `ParcelStoreUnreadable` and `ParcelTilesetUnreadable` propagate (500).
  5. Map the parcel to the output.

DTOs:

- `final readonly class ParcelDetailInput(public string $version, public int $parcelId)`.
- `ParcelDetailNotFound extends \RuntimeException` (named constructors above).
- `ParcelDetailOutput`:

```php
final readonly class ParcelDetailOutput
{
    /**
     * @param 'building'|'land' $kind
     * @param list<list<array{0: float, 1: float}>> $boundaryRings [longitude, latitude] positions, outer ring first
     */
    public function __construct(
        public int $id,
        public string $parcelNumber,        // ParcelNumber::toString(), e.g. "st. 72"
        public string $kind,
        public int $cadastralAreaCode,
        public string $cadastralAreaName,
        public int $municipalityCode,
        public string $municipalityName,
        public int $landTypeCode,
        public ?int $landUseCode,
        public int $areaSquareMetres,
        public array $boundaryRings,
        // step 6:
        public string $cadastreViewerUrl,
        // step 7:
        public float $west,
        public float $south,
        public float $east,
        public float $north,
    ) {}
}
```

Fields are appended in the step that introduces them.

**Map** (`ParcelMap\Application\Map`, step 8)

- `GetMapConfigurationUseCase` constructor gains `ParcelRepository $parcels` (third parameter).
- `ParcelLayerOutput` gains a last parameter `public bool $detailsAvailable` = `$parcels->isPublished($tileset->version)`.

### Infrastructure

**`Infrastructure\Coverage\DistrictAreaExtractorUsingOgr2ogr`** (step 1)

- Query 1 becomes `SELECT Kod, Nazev FROM Obce WHERE OkresKod = %d` with the expected header `['Kod', 'Nazev']`.
- The municipality names are kept by code.
- Query 2 is unchanged (it uses the codes of query 1).
- Each area is built with `new Municipality(new MunicipalityCode(ObecKod), name of that code)`.
- An `ObecKod` missing from query 1, or a non-numeric code → `StructureReadFailed`.
- Update the class comment.

**`Infrastructure\Cadastre\CoverageRepositoryUsingCsv`** (step 1)

- `HEADER = ['code', 'name', 'municipality_code', 'municipality_name']`. Reading requires exactly 4 fields
  (`expected 4 fields, found N`).
- Writing quotes `name` and `municipality_name` by the existing rule. Rows stay in coverage order.
- Update the class comment.

**`Infrastructure\Import\ParcelConverterUsingOgr2ogr`** (step 2)

- New SQL:

  ```
  SELECT Id AS id, DruhPozemkuKod AS landType, KatastralniUzemiKod AS areaCode, DruhCislovaniKod AS kind,
         KmenoveCislo AS baseNumber, PododdeleniCisla AS subdivision, ZpusobyVyuzitiPozemku AS landUse,
         VymeraParcely AS area, OriginalniHranice
  FROM Parcely WHERE KatastralniUzemiKod IN (…)
  ```

- Everything else is unchanged. Update the class comment: the extra attributes are for the parcel store; the tile
  builder keeps only `landType`.

**`Infrastructure\Import\TilesetBuilderUsingTippecanoe`**: no code change. `--include=landType` already drops the
new properties. A new integration assertion guards that the tiles carry only `landType`.

**`Infrastructure\Import\ParcelStoreBuilderUsingSqlite(string $workDir)`** implements `ParcelStoreBuilder` (step 2)

- Output `$workDir/parcels.sqlite`; an existing file is deleted first, and the directory is created if missing.
- Opened read-write with `new Pdo\Sqlite('sqlite:' . $path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])`.
  `PRAGMA journal_mode = OFF` and `PRAGMA synchronous = OFF` are set, with a comment: a build file that is not yet
  published is simply rebuilt if the import dies.
- Schema (one transaction for all inserts):

  ```sql
  CREATE TABLE metadata (name TEXT PRIMARY KEY, value TEXT NOT NULL);
  CREATE TABLE cadastral_areas (
      code INTEGER PRIMARY KEY,
      name TEXT NOT NULL,
      municipality_code INTEGER NOT NULL,
      municipality_name TEXT NOT NULL
  );
  CREATE TABLE parcels (
      id INTEGER PRIMARY KEY,                 -- RÚIAN Id (rowid alias: lookup by primary key)
      cadastral_area_code INTEGER NOT NULL REFERENCES cadastral_areas (code),
      kind INTEGER NOT NULL,                  -- DruhCislovaniKod: 1 building, 2 land
      base_number INTEGER NOT NULL,
      subdivision INTEGER,                    -- NULL when the number has none
      land_type INTEGER NOT NULL,             -- DruhPozemkuKod, known or not
      land_use INTEGER,                       -- ZpusobyVyuzitiPozemku, NULL when none
      area INTEGER NOT NULL,                  -- square metres
      boundary BLOB NOT NULL                  -- gzcompress(JSON of the Polygon coordinates [[[lon, lat], …], …])
  );
  ```

  `metadata` stays empty until the publisher stamps it.
- `cadastral_areas` gets one row per coverage area.
- **Parsing.** Every non-blank line of every converted file (in the given order) is decoded with
  `JSON_THROW_ON_ERROR`.
  - Required integer properties: `id`, `areaCode`, `kind`, `baseNumber`, `landType`, `area`.
  - Optional integer properties: `subdivision`, `landUse`. They are optional because GDAL omits null properties.
  - The geometry must be `type: Polygon` with numeric `[lon, lat]` positions.
- **Building.** The adapter builds `Parcel`: `ParcelId`, `$coverage->area(new CadastralAreaCode(areaCode))`,
  `ParcelNumber(ParcelKind::fromCode(kind), baseNumber, subdivision)`, `new LandType(landType)`,
  `landUse === null ? null : new LandUse(landUse)`, `Area`, and `ParcelBoundary::fromRings` of `Coordinates`.
- **Insert.** One row per parcel; the boundary is `gzcompress(json_encode(rings as [[lon, lat], …]))` from the
  domain boundary.
- **Unknown codes.** A `LandType` or `LandUse` with `!isKnown()` is counted per code. The result lists
  `UnknownCode`s sorted by list (land type first) and code.
- **Failures.**
  - A JSON error, a missing or mistyped property, a non-Polygon geometry, any domain exception
    (`InvalidArgumentException`) or a duplicate id (`PDOException` on the primary key) →
    `ParcelStoreBuildFailed::invalidParcel(path, line, reason)`.
  - I/O or other database failures → `ParcelStoreBuildFailed::because(...)`.
  - On failure the partial output file is deleted.
- Returns `new BuiltParcelStore($path, $unknownCodes)`.
- Class comment: one domain `Parcel` per line, so invalid source data fails the import, not a click; boundaries
  are compressed (district 46 MB instead of 110 MB).

**`Infrastructure\Import\ParcelDataPublisherUsingSqlite(string $tilesetFile, string $parcelStoreDirectory)`**
implements `ParcelDataPublisher` (step 2, replaces `TilesetPublisherUsingMbtiles`)

1. Stamp the built tileset: `INSERT OR REPLACE INTO metadata (name, value)` the rows `parcel_map_version` and
   `parcel_map_export_date`, as today.
2. Stamp the built store's `metadata` with the same two rows.
3. Read the live version: if `$tilesetFile` exists, read its `parcel_map_version` read-only. Any failure means
   there is no live version.
4. Create `$parcelStoreDirectory` if missing; `rename()` the store to `{dir}/{version}.sqlite`.
5. Create the tileset directory if missing; `rename()` the tileset onto `$tilesetFile`. This is the commit point.
   On failure, `unlink()` the store file of step 4, then throw.
6. Prune: delete every `{dir}/*.sqlite` except `{version}.sqlite` and `{live version}.sqlite`. Failures here are
   ignored on purpose, with a comment: the new data is already live, and a leftover file is harmless.

Any failure in steps 1–5 → `ParcelDataPublishFailed::because(...)`. The class comment explains the protocol
(Decision 2). Version strings are safe as file names: `TilesetVersion` allows only `[A-Za-z0-9-]`.

**`Infrastructure\Cadastre\ParcelRepositoryUsingSqlite(string $directory)`** implements `ParcelRepository` (step 3)

- `get()`:
  - File `{directory}/{version}.sqlite`; missing → `ParcelStoreNotPublished`.
  - Opened read-only per call (same reason as the MBTiles reader: a re-import replaces files).
  - Query:

    ```sql
    SELECT p.id, p.kind, p.base_number, p.subdivision, p.land_type, p.land_use, p.area, p.boundary,
           a.code, a.name, a.municipality_code, a.municipality_name
    FROM parcels p JOIN cadastral_areas a ON a.code = p.cadastral_area_code
    WHERE p.id = ?
    ```

  - No row → `ParcelNotFound`.
  - Builds the domain objects; `gzuncompress` returning false, a JSON error, `PDOException` or a domain exception →
    `ParcelStoreUnreadable::because(...)`.
- `isPublished()` (step 8): `is_file` of that path.

### Handlers (HTTP)

**`Handler\ParcelDetailRoute`** (step 3): `final class`, private constructor,
`public const string PATTERN = '/api/parcels/{version}/{id}'`,
`public static function urlTemplate(string $version): string` (replaces `{version}`, keeps `{id}`).

**`GET /api/parcels/{version}/{id}`** → `GetParcelDetailHandler(GetParcelDetailUseCase)` (step 3)

- `id` must be `ctype_digit` with at most 15 digits; otherwise 404. Then `ParcelDetailInput(version, (int) id)`.
- Results:

  | Case | Status | Headers | Body |
  |---|---|---|---|
  | found | 200 | `Content-Type: application/json`, `Cache-Control: public, max-age=31536000, immutable` | details JSON |
  | `ParcelDetailNotFound` (no data, unknown version, details not imported, unknown or invalid id) or bad digits | 404 | `Content-Type: application/json` | `{"error":"not_found"}` |
  | other method | 405 | `Allow: GET` | `{"error":"method_not_allowed"}` |
  | `ParcelStoreUnreadable` / `ParcelTilesetUnreadable` | 500 (kernel) | | `{"error":"internal_error"}` |

- Body for the fixture parcel st. 72 after step 7 (`boundary` shortened here). `boundary` is a GeoJSON Polygon
  built from `boundaryRings`. `landUseCode` is `null` when there is none. Step 3 has every member up to
  `boundary`, step 6 adds `cadastreViewerUrl`, step 7 adds `bounds`.

  ```json
  {
    "id": 1753295604,
    "parcelNumber": "st. 72",
    "kind": "building",
    "cadastralArea": { "code": 659541, "name": "Jičín" },
    "municipality": { "code": 572659, "name": "Jičín" },
    "landTypeCode": 13,
    "landUseCode": null,
    "areaSquareMetres": 455,
    "boundary": { "type": "Polygon", "coordinates": [[[15.3509779, 50.4377444], [15.3509661, 50.437745], "…"]] },
    "cadastreViewerUrl": "https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id=1753295604",
    "bounds": { "west": 15.3509216, "south": 50.4372305, "east": 15.3511404, "north": 50.437745 }
  }
  ```

**`GET /api/map-config`**:

- Step 3: `parcels` gains `"parcelUrlTemplate": "/api/parcels/<version>/{id}"` (from `ParcelDetailRoute::urlTemplate`).
- Step 8: `"parcelUrlTemplate": null` when `detailsAvailable` is false.
- Everything else is unchanged.

**Route table** (Container): step 3 adds `GET ParcelDetailRoute::PATTERN`.

### Commands (CLI)

**`bin/console coverage:generate`** (step 1): unchanged arguments and output; the written file has the 4th column.

**`bin/console parcels:import`** (step 2):

- Description: `Download the RÚIAN export and build the parcel tileset and parcel store.`
- Progress (stdout, `ImportProgressUsingConsole::parcelStoreBuildStarted`): `Building the parcel store from 25 parcels.`
  It is printed after the tileset build line.
- After the per-zoom statistics, one **stderr** line per unknown code:
  `Warning: unknown land use code 31 on 3 parcels; the map shows it as "Unknown land use (code 31)".`
  - With one parcel, `on 1 parcel`.
  - For land types: `unknown land type code N …` and `"Unknown land type (code N)"`.
- Last stdout line: `Published tileset and parcel store 20261123120000 with 25 parcels.` The existing API test
  regex is updated deliberately (log it).
- Exit codes unchanged (unknown codes still exit 0).

### Frontend

**Constants** (`frontend/src/mapOptions.ts`, step 4): `export const COMPLETE_PARCELS_MIN_ZOOM = 14`, used as the
outline layer `minzoom` and as the selection minimum. Comment: below 14 tiles leave out the smallest parcels.

**`frontend/src/mapConfiguration.ts`**:

- Step 4: `ParcelLayerConfiguration` gains `readonly parcelUrlTemplate: string`. It must be a string starting
  with `/` and containing `{id}`.
- Step 8: the type becomes `string | null`, and `null` is accepted.

**`frontend/src/text.ts`** (step 4, extended later): `export const UI_LOCALE = 'en'` and `export const TEXT`
(`as const`), with the panel strings below, plus
`unknownLandType(code: number): string` → `Unknown land type (code 99)` and
`unknownLandUse(code: number): string` → `Unknown land use (code 31)`.

| Key | English text | Step |
|---|---|---|
| panel label | `Parcel details` | 4 |
| heading | `Parcel {number}` (a function) | 4 |
| close label | `Close parcel details` | 4 |
| row labels | `Cadastral area`, `Municipality`, `Plot type`, `Land type`, `Land use`, `Area` | 4 |
| plot types | `Building plot`, `Land plot` | 4 |
| loading | `Loading parcel details…` | 4 |
| not found | `This parcel was not found. If the data has been updated, reload the page.` | 4 |
| error | `The parcel details could not be loaded. Please try again.` | 4 |
| viewer link | `Show owners in the ČÚZK cadastre viewer` | 6 |
| zoom hint | `Zoom in to select a parcel.` | 8 |
| details notice | `Parcel details have not been imported yet. Run make data, then reload this page.` | 8 |

**`frontend/src/landTypes.ts`** (step 4)

- `LAND_TYPE_COLOURS` is replaced by `LAND_TYPES: ReadonlyMap<number, { readonly name: string; readonly colour: string }>`,
  with the same colours and a Czech comment per code.
- `landTypeFillColour()` keeps exactly its current output.
- `landTypeName(code: number): string` returns the name or `unknownLandType(code)`.

| Code | Name | Code | Name |
|---|---|---|---|
| 2 | Arable land | 8 | Permanent grassland |
| 3 | Hop field | 10 | Forest land |
| 4 | Vineyard | 11 | Water area |
| 5 | Garden | 13 | Built-up area and courtyard |
| 6 | Orchard | 14 | Other area |
| 7 | Permanent grassland | | |

**`frontend/src/landUses.ts`** (step 4): `LAND_USE_NAMES: ReadonlyMap<number, string>` (Czech comment per code)
and `landUseName(code: number): string` (name or `unknownLandUse(code)`).

| Code | Name | Code | Name |
|---|---|---|---|
| 1 | Greenhouse, hotbed | 16 | Road |
| 2 | Tree nursery | 17 | Other road |
| 3 | Woody plant plantation | 18 | Other transport area |
| 4 | Forest other than commercial forest | 19 | Green space |
| 5 | Forest land with a building | 20 | Sports and recreation area |
| 6 | Fishpond | 21 | Cemetery |
| 7 | Natural or regulated watercourse bed | 22 | Cultural and educational area |
| 8 | Artificial watercourse bed | 23 | Handling area |
| 9 | Natural water reservoir | 24 | Mining area |
| 10 | Artificial water reservoir | 25 | Landfill |
| 11 | Wetland | 26 | Miscellaneous area |
| 12 | Shared courtyard | 27 | Unproductive land |
| 13 | Demolition site | 28 | Water area with a building |
| 14 | Railway | 29 | Photovoltaic power plant |
| 15 | Motorway | 30 | Field boundary, slope |

**`frontend/src/parcelFormat.ts`** (step 4)

- `formatArea(squareMetres: number): string` with `Intl.NumberFormat(UI_LOCALE)`: `455 m²`, `13,077 m²`.
- `formatCadastralArea(name: string, code: number): string`: `Jičín (659541)`.
- `plotTypeName(kind: 'building' | 'land'): string`.

**`frontend/src/parcelDetail.ts`** (step 4, extended in 5–7)

- `interface ParcelDetail`, with every member read-only:
  - step 4: `id`, `parcelNumber`, `kind`, `cadastralArea {code, name}`, `municipality {code, name}`,
    `landTypeCode`, `landUseCode: number | null`, `areaSquareMetres`;
  - step 5: `boundary: { type: 'Polygon'; coordinates: number[][][] }`;
  - step 6: `cadastreViewerUrl: string` (must start with `https://`);
  - step 7: `bounds {west, south, east, north}`.
  
  Each step validates the members it adds, in the style of `mapConfiguration.ts`.
- `parcelDetailUrl(template: string, id: number): string` replaces `{id}`.
- `type ParcelDetailResult = { readonly found: true; readonly detail: ParcelDetail } | { readonly found: false }`.
- `loadParcelDetail(fetchFn: typeof fetch, url: string, signal?: AbortSignal): Promise<ParcelDetailResult>`:
  - 404 → `{ found: false }`;
  - another non-2xx status, a network failure, invalid JSON or the wrong shape → throws
    `class ParcelDetailError extends Error`;
  - an `AbortError` is rethrown unchanged.

**`frontend/src/parcelPanel.ts`** (step 4): `class ParcelPanel`, which creates and owns this DOM, appended to
`body` and hidden initially:

```html
<aside class="parcel-panel" aria-label="Parcel details" hidden>
  <button type="button" class="parcel-panel-close" aria-label="Close parcel details">×</button>
  <h2 class="parcel-panel-title">Parcel st. 72</h2>
  <dl class="parcel-panel-facts">
    <dt>Cadastral area</dt><dd>Jičín (659541)</dd>
    <dt>Municipality</dt><dd>Jičín</dd>
    <dt>Plot type</dt><dd>Building plot</dd>
    <dt>Land type</dt><dd>Built-up area and courtyard</dd>
    <!-- Land use row only when landUseCode is not null -->
    <dt>Area</dt><dd>455 m²</dd>
  </dl>
  <!-- step 6: -->
  <a class="parcel-panel-viewer" href="…" target="_blank" rel="noopener noreferrer">Show owners in the ČÚZK cadastre viewer</a>
  <p class="parcel-panel-message" role="status"><!-- loading / not found / error --></p>
</aside>
```

- Methods: `showLoading()` (message only), `showDetail(detail)`, `showNotFound()`, `showError()`, `hide()`,
  `isOpen(): boolean`, `element(): HTMLElement`.
- The constructor takes `onClose: () => void`, called by the close button.
- Texts are set with `textContent`, never `innerHTML`.
- Step 6 adds the link; step 7 adds nothing here.

**`frontend/src/selectedParcel.ts`** (step 5)

- `selectedParcelData(detail: ParcelDetail | null): GeoJSON.FeatureCollection`. It is empty for `null`, otherwise
  one Polygon feature with the boundary and `id` = parcel id.
- `SELECTED_PARCEL_SOURCE = 'selected-parcel'`.

**`frontend/src/mapOptions.ts`** (step 5): when `parcels` is not null, the style also has:

- the source `selected-parcel`: `{ type: 'geojson', data: { type: 'FeatureCollection', features: [] } }`;
- the layers `selected-parcel-casing` (`line`, `line-color #ffffff`, `line-width 5`, `line-opacity 0.9`) and
  `selected-parcel-outline` (`line`, `line-color #e6007e`, `line-width 2.5`);
- layer order: `base-map`, `parcels-fill`, `parcels-outline`, `selected-parcel-casing`, `selected-parcel-outline`.

The parcel tile layers stay exactly as they are (no `feature-state`, no filter).

**`frontend/src/parcelHash.ts`** (step 7)

- `parcelIdFromHash(hash: string): number | null`: the `parcel` value of `new URLSearchParams(hash.replace(/^#/, ''))`
  if it matches `/^[1-9][0-9]{0,14}$/`, else `null`.
- `hashWithParcel(hash: string, id: number | null): string`: keeps other keys, sets or removes `parcel`, and
  returns `''` when nothing is left (otherwise `#…`).

**`frontend/src/fitPadding.ts`** (step 7):
`fitPadding(panel: { right: number; bottom: number }, viewportWidth: number): { top: number; right: number; bottom: number; left: number }`.

- Every side gets 40.
- When `viewportWidth > 640`, `left` = `panel.right + 24`; otherwise `top` = `panel.bottom + 24`.

**`frontend/src/parcelSelection.ts`** (step 4, extended in 5, 7 and 8): `class ParcelSelection`.

- Constructed with the map, the panel, `fetch`, the parcel URL template and (step 7) `window`.
- `select(id: number, options: { fit: boolean })`:
  - aborts an in-flight request, clears the highlight and calls `panel.showLoading()`;
  - loads the detail, then shows the detail, not found or the error (`console.warn` on error);
  - an aborted request is ignored;
  - step 5 sets the highlight data when found;
  - step 7 writes the hash and fits the map to `bounds` (`maxZoom: 18`, `fitPadding`) when `options.fit`.
- `clear()`: aborts, hides the panel, clears the highlight (step 5) and the hash (step 7).
- Map `click` handler:
  - zoom below `COMPLETE_PARCELS_MIN_ZOOM` → nothing;
  - otherwise `queryRenderedFeatures(event.point, { layers: ['parcels-fill'] })`, take the first feature whose
    `id` is a positive safe integer;
  - none → `clear()`; the selected id → nothing; else `select(id, { fit: false })`.
- `keydown` on `document`: `Escape` while the panel is open → `clear()`.
- Step 7 adds:
  - `window` `hashchange`: a valid id different from the selection → `select(id, { fit: true })`; no valid id
    while selected → `clear()`;
  - the hash is written with `history.replaceState(history.state, '', pathname + search + hash)`.

**`frontend/src/main.ts`**

- Step 4: when `parcels` is not null, it creates the panel and the selection after the map.
- Step 7: when `parcels` is not null and the hash has a valid parcel id, it fetches the detail **before** creating
  the map.
  - Found: the map options get `bounds` and `fitBoundsOptions: { maxZoom: 18, padding }` (the panel is shown with
    the detail first so its rectangle is known). The highlight is set on the map `load` event.
  - Not found: default view, panel not-found.
  - Error: default view, panel error.
  
  `createMapOptions` gains an optional parameter `initialBounds?: { bounds, padding }` for this.
- Step 8:
  - With `parcelUrlTemplate === null` it shows the details notice (class `map-notice`, `role="status"`), creates
    no selection and ignores the hash.
  - Otherwise it adds `<p class="map-hint">Zoom in to select a parcel.</p>`, hidden while
    `map.getZoom() >= COMPLETE_PARCELS_MIN_ZOOM`. It is updated on `load` and `zoomend` only.

**`frontend/src/app.css`**

- Step 4: `.parcel-panel` per Decision 14: white, readable, `overflow: auto`, a close button in the corner, the
  `dl` as a two-column grid, `@media (max-width: 640px)` variant.
- Step 8: `.map-hint`: bottom centre above the attribution (`bottom: 2.5rem`), small pill, `pointer-events: none`.

### Wiring

`Bootstrap\Container`:

- Step 2:
  - `ImportParcelsUseCase` receives `new ParcelStoreBuilderUsingSqlite($dataDir . '/build')` and
    `new ParcelDataPublisherUsingSqlite($this->tilesetFile(), $this->parcelStoreDirectory())`;
  - private `parcelStoreDirectory()` = data dir + `/parcels`.
- Step 3: route `GET ParcelDetailRoute::PATTERN` →
  `new GetParcelDetailHandler(new GetParcelDetailUseCase(new ParcelTilesetRepositoryUsingMbtiles(...), new ParcelRepositoryUsingSqlite($this->parcelStoreDirectory())))`.
- Step 8: `GetMapConfigurationUseCase` also receives `new ParcelRepositoryUsingSqlite(...)`.

Wiring only. No new environment variables: the store lives under `PARCEL_MAP_DATA_DIR`, so the test project's
`tests/Fixtures/data` works unchanged.

### Fixtures (`tests/Fixtures/`, documented in `tests/Fixtures/README.md`)

- **Step 1, `coverage/*.csv`**: add `municipality_name`:
  - `fixture.csv`: `659541,Jičín,572659,Jičín`, `740225,Robousy,572659,Jičín`;
  - `two-municipalities.csv`: plus `601462,Běchary,572675,Běchary`;
  - `unpublished-municipality.csv`: `699999,Missing,599999,Missing`.
- **Step 2, `geojson/572659.geojsonl`**: regenerated with `ParcelConverterUsingOgr2ogr` (25 lines, properties as
  in Verified facts). The README entry lists the properties.
- **Step 3, `data/`**: regenerated with the procedure of task 002:
  - run `bin/console parcels:import --export-date=2026-10-31` with
    `PARCEL_MAP_COVERAGE_FILE=tests/Fixtures/coverage/fixture.csv`, a temporary `PARCEL_MAP_DATA_DIR` whose
    `cache/ruian/` holds a copy of the fixture zip, and `RUIAN_EXPORT_BASE_URL=http://127.0.0.1:9/`;
  - copy `tiles/parcels.mbtiles` and `parcels/<version>.sqlite` into `tests/Fixtures/data/`, replacing the old
    tileset.
  
  Then:
  - check the README facts (bounds, zooms 9–16, tiles `12/2222/1381`, `13/4445/2762`, `14/8890/5525` present,
    `14/8891/5525` empty);
  - update the version in `tests/Support/FixtureTileset.php`, `tests/E2E/support/fixture.ts` (new, exports
    `FIXTURE_VERSION`, used by `parcels.spec.ts` and the new specs) and the README;
  - rewrite the README note "built with the options of task 002 … not rebuilt": it is now rebuilt with the current
    options.
- **Step 3, `tests/Support/FixtureParcelStore.php`** (new): `final class` with `file(): string` (the fixture store
  path, built from `FixtureTileset::VERSION`) and constants for the fixture parcels in Verified facts (ids,
  numbers, codes, areas).

## Test scenarios

### Unit

- **Step 1**
  - `Municipality` rejects an empty or whitespace name and equals by code.
  - `CadastralArea` is built with a `Municipality`.
  - `Coverage`:
    - rejects two areas of municipality 572659 with different municipality names;
    - `municipalities()` stays unique codes in order;
    - `areasOf()` works with the code.
  - Existing tests constructing `CadastralArea` follow the new constructor.
- **Step 2, domain**
  - `ParcelId` accepts 1 and 9007199254740991; rejects 0, −1 and 9007199254740992.
  - `ParcelKind::fromCode(1|2)`; rejects 3.
  - `ParcelNumber`:
    - `it_rejects_a_parcel_number_with_a_zero_base_number`;
    - rejects subdivision 0;
    - `toString()` gives `st. 72`, `st. 80/2`, `1171`, `867/121`;
    - `equals()` depends on the kind (`st. 72` ≠ `72`).
  - `LandType` and `LandUse`:
    - accept 2 and 99 (99 is not known);
    - reject 0 and −1;
    - `isKnown()` is true for exactly the pinned lists (land type 2, 3, 4, 5, 6, 7, 8, 10, 11, 13, 14; land use
      1–30) and false for 1, 9, 12, 15 (land type) and 31 (land use).
  - `Area` rejects 0.
  - `ParcelBoundary` rejects no rings, a ring with 3 positions and an open ring; keeps rings in order.
  - `Parcel` equals by id.
  - `Coverage::area()` returns the area; an uncovered code → `CadastralAreaNotCovered`.
  - `UnknownCode` rejects `parcelCount` 0.
- **Step 2, `ImportParcelsUseCaseTest`** (new fakes `ParcelStoreBuilderUsingMemory`, `ParcelDataPublisherUsingMemory`):
  - the store is built from the same converted parcels (in order) and the coverage, after the budget gate;
  - one `publish` call receives the tileset, the store, the clock version and the export date;
  - over budget → no store build, nothing published;
  - store build failure → `ParcelStoreBuildFailed` propagates, nothing published;
  - the progress events end with `build from N parcels`, then `parcel store from N parcels`;
  - unknown codes from the builder reach `ImportParcelsOutput::$unknownCodes` as `land use` / 31 / 3.
- **Step 2, `ImportProgressUsingConsoleTest`**: `parcelStoreBuildStarted(25)` prints
  `Building the parcel store from 25 parcels.`
- **Step 3, `GetParcelDetailUseCaseTest`** (fakes `ParcelTilesetRepositoryUsingMemory`, new `ParcelRepositoryUsingMemory`):
  - maps every field: number string, kind `building` / `land`, area and municipality, codes (a `null` land use),
    area, rings as `[lon, lat]`;
  - no data, a malformed version, another version, id 0, an unknown id or a missing store → `ParcelDetailNotFound`.
- **Step 3, handler and route**
  - `GetParcelDetailHandlerTest`: 404 for `abc`, a 16-digit id and an empty id; 200 with the JSON and
    `Cache-Control`; `boundary` is a GeoJSON Polygon.
  - `ParcelDetailRouteTest`: the template keeps `{id}`.
- **Step 6**: `Parcel::cadastreViewerUrl()` for id 1753295604; the use case maps it.
- **Step 7**: `ParcelBoundary::bounds()` is taken from the outer ring (a hole outside it does not change it; use
  distinct values so swapped corners fail); the use case maps the bounds.
- **Step 8**: `GetMapConfigurationUseCaseTest` sets `detailsAvailable` from `isPublished()` (true and false).

### Integration

- **Step 1**
  - `DistrictAreaExtractorUsingOgr2ogr` (fixture ST_UZSZ, district 3604): the Jičín areas carry municipality
    `572659 Jičín`; 601462 carries `572675 Běchary`.
  - `CoverageRepositoryUsingCsv`:
    - reads and writes 4 columns;
    - quotes a municipality name with a comma;
    - rejects a 3-field row and the old 3-column header;
    - `config/coverage.csv` has 240 areas, 111 municipalities, and 659541 has the municipality name Jičín.
- **Step 2**
  - `ParcelConverterUsingOgr2ogr` (fixture zip):
    - st. 72 has exactly the properties `id, landType, areaCode, kind, baseNumber, area`;
    - 99225194010 also has `subdivision` 121 and `landUse` 26;
    - the other existing assertions stay.
  - `TilesetBuilderUsingTippecanoe`: decoded features of `14/8890/5525` carry exactly the property `landType`.
  - `ParcelStoreBuilderUsingSqlite` (`geojson/572659.geojsonl`, fixture coverage):
    - 25 parcel rows and 2 area rows;
    - the st. 72 row has the expected values and NULL subdivision and land use;
    - 1761441604 decompresses to 3 rings;
    - no unknown codes.
    - Temporary files:
      - `landType` 99 and `landUse` 31 → stored, reported as unknown with counts;
      - `landType` 0 → `ParcelStoreBuildFailed` naming the file and line 1;
      - missing `kind` → `ParcelStoreBuildFailed`;
      - area code 699999 (not covered) → `ParcelStoreBuildFailed`;
      - a duplicate id → `ParcelStoreBuildFailed`;
      - a `MultiPolygon` → `ParcelStoreBuildFailed`;
      - no output file remains after a failure.
  - `ParcelDataPublisherUsingSqlite` (temporary directories, copies of the fixture tileset and of a built store):
    - both files are stamped with version and export date and moved to their targets;
    - with a live tileset of version A and stores A, B (older) and C: publishing D keeps A and D and deletes B and
      C;
    - when the tileset target cannot be written (target path is a directory), `ParcelDataPublishFailed` and the
      new store file is gone;
    - a non-SQLite tileset → `ParcelDataPublishFailed`.
- **Step 3**: `ParcelRepositoryUsingSqlite` (fixture store):
  - `get(FIXTURE_VERSION, 1753295604)` returns the full parcel (number `st. 72`, area Jičín with municipality Jičín,
    land type 13, no land use, 455 m², 1 ring with 32 positions);
  - id 999 → `ParcelNotFound`;
  - another version → `ParcelStoreNotPublished`;
  - a text file named `<version>.sqlite` → `ParcelStoreUnreadable`;
  - a store with a non-zlib boundary → `ParcelStoreUnreadable`.
- **Step 8**: `isPublished()` is true for the fixture version and false for another.

### API (HTTP and CLI)

- **Step 1 (CLI)**: `GenerateCoverageCommandTest` expects the 4-column file
  (`601462,Běchary,572675,Běchary`, `659541,Jičín,572659,Jičín`, …).
- **Step 2 (CLI, `ImportParcelsCommandTest`)**:
  - the published line regex is updated (deliberately) to `/^Published tileset and parcel store \d{14} with 25 parcels\.$/m`;
  - `<temp>/parcels/<that version>.sqlite` exists, has 25 parcels and its `metadata` export date is 2026-10-31;
  - stdout contains `Building the parcel store from 25 parcels.`;
  - stderr has no `Warning:` line;
  - a second import keeps the first store (previous live) and adds the second.
- **Step 3 (HTTP, fixture data)**:
  - `GET /api/parcels/<FIXTURE_VERSION>/1753295604` → 200, `application/json`,
    `Cache-Control: public, max-age=31536000, immutable`, the body as in Handlers (without `cadastreViewerUrl` /
    `bounds`), boundary 1 ring, 32 positions, first `[15.3509779, 50.4377444]`;
  - `/1761441604` → `"parcelNumber": "1171"`, `"kind": "land"`, `"landUseCode": 17`, 3 rings;
  - `/99225194010` → `"867/121"`, area `740225 Robousy`, municipality `572659 Jičín`;
  - 404 `{"error":"not_found"}` for id 999, another version, `abc` and a 16-digit id;
  - `POST` → 405 with `Allow: GET`;
  - `/api/map-config` (exact body, updated deliberately) has `parcelUrlTemplate` `/api/parcels/<FIXTURE_VERSION>/{id}`.
- **Step 6**: the body has `cadastreViewerUrl` for 1753295604.
- **Step 7**: the body has `bounds` W 15.3509216, S 50.4372305, E 15.3511404, N 50.437745.

### Frontend (unit and E2E)

**Vitest:**

- **Step 4**
  - `mapConfiguration.test.ts`: accepts `parcelUrlTemplate`; rejects a missing one, one without `{id}` and a
    relative one.
  - `landTypes.test.ts`:
    - the names of all 11 codes;
    - 7 and 8 share a name and a colour;
    - `landTypeName(99)` gives `Unknown land type (code 99)`;
    - the catalogue has exactly the codes 2, 3, 4, 5, 6, 7, 8, 10, 11, 13, 14;
    - the fill expression is unchanged.
  - `landUses.test.ts`: exactly the codes 1–30; `landUseName(17)` = `Other road`; `landUseName(31)` =
    `Unknown land use (code 31)`.
  - `parcelFormat.test.ts`: `455 m²`, `13,077 m²`, `Jičín (659541)`, plot types.
  - `parcelDetail.test.ts` (fake fetch):
    - a valid body → `found: true`;
    - 404 → `found: false`;
    - 500, a network error, invalid JSON, a missing `parcelNumber` and a non-number `landUseCode` →
      `ParcelDetailError`;
    - `landUseCode: null` is accepted;
    - `parcelDetailUrl` replaces `{id}`;
    - the abort signal is passed to fetch.
  - `mapOptions.test.ts`: the outline `minzoom` equals `COMPLETE_PARCELS_MIN_ZOOM` (14).
- **Step 5**
  - `selectedParcel.test.ts`: empty for `null`; one Polygon feature with the id and coordinates; a boundary is
    required.
  - `mapOptions.test.ts`:
    - the `selected-parcel` source is empty;
    - layer order as specified;
    - the casing and outline paints;
    - no paint or filter of `parcels-fill` / `parcels-outline` mentions `feature-state`;
    - without parcels there is no selection source.
- **Step 6**: `parcelDetail.test.ts` requires `cadastreViewerUrl` to be an `https://` string.
- **Step 7**
  - `parcelHash.test.ts`:
    - `#parcel=1753295604` → the id; `''`, `#`, `#parcel=abc`, `#parcel=0`, `#parcel=0123` and a 16-digit id →
      `null`;
    - `#map=1/2/3&parcel=5` → 5;
    - `hashWithParcel('#map=1/2/3', 5)` → `#map=1%2F2%2F3&parcel=5` (URLSearchParams encoding, assert the
      round-trip);
    - removing the only key → `''`.
  - `fitPadding.test.ts`: wide and narrow cases.
  - `parcelDetail.test.ts`: requires `bounds`.
  - `mapOptions.test.ts`: `initialBounds` sets `bounds` and `fitBoundsOptions`; without it, center and zoom as
    before.
- **Step 8**: `mapConfiguration.test.ts` accepts `parcelUrlTemplate: null`.

**Playwright** (`tests/E2E/parcel-detail.spec.ts`, offline-tiles fixture):

- A helper rewrites `initialView` of the real `/api/map-config` response, as the perf spec does.
- Clicking uses a retry so that a click before the tile is rendered does not flake:
  `await expect(async () => { await canvas.click({ position }); await expect(panel).toBeVisible({ timeout: 500 }); }).toPass()`.
- Panel locator: `page.getByRole('complementary', { name: 'Parcel details' })`.

Scenarios:

- **Step 4**
  - View centred on lon 15.3510535 / lat 50.4373542 at zoom 18; clicking the canvas centre requests
    `/api/parcels/<FIXTURE_VERSION>/1753295604`. The panel shows the heading `Parcel st. 72`, `Jičín (659541)`,
    `Jičín`, `Building plot`, `Built-up area and courtyard` and `455 m²`, and no `Land use` term.
  - Centred on lon 15.3517724 / lat 50.4368209 at zoom 18: `Parcel 1171`, `Land plot`, `Other area`, `Other road`,
    `13,077 m²`.
  - The close button hides the panel; Escape hides it.
  - Clicking the canvas at (width − 100, height / 2), where the fixture has no parcel, hides it.
  - At the default zoom 12, clicking the canvas centre makes no `/api/parcels/` request within 1 s and shows no
    panel.
  - With `/api/parcels/**` answered 500 (route fulfil; `allowedConsoleErrors: [/status of 500/]`), the panel shows
    the error text.
- **Step 6**: the panel of st. 72 has the link `Show owners in the ČÚZK cadastre viewer` with the viewer URL and
  `target="_blank"`. The test never opens it; any request leaving the origin fails the fixture.
- **Step 7**
  - `/#parcel=1753295604` requests the detail.
  - The panel shows `Parcel st. 72`.
  - Parcel tile `16/35562/22101` is requested, and **`12/2222/1381` is not** (the map opens at the parcel).
  - Selecting a parcel by click writes `#parcel=1761441604` into `page.url()`; closing removes the hash (the URL
    has no `#`).
  - Setting `location.hash = '#parcel=1761441604'` from `page.evaluate` shows `Parcel 1171`.
  - `/#parcel=999` shows the not-found text on the default view (`12/2222/1381` requested;
    `allowedConsoleErrors: [/status of 404/]`).
  - `/#parcel=abc` makes no parcel request and shows no panel.
- **Step 8**
  - At zoom 12 the hint `Zoom in to select a parcel.` is visible; at zoom 18 it is hidden.
  - With `parcelUrlTemplate: null` (rewritten map-config):
    - the details notice is visible;
    - at zoom 18 a click on the centre makes no `/api/parcels/` request;
    - no hint;
    - `/#parcel=1753295604` opens no panel.
- **Existing specs** keep passing. `parcels.spec.ts` imports `FIXTURE_VERSION` (step 3). `no-parcel-data.spec.ts`
  (`parcels: null`): no panel and no hint (step 8 adds the assertion).

## Acceptance criteria

- [ ] Step 1: `config/coverage.csv` has the header `code,name,municipality_code,municipality_name`, 241 lines and
      sha256 `333b92724b79dd4749078928769198f812d1b1432ce2e56a1e535e35145bef98`.
- [ ] `make data` from the cache publishes `data/tiles/parcels.mbtiles` and `data/parcels/<version>.sqlite` with
      the same version and 272,011 parcels.
      - The store is at most 60 MB.
      - The import takes at most 15 s longer than before (record both times).
      - No `Warning:` line for the 2026-10-31 export.
- [ ] A second `make data` keeps exactly two store files (the new one and the previous one). A third one deletes
      the oldest.
- [ ] Tiles are unchanged: features carry only `landType`; every tile is within the 200 kB budget (the gate still
      passes).
- [ ] `curl -i localhost:8000/api/parcels/<version>/1753295604` with real data returns the details with the
      immutable `Cache-Control`. `/api/map-config` has the `parcelUrlTemplate`.
- [ ] In the browser with real data:
      - zoom 14+ over Jičín: clicking a parcel opens the left panel with the right number, area, municipality,
        names and m², and the parcel is outlined;
      - the ČÚZK link opens the viewer page of that parcel in a new tab;
      - the URL shows `#parcel=<id>`; reloading reopens it fitted to the parcel;
      - below zoom 14 the hint is shown and clicks select nothing.
- [ ] Performance, recorded in the implementation log:
  - [ ] 100 sequential detail requests for random ids on real data, localhost: median under 20 ms (the lookup alone
        measured 0.26 ms).
  - [ ] Frontend: no `move`, `render` or `mousemove` listeners added. The parcel tile layers have no
        `feature-state` and no filter (unit test). Selecting changes only the one-feature `selected-parcel` source.
  - [ ] Manual, Chrome performance panel on real hardware (a person; mark "NOT done" if not possible): clicking
        parcels at zoom 16 over Jičín and panning afterwards shows no long task over 50 ms caused by the selection.
- [ ] Tests never contact ČÚZK (including the cadastre viewer) or OSM.
- [ ] `make check` passes at every commit (`git rebase --exec "make check" main`); CI groups are unchanged.
- [ ] README, `config/README.md`, `tests/Fixtures/README.md` and the CLAUDE.md Glossary are updated as listed
      below. The README Notes say that nothing is logged in this version (Decision 15).

## Documentation updates per commit

- **Step 1**
  - `config/README.md`: the format line with `municipality_name`; the committed version's sha256.
  - `tests/Fixtures/README.md`: the coverage CSV entries.
  - CLAUDE.md Glossary, `Municipality` row note: "Code (`MunicipalityCode`) and name; names come from
    `config/coverage.csv`."
- **Step 2**
  - README Data:
    - the import also builds the parcel store `data/parcels/{version}.sqlite` (about 46 MB for the district,
      zlib-compressed boundaries), published together with the tileset under one version;
    - the previous store is kept for requests in flight, older ones are deleted;
    - unknown land type / land use codes are kept and reported as warnings on stderr.
  - README Notes > Decisions, new bullet (wording may be polished, content binding): "**No logging in this
    version.** Nothing is logged: the import prints warnings (such as unknown land type or land use codes) to
    stderr, and the server reports uncaught errors to stderr. A production version would have a logger that
    records every error and any other meaningful information."
  - README Notes > Decisions, also:
    - the parcel store as a separate SQLite file per version, with the tileset rename as the commit point;
    - attributes are not put into the tiles (smoothness).
  - README With more time: generate the frontend code catalogues and the PHP known-code lists from one source
    (today they are pinned by separate tests; Decision 9).
  - CLAUDE.md Glossary changes:

    | Czech (source data) | Code name | Notes |
    |---|---|---|
    | — | `ParcelId` | RÚIAN parcel `Id`; positive, at most 2^53 − 1. Feature id in the tiles, key of the parcel store. |
    | druh pozemku | `LandType` (note update) | RÚIAN `DruhPozemkuKod`. A positive code outside the published code list is kept and shown as unknown. |
    | způsob využití pozemku | `LandUse` (note update) | RÚIAN `ZpusobyVyuzitiPozemku`; optional. Unknown positive codes are kept and shown as unknown. |
    | parcelní číslo | `ParcelNumber` (note update) | Kind + base number (kmenové číslo) + optional subdivision (poddělení), e.g. `st. 80/2`, `867/121`. |
    | hranice parcely (`OriginalniHranice`) | `ParcelBoundary` | Polygon rings in WGS84, outer ring first. |
    | — | Parcel store (`ParcelStoreBuilder`, `BuiltParcelStore`, `ParcelRepository`) | SQLite file of parcel details per `TilesetVersion`: `data/parcels/{version}.sqlite`. |
    | — | Parcel data (`ParcelDataPublisher`) | The tileset and parcel store of one import, published together under one `TilesetVersion`. |
    | — | `TilesetVersion` (note update) | Identifies one published import (tileset and parcel store); part of the tile and parcel URLs. |

- **Step 3**
  - README:
    - a new subsection "Parcel details" next to "Parcel tiles": `GET /api/parcels/{version}/{id}`, caching, 404
      cases, `parcelUrlTemplate` in map-config;
    - Architecture text if it lists endpoints.
  - `tests/Fixtures/README.md`: the regenerated data dir (new version, store file, regenerated with current
    options).
  - CLAUDE.md Glossary: `— | Parcel detail | What the side panel shows about a parcel (GET /api/parcels/{version}/{id}).`
- **Step 4**
  - README Running: clicking a parcel from zoom 14 opens the panel.
  - Notes > Decisions: minimum zoom 14 and why; English UI with all strings in `text.ts` and the code catalogues.
  - CLAUDE.md Glossary, new row, because the concept (`ParcelSelection`) appears in this commit:
    `— | Selected parcel | The parcel shown in the side panel.`
- **Step 5**
  - README Notes > Decisions: the highlight is a GeoJSON overlay, not feature-state or a filter (smoothness).
  - CLAUDE.md Glossary, the "Selected parcel" note becomes: `The parcel shown in the side panel and outlined on the
    map (source selected-parcel).`
- **Step 6**
  - README: the ČÚZK viewer link replaces the missing ownership data.
  - CLAUDE.md Glossary: `Nahlížení do katastru nemovitostí | Cadastre viewer (Parcel::cadastreViewerUrl()) | ČÚZK's public web viewer; shows owners and the LV number after login or a CAPTCHA.`
  - The `OwnershipSheet` note gains "the panel links to the cadastre viewer".
- **Step 7**
  - README: `#parcel=<id>` links.
  - CLAUDE.md Glossary, the "Selected parcel" note becomes: `The parcel shown in the side panel and outlined on the
    map (source selected-parcel); in the URL as #parcel={id}.`
- **Step 8**: README Running: the hint and the details-not-imported notice (rerun `make data` after upgrading from
  a tileset imported before task 006).

## Risks and assumptions

- **Cadastre viewer URL.** It is undocumented and verified only by real requests and indexed pages. If ČÚZK
  changes it, the link breaks silently. It lives in one constant (`Parcel::CADASTRE_VIEWER_URL`). The viewer's bot
  protection affects only scripted access.
- **Code lists are pinned twice** (PHP and frontend) and nothing cross-checks them (Decision 9). A ČÚZK code-list
  change shows "Unknown … (code N)" until both are updated by hand. The import's stderr warning tells the operator.
- **Upgrade path.** A tileset imported before this task has no store. The map shows the details notice until
  `make data` is rerun (Decision 16).
- **Disk.** Two stores of about 46 MB each are kept in `data/parcels/`, plus 17 MB more GeoJSON in `data/build/`.
- **Stale pages.** After a re-import, an open page gets 404 for details and shows "reload the page", consistent
  with tiles.
- **Ids above 2^53 − 1** would fail the import (`ParcelId`). The observed maximum is 1.06 × 10^11.
- **Fixture regeneration (step 3)** changes the fixture version. Bounds and tiles are expected to stay identical
  (same input, tiles far below budget). If not, update the constants and log it.
- **E2E clicks depend on rendering.** A click before the tile is rendered finds no feature, hence the `toPass`
  retry. The deep-link assertion that `12/2222/1381` is never requested relies on MapLibre applying `bounds` in
  the constructor. If it flakes, report it rather than drop it.
- **No logging** (Decision 15): warnings go to stderr only and are not persisted.
- **Assumption:** RÚIAN parcel ids stay stable while a parcel exists, so links survive monthly re-imports. A split
  or merged parcel gets new ids, and its old link shows "not found".

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: coverage with municipality names

- Added `Municipality` (code + name) and `InvalidMunicipality`; `CadastralArea` holds it; `Coverage` rejects two
  areas of one municipality with different names (`InvalidCoverage::inconsistentMunicipality`).
- `DistrictAreaExtractorUsingOgr2ogr` reads `Kod, Nazev` of `Obce`; a non-numeric municipality code or an area whose
  municipality is not in the first query fails with `StructureReadFailed`.
- `CoverageRepositoryUsingCsv` reads and writes the 4 columns.
- Deliberate changes to existing tests: every `CadastralArea` construction in tests takes a `Municipality`;
  `GenerateCoverageCommandTest` expects the 4-column file; `CoverageRepositoryUsingCsvTest` expects the new header,
  rows and error cases (the old 3-column header is now a "line 1" error) and the committed file's municipality names;
  `DistrictAreaExtractorUsingOgr2ogrTest` also asserts the municipality names.
- Regenerated `config/coverage.csv` from the cached `20261031_ST_UZSZ.xml.zip`: 241 lines, sha256
  `333b9272...bef98` as expected.

### Step 2: the import publishes a parcel store

- Domain: `ParcelId`, `ParcelKind`, `ParcelNumber`, `LandType`, `LandUse`, `Area`, `ParcelBoundary`, `Parcel`,
  `Coverage::area()` with `CadastralAreaNotCovered`, and one `Invalid…` exception per value object.
- Application: `ParcelStoreBuilder`, `BuiltParcelStore`, `CodeList`, `UnknownCode`, `ParcelStoreBuildFailed`;
  `TilesetPublisher` renamed to `ParcelDataPublisher` (files moved with `git mv`); `ImportParcelsUseCase` builds the
  store after the budget gate and exposes the unknown codes in `ImportParcelsOutput`.
- Infrastructure: `ParcelStoreBuilderUsingSqlite`, `ParcelDataPublisherUsingSqlite`, the converter reads the extra
  attributes. Decision below the plan's level: a line that is not a valid parcel (not JSON, a missing property, a
  non-Polygon geometry) is reported by a small infrastructure exception `InvalidParcelLine`
  (an `InvalidArgumentException`), which the builder turns into `ParcelStoreBuildFailed::invalidParcel`.
- Command: `ImportProgressUsingConsole::parcelStoreBuildStarted`, the stderr warnings and the new published line.
  A unit test `tests/Unit/Command/ImportParcelsCommandTest.php` covers the warnings with fakes (the API suite has no
  way to feed an unknown code to the real import).
- Test helper `tests/Support/SqliteFile.php` (PHPStan does not accept `?->` on `PDO::query()`).
- Deliberate changes to existing tests: `ImportParcelsCommandTest` (API) expects the new
  `Published tileset and parcel store …` line, the parcel store build line and the store file;
  `ParcelConverterUsingOgr2ogrTest` no longer expects the property list `id, landType, areaCode` but the extended one;
  the `TilesetPublisherUsingMbtiles` test became `ParcelDataPublisherUsingSqliteTest` with the store protocol;
  `ImportParcelsUseCaseTest` follows the new constructor and fakes. `tests/Fixtures/geojson/572659.geojsonl` was
  regenerated with the converter (25 lines).
- README, Glossary and `tests/Fixtures/README.md` updated as listed.

### Step 3: parcel details endpoint

- Domain: `ParcelRepository` (`get()` only; `isPublished()` comes in step 8) and the three exceptions. Application:
  `GetParcelDetailUseCase` with input, output (without the cadastre viewer URL and bounds) and `ParcelDetailNotFound`.
  Infrastructure: `ParcelRepositoryUsingSqlite`. Handler: `GetParcelDetailHandler`, `ParcelDetailRoute`; the route is
  wired in `Container`, and `/api/map-config` gains `parcelUrlTemplate`.
- Fixture data regenerated with the task 002 procedure (temporary data dir with the fixture zip in `cache/ruian/`):
  new version `20261123140206`, store `tests/Fixtures/data/parcels/20261123140206.sqlite`. Bounds
  `15.350516,50.432327,15.403251,50.437799`, zooms 9 to 16 and the tiles `12/2222/1381`, `13/4445/2762`,
  `14/8890/5525` (present) and `14/8891/5525` (empty) are unchanged; `16/35562/22101` exists. The version is now in
  `FixtureTileset::VERSION` and `tests/E2E/support/fixture.ts` (`FIXTURE_VERSION`, used by `parcels.spec.ts`).
- Deliberate change to an existing test: `MapConfigurationTest` expects `parcelUrlTemplate` in the exact body.
- `ParcelRepositoryUsingMemory` takes the version that has a store (or null) and the parcels, so one fake serves the
  unknown-version and missing-store cases.

### Step 4: side panel

- Added `text.ts`, `landUses.ts`, `parcelFormat.ts`, `parcelDetail.ts`, `parcelPanel.ts`, `parcelSelection.ts`; the
  land type catalogue is now `LAND_TYPES` (name and colour); `COMPLETE_PARCELS_MIN_ZOOM` in `mapOptions.ts`;
  `parcelUrlTemplate` is validated in `mapConfiguration.ts`; the panel CSS is in `app.css`.
- `ParcelSelection.select(id)` has no `options` parameter yet: `fit` is introduced in step 7, where it is used.
- Deliberate changes to existing tests: `landTypes.test.ts` uses `LAND_TYPES` instead of `LAND_TYPE_COLOURS`;
  `mapConfiguration.test.ts` and `mapOptions.test.ts` carry a `parcelUrlTemplate` in their fixtures.
- Deviation from the plan's E2E scenario: the click "where the fixture has no parcel" at `(width - 100, height / 2)`
  hits parcel 1753303604 at the default 1280x720 viewport (540 px east of the centre at zoom 18; the parcel spans
  525 to 589 px). The test clicks 310 px further north, `(width - 100, height / 2 - 310)`, where no fixture parcel is.

### Step 5: highlight

- `selectedParcel.ts` (`selectedParcelData`, `SELECTED_PARCEL_SOURCE`), the `selected-parcel` source and its two layers
  in `mapOptions.ts`, and `ParcelDetail.boundary` (validated as a Polygon). `ParcelSelection` sets the source data
  when a detail is found and clears it on `select` and `clear`.
- `GeoJSONSource.setData` returns a promise in MapLibre 6, so a rejection is logged with `console.warn`.
- `FeatureCollection` is imported from `geojson` (`@types/geojson`, a dependency of maplibre-gl), because the
  `GeoJSON` global is not available under the project's `types` setting.
- E2E: the plan lists no E2E scenario for the highlight, so `parcel-detail.spec.ts` gets one: it counts magenta
  (`#e6007e`) pixels in a page screenshot, which are 0 before selecting, many after, and 0 again after Escape.

### Step 6: cadastre viewer link

- `Parcel::cadastreViewerUrl()` feeds `ParcelDetailOutput::$cadastreViewerUrl`, the API field `cadastreViewerUrl` and the
  panel link (`target="_blank"`, `rel="noopener noreferrer"`). The frontend only accepts an `https://` URL, so a
  response can never put a `javascript:` URL into the link. No test opens the link or contacts ČÚZK.

### Step 7: selected parcel in the URL

- Backend: `ParcelBoundary::bounds()` (the outer ring), `ParcelDetailOutput` west/south/east/north and the API member
  `bounds`.
- Frontend: `parcelHash.ts`, `fitPadding.ts`, `ParcelDetail.bounds`, `createMapOptions(..., initialBounds?)` (the
  initial view is not set when bounds are given, because the bounds override it), `ParcelSelection.select(id, {fit})`,
  `adopt()`, the `hashchange` listener and the `replaceState` writes. `main.ts` loads a linked parcel before the map is
  created and takes it over on the map `load` event.
- `ParcelSelection` writes the hash when a selection starts (also for an id that turns out to be unknown, whose hash
  therefore stays until the panel is closed) and removes it in `clear()`.
- `ParcelBoundary::bounds()` throws `InvalidBounds` for a degenerate outer ring (zero width or height). Real RÚIAN
  parcels have an area of at least 1 m², so this is not expected.

### Step 8: when selection is not possible

- `ParcelRepository::isPublished()` (a file check), `ParcelLayerOutput::detailsAvailable`,
  `GetMapConfigurationUseCase` takes the `ParcelRepository`, and `/api/map-config` sends `parcelUrlTemplate: null`
  when the store is missing. A unit test of `GetMapConfigurationHandler` covers the null case, because the API suite
  runs on the fixture data, which has a store.
- Frontend: `mapConfiguration.ts` accepts `null`, `zoomHint.ts` (the hint is set once at creation and then updated on
  `load` and `zoomend`), `TEXT.zoomHint` / `TEXT.detailsNotice`, and `main.ts` shows the details notice and creates
  no selection when `parcelUrlTemplate` is null.
- `UnreadableParcelRepositoryUsingMemory` replaces an anonymous class in `GetParcelDetailUseCaseTest`, which would
  have needed the new interface method.

### Acceptance checks with real data

Run on 2026-11-23 against a separate data dir (`PARCEL_MAP_DATA_DIR=var/acceptance`, its `cache` symlinked to the
cached 2026-10-31 export), so the data of the dev app was not touched; the directory was deleted afterwards.

- `parcels:import --export-date=2026-10-31` from the cache: 272,011 parcels, no `Warning:` line, tiles unchanged (the
  budget gate passes, largest tile 186.1 kB at zoom 13). It published `tiles/parcels.mbtiles` (55.9 MB) and
  `parcels/20261123142224.sqlite` (45.9 MB, under the 60 MB limit) with the same version.
- Time: 51 s wall clock including container start for the whole import from the cache. The README gave about 45 s
  for the import before this task, so the store costs about 6 s, within the 15 s allowed. The earlier figure is from
  the README, not a new measurement.
- A second import left two stores (the new one and the previous one), a third one deleted the oldest, as specified.
- 100 sequential detail requests for random ids against a throwaway PHP server (8 workers) in the same container,
  localhost: median 1.9 ms, p95 4.2 ms, maximum 8.4 ms (limit: median under 20 ms). The response for 1753295604 had
  `Cache-Control: public, max-age=31536000, immutable`, and `/api/map-config` had
  `parcelUrlTemplate: /api/parcels/20261123142420/{id}`.
- Frontend listeners: the code adds only `click`, `zoomend`, `hashchange` and `keydown` handlers, the close button
  handler, and a `load` handler (once for a linked parcel, and for the zoom hint). No `move`, `render` or `mousemove`
  listener. The parcel tile layers have no `feature-state` and no filter (unit test); selecting changes only the
  one-feature `selected-parcel` source.
- Not done: the manual check in a real browser with real data (clicking parcels at zoom 16 over Jičín, the ČÚZK link
  opening in a new tab, reloading a `#parcel=` link, the Chrome performance panel for long tasks). The E2E tests
  cover the same behaviour on the fixture data, and the viewer link is never opened by a test. NOT done.

### Red-run verification (review round 1)

The developer asked to verify the tests that were written together with their code instead of red first. For each one
the production code it covers was mutated in the working tree at head (a check removed, a value changed, a call
dropped), the test run, and the file restored; nothing of this was committed. Result: every listed test fails for the
expected reason, except the three ineffective ones listed at the end.

- **Step 1** (Municipality, Coverage, extractor, CSV; 7 mutations): `MunicipalityTest` (empty name accepted, `equals`
  always false), `CoverageTest` (different municipality names accepted, `areasOf` ignoring the code),
  `DistrictAreaExtractorUsingOgr2ogrTest` (wrong municipality name), `CoverageRepositoryUsingCsvTest` (name not
  written, 3 fields accepted). Failure reasons: "exception ... is thrown" not thrown, or two arrays/strings not
  identical. `CadastralAreaTest::it_holds_its_municipality` cannot be mutated: the property is enforced by its type.
- **Step 2** (domain, import, store, publisher; 41 mutations): `ParcelIdTest`, `ParcelKindTest`, `ParcelNumberTest`,
  `LandTypeTest`, `LandUseTest`, `AreaTest`, `ParcelBoundaryTest`, `ParcelTest`, `UnknownCodeTest`, `CoverageTest::area`,
  `ImportParcelsUseCaseTest` (store built before the budget gate, no store progress event, unknown codes dropped, a
  wrong store published), `ImportProgressUsingConsoleTest`, `ImportParcelsCommandTest`,
  `ParcelConverterUsingOgr2ogrTest` (attribute dropped from the SQL), `TilesetBuilderUsingTippecanoeTest` (an extra
  property kept), `ParcelStoreBuilderUsingSqliteTest` (unknown-code counting for both lists, non-Polygon accepted,
  duplicate id not detected, partial file kept, boundary not compressed, sort order) and
  `ParcelDataPublisherUsingSqliteTest` (store not stamped, store not removed after a failed tileset rename, no prune,
  live store not kept). All fail with the expected assertion or missing exception.
- **Step 3** (repository, use case, handler, route; 14 mutations): `ParcelRepositoryUsingSqliteTest` (not-published
  check, not-found check, zlib check, holes dropped), `GetParcelDetailUseCaseTest` (version check, invalid id passed
  on, missing store, kind, land use), `GetParcelDetailHandlerTest` (cache header, 404), `ParcelDetailRouteTest`.
- **Step 4** (frontend, 24 mutations plus 7 E2E): `landUses`, `landTypes`, `parcelFormat`, `text`, `parcelDetail`,
  `mapOptions` and `mapConfiguration` unit tests (names, colours, formats, 404, abort, signal, URL, field checks, minimum
  zoom, `{id}` and relative template); E2E: zoom gate, Escape, click on nothing, close button, land use row, error state.
- **Step 5** (highlight; 8 mutations plus 1 E2E): `selectedParcel.test.ts` (empty collection, id, geometry),
  `mapOptions.test.ts` (casing width, outline colour, source, layer order), `parcelDetail.test.ts` (boundary check);
  E2E: the highlight is not drawn when the data is not set.
- **Step 6** (viewer link; 4 mutations plus 1 E2E): `ParcelTest`, `GetParcelDetailUseCaseTest`,
  `GetParcelDetailHandlerTest` and `parcelDetail.test.ts` (https check); E2E: link without `href`.
- **Step 7** (URL; 13 mutations plus 5 E2E): `ParcelBoundaryTest::bounds` (swapped corners, holes widening the bounds),
  use case and handler bounds, `parcelHash`, `fitPadding`, `mapOptions` (`initialBounds`, `fit` maximum zoom, initial
  view kept), `parcelDetail` bounds check; E2E: hash not written, hash not removed, no `hashchange` listener, map not
  fitted to the linked parcel, not-found message, malformed hash used.
- **Step 8** (selection not possible; 4 mutations plus 2 E2E): `GetMapConfigurationUseCaseTest` (detailsAvailable
  always true, inverted), `GetMapConfigurationHandlerTest` (null template), `ParcelRepositoryUsingSqliteTest::isPublished`,
  `mapConfiguration.test.ts` (null rejected); E2E: hint always visible, details notice not shown.
- Not mutated: the highlight set on the map `load` event for a linked parcel (`adopt`) and the fit after a
  `hashchange` (`ParcelSelection.fitTo`) have no test that fails without them; the E2E for a linked parcel checks the
  highlight, which `adopt` draws, but I did not run that mutation.

**Ineffective tests found (not changed, reported to the coordinator first):**

1. `GetParcelDetailUseCaseTest::it_maps_a_building_parcel_without_land_use` does not tell the cadastral area name from
   the municipality name (both are "Jičín"); replacing `$area->municipality->name` with `$area->name` in the use case
   leaves it green. The API test with the Robousy parcel would catch it, the unit test does not.
2. `GetParcelDetailHandlerTest::it_answers_404_for_an_id_that_is_not_one_to_fifteen_digits` (`sixteen digits`) and the
   API test for a 16-digit id: removing the 15-digit limit in `GetParcelDetailHandler` keeps both green, because the
   unknown id also gives 404. The limit is masked.
3. `parcelDetail.test.ts` "the status is 500": without the `!response.ok` check the test still passes, because the
   JSON error body fails the shape check and also throws `ParcelDetailError`. The status branch is masked.

## Review

Filled in during the review loop (implement agent), one entry per round with findings.

### Round 1: head 1ca6bcf, 2026-11-23

1. Confirmed. `src/Infrastructure/Import/ParcelStoreBuilderUsingSqlite.php:238` and `tests/Integration/Infrastructure/Import/ParcelStoreBuilderUsingSqliteTest.php:242`, Code rules (`mixed`): both `mixed` parameters now have an `@param mixed` line that says why. Fixed in `feat(import): publish a parcel store with each tileset (#006)`.
2. Confirmed. `frontend/src/parcelSelection.ts:17` and `CLAUDE.md:328`, DDD rules (Glossary in the same change): the "Selected parcel" row is added in `feat(map): show the details of a clicked parcel in a side panel (#006)` ("shown in the side panel"), extended in `feat(map): highlight the selected parcel (#006)` and `feat(map): keep the selected parcel in the URL (#006)` (`#parcel={id}`). Fixed in those three commits.
3. Confirmed. `tests/Fake/UnreadableParcelRepositoryUsingMemory.php:14`, Naming: the fake is removed and `ParcelRepositoryUsingMemory` has an `unreadable` flag that makes `get()` throw `ParcelStoreUnreadable`; its parcels now come as a list argument, because a variadic could not be followed by the flag. Fixed in `feat(map): explain when parcels cannot be selected (#006)`.
