# Jičín Parcel Map

A web application that shows land parcels of the Jičín district (Czech Republic) on a map.
Clicking a parcel shows its details. The application stays smooth even when the whole
district is in view.

Built as an interview task for Viagem a.s.

> **Status:** the map page works: http://localhost:8000 shows an OpenStreetMap base map centred
> on Jičín (zoom 12) with the OSM attribution. `GET /api/map-config` serves the map
> configuration. `make data` downloads the ČÚZK parcels of the whole Jičín district (240 cadastral areas,
> 272,011 parcels) and builds a vector tileset from them; the map then shows the parcels over OSM, filled by
> land type (outlines from zoom 14) with the credit "ČÚZK, 2026". From zoom 14, clicking a parcel opens a side panel
> with its details. The interface is in Czech by default.
> The application is built in the tasks in [`.tasks/`](.tasks/). This README is updated as features land.

## Prerequisites

- Docker 24+ with Docker Compose v2
- GNU make (optional; it only wraps the `docker compose` commands shown below)

Nothing else is required on the host. PHP 8.6, Composer and all other tooling run inside the
container.

## Running

```bash
docker compose build
docker compose up        # installs dependencies, builds the frontend, serves http://localhost:8000
make data                # once: download the ČÚZK parcels and build the tileset (see Data)
```

The interface is Czech by default. The messages quoted below are the English texts of the Czech-by-default
interface. Two flag buttons above the legend (Czech, English) switch the language; the language's name shows on
hover. Switching re-renders every text in place: the page does not reload and the map and its tiles are untouched.
The chosen language is remembered in the browser (`localStorage`); without a stored choice the page is Czech,
whatever the browser language.

Before `make data` the page shows only the OpenStreetMap base map and a notice, "Parcel data has not
been imported yet. Run make data, then reload this page." After `make data`, reload the page: the parcels
appear over Jičín. If the data was imported by a version from before the parcel details (task 006), the page shows
"Parcel details have not been imported yet. Run make data, then reload this page." and clicks select nothing: rerun
`make data` to build the parcel store. From zoom 14, clicking a parcel opens a panel on the left with its number, plot type, cadastral
area, municipality, land type, land use (when it has one) and area; the close button, Escape or a click on no
parcel close it. The selected parcel is outlined on the map. Ownership is not in the open data (neither the owners
nor the ownership sheet number), so the panel links to the parcel in the ČÚZK cadastre viewer
("Show owners in the ČÚZK cadastre viewer", opens in a new tab), which shows them after a login or CAPTCHA.

The selected parcel is kept in the URL as `#parcel=<RÚIAN id>` (updated with `history.replaceState`, so clicking does
not fill the browser history; closing the panel removes it). Opening such a link fits the map to the parcel (at most
zoom 18) and shows its panel, and changing the hash selects another parcel. The link has the id only, not the tileset
version, so it survives re-imports; an id that no longer exists shows "This parcel was not found". Below zoom 14 clicks select nothing, and a hint at the bottom says "Zoom in to select a parcel." until zoom 14, while no parcel panel is open.

A land type legend sits in the bottom-left corner of the map (open on wide screens, collapsed up to 640 px width; click
"Land type" to toggle it). It lists the land type colours and "Unknown land type".

`docker compose up` runs a one-shot `node` service (`npm ci && npm run build`), then starts the
PHP server. The first run pulls the Playwright image (about 2 GB), which is also used for all
Node tooling. `GET /` is the map page, `GET /api/map-config` the map configuration (initial view, base
map and, once data is imported, the `parcels` tile layer; `parcels` is `null` before), and every
other path answers `404 {"error":"not_found"}`.

### Data

Run `make data` once (and again whenever you want a newer export). It runs
`bin/console parcels:import [--export-date=YYYY-MM-DD]`, which

1. downloads the monthly RÚIAN VFR file of every municipality in `config/coverage.csv`
   (`https://vdp.cuzk.gov.cz/vymenny_format/soucasna/{YYYYMMDD}_OB_{municipality}_UKSH.xml.zip`;
   files are dated on the last day of a month and appear the next day, so without
   `--export-date` the import takes the latest one and falls back once to the month before);
2. converts the parcels of the listed cadastral areas to WGS84 with GDAL (`ogr2ogr`), reading each
   municipality file once into `data/build/municipalities/{municipality}.geojsonl`, and reads its parts of the
   municipality, streets, buildings and address points in a second GDAL run into
   `data/build/places/{municipality}.sqlite` (the search index builder joins them);
3. builds one vector tileset with tippecanoe and checks it against the tile size budget;
4. builds the parcel store, a SQLite file of parcel details (number, area, land type, land use, boundary), and
   the search index, a SQLite file of parcel numbers with their bounds, and publishes both together with the
   tileset under one version, atomically.

The list of cadastral areas, `config/coverage.csv`, is generated from the RÚIAN state structure file
(`{YYYYMMDD}_ST_UZSZ.xml.zip`, same directory as the municipality files) by `make coverage`, which runs
`bin/console coverage:generate --district=3604 [--export-date=YYYY-MM-DD]`. Only cadastral areas with a digital
map are listed (areas without one have no parcel polygons). `make data` never rewrites the list: run
`make coverage` deliberately, review the diff and commit it.

Data lives in `data/` (git-ignored): `cache/ruian/` holds the downloads (a cached file is never
downloaded again, so a rerun is offline), `build/` the intermediate files, `tiles/parcels.mbtiles` the
published tileset, `parcels/{version}.sqlite` the published parcel store (about 46 MB for the district,
zlib-compressed boundaries) and `search/{version}.sqlite` the published search index. `config/coverage.csv` lists the whole district: 240 cadastral areas of 111
municipalities, 272,011 parcels. The first `make data` downloads 111 municipality files (54 MB per monthly
export) and takes about 1 minute; a rerun from the cache about 45 seconds (with the search index and its places about 85 seconds; measured on 2026-11-24 from the cache: 57 s before the places, 85 s after, so +28 s). The search index of the district has 33,000 places and is 27 MB. The tileset is about 56 MB and
the intermediate GeoJSON in `build/` about 130 MB. The tile build needs about 1 to 2 GB of memory (Docker Desktop
users: allow at least 4 GB). Smoothness gate: a vector tile is limited to 200 kB (gzip; at most about 20 tiles are visible on a 1920x1080
screen, so a view stays under 4 MB). The limit is passed to tippecanoe, and after the build the import reads the
tileset back: if any tile is larger it fails with "Tile z/x/y has N bytes, over the budget of 200000 bytes; the
tileset was not published." and nothing is published. On success it prints the tile count and the largest tile
per zoom level. The import prints its progress (one line per municipality download and conversion,
then the tile build, the parcel store build and the search index build). A failed run keeps the completed downloads in the cache, so a rerun resumes. ČÚZK publishes
the monthly files over about an hour on the 1st; if a later municipality is not published yet the import stops
with "The RÚIAN export of … for municipality … is not published": rerun it later or pass `--export-date` of the
month before. Old cached months and build files are kept; delete them by hand when needed.

The store and the search index of the previous tileset are kept next to the new ones, because requests in flight may
still read them; older ones are deleted. An unknown land type or land use code (one outside the published ČÚZK code list) does not fail
the import: it is stored, and the import prints a warning per code on stderr.

The data is licensed CC BY 4.0; the required credit is "ČÚZK, {year of the export}". Conditions:
https://cuzk.gov.cz/Predpisy/Podminky-poskytovani-prostor-dat-a-sitovych-sluzeb/Podminky-poskytovani-prostorovych-dat-CUZK.aspx

### Parcel tiles

`GET /tiles/parcels/{version}/{z}/{x}/{y}.pbf` serves the vector tiles from `data/tiles/parcels.mbtiles`.
The tileset version is part of the URL, so a tile is immutable: `Cache-Control: public, max-age=31536000,
immutable`, and after a re-import the URLs change. Tiles are stored gzip-compressed and sent as they are
when the client accepts gzip (MapLibre does not decompress them itself); otherwise they are decompressed
first. A tile without data answers `204`; an unknown version, a bad coordinate or no imported data answers
`404`. The compose file starts the built-in PHP server with `PHP_CLI_SERVER_WORKERS=8` so one slow request
does not block the others.

### Parcel details

`GET /api/parcels/{version}/{id}` serves the details of one parcel from the parcel store of that tileset
version, `id` being the RÚIAN parcel id (the feature id in the tiles): number, kind (`building` or `land`),
cadastral area and municipality (code and name), land type and land use as RÚIAN codes (`landUseCode` is `null`
when there is none), area in m², the boundary as a GeoJSON Polygon, `cadastreViewerUrl` and its `bounds` (west, south, east, north). It is one primary-key lookup. Like tiles,
the response is immutable for its version (`Cache-Control: public, max-age=31536000, immutable`). It answers `404`
for an unknown id, an id that is not 1 to 15 digits, another version, a missing parcel store or no imported data.
`GET /api/map-config` tells the frontend where to ask: `parcels.parcelUrlTemplate`, e.g.
`/api/parcels/20261124150349/{id}`, or `null` when the parcel store of the current tileset is missing.

### Search

`GET /api/search/{version}?q=<text>&near=<lon>,<lat>` answers `{"places": [...], "parcels": [...]}` from the search
index `data/search/{version}.sqlite` of that tileset version (`near` is the map centre; equal matches are ordered by
distance from it). Typing is read the way people write parcel numbers: `72` finds land 72 and building plot st. 72,
`st. 72` building plots only, `80/2` the subdivision, numbers match exactly (`80` does not suggest `80/2`); a word
narrows the parcels to the cadastral areas whose name or municipality name has a word starting with it
(`867/121 rob`), and a 6-digit code to one cadastral area (`867/121 740225`). Matching ignores case and diacritics.
Places (municipalities, cadastral areas, parts of municipalities, streets and addresses such as
`Valdštejnovo náměstí 58`) are found when the text has a word of two letters or more: every word or number must start a
word of the place's name or context ("Staré Město, Jičín"); the exact name comes first, then names starting with the text,
then the kind and the distance from `near`. A municipality, a cadastral area and a part of the same name are one place
(the biggest kind). An address is indexed when its building stands on a covered parcel, and carries that parcel's id.
At most 8 places and 8 parcels come back. Like tiles and parcel details the response is immutable for its version
(`Cache-Control: public, max-age=31536000, immutable`); `400` for a missing or blank `q`, a text over 100
characters or a malformed `near`, `404` for another version or no index, `405` for other methods.
`GET /api/map-config` tells the frontend where to ask: `parcels.searchUrl`, or `null` when the search index of the
current tileset is missing (data imported before the search was added: run `make data`).

In the browser the search box sits at the top left (the parcel panel below it), and shows the places and parcels found as you
type (a request starts 200 ms after the last keystroke; a newer request aborts the one in flight). It is an ARIA
combobox: Arrow Up/Down move through the results, Enter opens the active one (or the first), Escape closes the list
(a second Escape closes the parcel panel). Picking a parcel selects it exactly as a link does: panel, highlight,
`#parcel=` and the map fitted to it. Picking an address selects the parcel its building stands on; picking another place
closes an open parcel panel and fits the map to the place (at most zoom 17; a place without bounds is eased to at zoom 17). The box adds no map listener and typing causes no tile request. Without a search
index of the current version the box is not shown.

### Frontend

TypeScript, built with Vite, MapLibre GL JS from npm (bundled, no CDN). Edit `frontend/`, then run
`make assets` or keep `make watch` running. The build goes to `public/build/` (git-ignored).

The interface texts are in `frontend/languages/<code>.json`, one flat file per language (`cs.json` is the reference:
every other file must have exactly its keys and placeholders, which a test checks). Edit the wording there; the page
title and the fallback error of `frontend/index.html` repeat the Czech texts and a test keeps them equal.

## Testing and QA

`make` wraps `docker compose`; the plain command is shown for each target. `PHP` below means
`docker compose run --rm --no-deps app` and `NODE` means `docker compose run --rm --no-deps node`.

| Make target       | Plain command                                                                 | What it does                                  |
|-------------------|-------------------------------------------------------------------------------|-----------------------------------------------|
| `make check`      | (runs every check group, see below)                                           | Full local check, same groups as CI           |
| `make qa`         | `cs`, `phpstan`, `deptrac`, `lint-ci`, `typecheck`, `lint-frontend`           | All static checks                             |
| `make test`       | `test-unit`, `test-integration`, `test-api`, `test-frontend`, `test-e2e`      | All tests                                     |
| `make test-unit`  | `PHP vendor/bin/phpunit --testsuite unit`                                     | Unit tests                                    |
| `make test-integration` | `PHP vendor/bin/phpunit --testsuite integration`                        | Adapters against real GDAL, tippecanoe, SQLite and a local HTTP server |
| `make data`       | `PHP bin/console parcels:import`                                              | Download the RÚIAN export and build the parcel tileset, store and search index |
| `make test-api`   | starts the test app (`docker compose -p parcel-map-test -f compose.yaml -f compose.test.yaml up -d --wait --no-deps app`), then `phpunit --testsuite api` in it | API tests over real HTTP and CLI |
| `make cs`         | `PHP vendor/bin/php-cs-fixer check --diff`                                    | Code style (PER-CS)                           |
| `make cs-fix`     | `PHP vendor/bin/php-cs-fixer fix`                                             | Fix code style                                |
| `make phpstan`    | `PHP vendor/bin/phpstan analyse --no-progress`                                | Static analysis, level max                    |
| `make deptrac`    | `PHP vendor/bin/deptrac analyse --no-progress --fail-on-uncovered --report-uncovered` | Layer dependency rules                |
| `make typecheck`  | `NODE npm run typecheck`                                                      | `tsc -b`                                      |
| `make lint-frontend` | `NODE npm run lint`, `NODE npm run format:check`                           | ESLint (type-aware) and Prettier check        |
| `make format`     | `PHP vendor/bin/php-cs-fixer fix`, `NODE npm run format`                      | Format PHP and frontend code                  |
| `make test-frontend` | `NODE npm test`                                                            | Vitest unit tests                             |
| `make perf`       | `NODE npm run perf`                                                           | Browser measurement of the district views against the app of `make up` with real data (`make data` first); not part of `make check` |
| `make test-e2e`   | starts the test app, then `npm run e2e` in the test project                   | Playwright E2E (needs `make assets` first)    |
| `make assets`     | `NODE npm ci`, `NODE npm run build`                                           | Build the frontend into `public/build/`       |
| `make watch`      | `NODE npm run watch`                                                          | Rebuild the frontend on change                |
| `make lint-ci`    | `docker run --rm -v "$PWD":/repo -w /repo rhysd/actionlint:1.7.12 .github/workflows/ci.yml` | Lint the GitHub workflow        |

Run `composer install` first (`make install-php`); the `make` check groups do it for you.

The API and E2E tests run against their own app: a separate compose project (`parcel-map-test`,
`compose.test.yaml`) with no published port and the fixture data in `tests/Fixtures/data`, so
`make check` never restarts or changes the app of `make up`. `make down` stops both projects.

### Smoothness evidence

The main quality criterion is that the map stays smooth with the whole district in view. Three kinds of evidence:

1. **The tile budget gate** (import time): no vector tile may exceed 200 kB gzip, so a view of at most about 20
   tiles stays under 4 MB. An import that produces a larger tile fails and publishes nothing.
2. **`make perf`** (`tests/Perf/district.spec.ts`, Playwright, needs `make up` and `make data`): opens the whole
   district (zoom 10), Jičín at zoom 12 and at zoom 14 at 1920x1080, then pans around. It asserts budgets that do
   not depend on the hardware: at most 30 parcel tile requests and 4 MB while loading, a p95 tile response time of at
   most 100 ms, tiles settled within 5 s, and every tile answering 200 or 204. It also records frame intervals and
   long tasks and writes the report to `var/perf/district-perf.json`, but does not assert them: Docker's headless
   Chromium renders WebGL in software, so frame times say little about real hardware. Measured on 2026-11-20: the
   district view loads 9 tiles (263 kB), Jičín at zoom 12 loads 15 tiles (1.9 MB), tile responses take 13 to 56 ms.
3. **Search latency** (`tests/Perf/search.spec.ts`, part of `make perf`): every query of a fixed list is asked 20
   times over HTTP and must have a p95 of at most 30 ms; the report goes to `var/perf/search-perf.json`. It uses
   Node's fetch, because Playwright's request context and Chromium add about 40 ms to every response of PHP's
   built-in server (even a 404) in the Docker setup.
4. **A manual check in Chrome** on real hardware (performance panel while panning and zooming over the district
   at zoom 10 and over Jičín at zoom 12 to 16: no long tasks over 50 ms once the tiles have loaded).

## Continuous integration

`.github/workflows/ci.yml` runs one parallel job per check group in the Makefile variable
`CHECK_TARGETS` (`make ci-targets` prints the list as JSON), plus an aggregate job `check` that
fails if any group fails. `make check` runs the same groups one after another locally, so CI and
local runs cannot drift. Adding a group to `CHECK_TARGETS` adds a CI job without touching the
workflow.

Make `check` a required status check once the repository is public (rulesets need a public
repository or a paid plan; until then CI reports on pull requests but merging is not
technically blocked):

1. Push the branch and open a pull request so the `check` job has run at least once.
2. Settings, Rules, Rulesets, New ruleset, New branch ruleset. Name it `main`, set Enforcement
   status to **Active**. Target branches, Add target, Include default branch.
3. Enable **Require a pull request before merging**.
4. Enable **Require status checks to pass**. Add the check `check` (source: GitHub Actions)
   with **+**. Optionally enable "Require branches to be up to date before merging".
5. Enable **Block force pushes**. Click **Create**.

## Architecture

Hexagonal architecture with DDD, written in plain PHP (no framework):

| Layer            | Role                                                                |
|------------------|---------------------------------------------------------------------|
| `Domain`         | Parcels, cadastral areas and their rules. No dependencies.          |
| `Application`    | Use cases and the ports they need.                                  |
| `Infrastructure` | Outbound adapters: data storage, ČÚZK data download, geo tooling.   |
| `Handler`        | Inbound HTTP adapters (map configuration, later tiles and parcel detail). |
| `Command`        | Inbound CLI adapters (data download and import).                    |

The data is downloaded from ČÚZK and preprocessed ahead of time. It is not fetched live at
request time, because live ČÚZK services cannot serve a whole district smoothly (see Notes).

Full rules are in [`CLAUDE.md`](CLAUDE.md).

## Development workflow

Development uses Claude Code with three agents and strict TDD:

1. **plan** (`.claude/agents/plan.md`, stronger model): analyses a feature request, iterates on
   it with the developer, and writes a task file in `.tasks/` describing the architecture of
   the change, its vertical-slice steps and test scenarios.
2. **implement** (`.claude/agents/implement.md`, cheaper model): implements the task file
   test-first, step by step, and commits each step locally.
3. **review** (`.claude/agents/review.md`, stronger model, read-only): reviews the commits of a
   task branch against the architecture and language rules of `CLAUDE.md`.
4. The developer reviews the commits and pushes. Claude never pushes; this is enforced in
   `.claude/settings.json`.

Each task runs on its own local branch, `task/NNN-slug`, or `refactor/NNN-slug` for a task that
only changes how the code is written.

The review loop repeats until a round has no findings: the developer confirms findings by
number, implement folds the fixes into the commits they belong to, and a fresh review runs.

Tests: unit, integration, API (real HTTP and CLI) and frontend (unit + browser E2E).

## Notes

A running log of decisions, surprises and what I would do with more time.

### Decisions

- **Pre-downloaded data, not live ČÚZK services.** The ČÚZK cadastral WMS only renders at
  large scales, and the INSPIRE WFS limits features per request and is slow. Neither supports
  a smooth district-wide view.
- **Vector tiles.** Hundreds of thousands of polygons stay smooth because the browser only
  draws what is in view, simplified for the current zoom.
- **Whole district as the goal.** With pre-built vector tiles, covering the whole district
  costs about the same as covering four cadastral areas.
- **Offline search of the covered area only.** The search runs on an index that the import builds from the RÚIAN
  exports it already downloads, so no query leaves the server, tests need no network and a query takes a few
  milliseconds. An external geocoder (query text would leave the server, API keys or an unreliable public instance)
  and a Czech Republic-wide address search are left out on purpose.
- **Plain PHP, hexagonal architecture.** No framework; the domain stays independent of storage
  and delivery, so the storage can change (e.g. to PostGIS) without touching the domain.
- **Source: the RÚIAN VFR monthly municipality export.** One feature carries the parcel
  attributes and the polygon, and GDAL reads it straight from the zip. The cadastral-area SHP
  splits attributes and polygons into two layers, INSPIRE lacks the land type, and the open VFK
  has no parcel rows.
- **GDAL and tippecanoe are installed in the app image** (+287 MB), because the import use case
  drives them through process adapters in the same container. Separate tool containers would be
  the preferred design (lean runtime image, independent tool upgrades, isolation). One image is
  enough for now: the import is the only consumer and the process adapters need the binaries in
  the same container.
- **Low zooms leave out the smallest parcels instead of merging them.** A tile over the 200 kB budget drops its
  smallest parcels (tippecanoe `--drop-smallest-as-needed`, only below zoom 14). Merging them
  (`--coalesce-densest-as-needed`) painted blocks of several square kilometres with one parcel's land type at zoom
  9 to 11, so the map showed wrong land types; dropping keeps every drawn parcel true and lets the base map show
  through the gaps. It is also faster (an 18 s tile build instead of 65 s).
- **Immutable, versioned tile URLs.** The browser never asks the single-threaded PHP server for the
  same tile twice; empty tiles answer 204 (a 404 would be logged as a console error by the browser).
- **The parcel details are a separate SQLite file per version, not part of the tiles.** Putting the attributes into
  the tiles would grow every tile, and smoothness is the main requirement. Selecting a parcel costs one small request
  (a primary-key lookup). The tileset rename is the single commit point of a publication: the store is placed first
  under its version, then the tileset is renamed onto the live path, so a reader never sees tiles without their store.
- **No logging in this version.** Nothing is logged: the import prints warnings (such as unknown land type or land
  use codes) to stderr, and the server reports uncaught errors to stderr. A production version would have a logger
  that records every error and any other meaningful information.
- **Parcels can be selected from zoom 14 only.** Below zoom 14 the tiles leave out the smallest parcels and parcels
  are a pixel or two wide, so a click would hit the wrong parcel or nothing. It is the same constant as the outline
  layer (`COMPLETE_PARCELS_MIN_ZOOM`).
- **Texts are data.** One JSON file per language in `frontend/languages/`, emitted by Vite as separate
  content-hashed files (`/build/languages/cs-<hash>.json`) and never compiled into the JS bundle (a test checks
  that). The page fetches the texts of the start language in parallel with `GET /api/map-config`, so they are not on
  the critical path of the first map paint. Land type and land use names are in the same files, keyed by the RÚIAN
  codes. The PHP built-in server sends no cache headers for static files, so the file is fetched on every page load
  (about 4 KB).
- **Switching the language re-renders in place.** A `Localization` object holds the current texts and the languages
  loaded so far (the other language is fetched on first use, then cached). Every component has a `setTexts`; the map,
  its style, layers and sources are not touched, so no tile is requested again. MapLibre has no runtime locale
  setter, so its own labels (zoom buttons, canvas) are set through the DOM.
- **The flags are hand-written SVG files** (`frontend/languages/<code>.svg`, emitted next to the texts), because
  Windows draws flag emoji as two letters. The attribution ("© OpenStreetMap contributors", "ČÚZK, 2026") is not
  translated: it comes from the map configuration and changing it would mean changing a map source.
- **The panel's height caps include the language switcher** (`calc(100% - 19.75rem)`, `24.25rem` up to 800 px), so
  on a short window the panel shows a little less before it scrolls.
- **The selected parcel is outlined by a GeoJSON overlay, not by feature-state or a filter.** The overlay is a
  one-feature source (`selected-parcel`) drawn from the boundary in the parcel details, as a white casing under a
  magenta line. Feature-state expressions would make the paint data of every parcel feature depend on the selection,
  and `setFilter` re-lays out every visible tile of the source; the overlay changes one small source, works at every
  zoom and for a parcel that is not in view yet.
- **The ownership gap is closed with a link, not data.** The owners and the LV number are not in any open data set,
  so each parcel links to its page in ČÚZK's Nahlížení do katastru nemovitostí
  (`https://nahlizenidokn.cuzk.gov.cz/ZobrazObjekt.aspx?typ=parcela&id={RÚIAN id}`). ČÚZK documents no such URL; it was
  verified against the live service on 2026-11-23 and lives in one constant (`Parcel::CADASTRE_VIEWER_URL`). Its bot
  protection blocks scripted access, so no test opens it.
- **Docker for everything.** Same PHP version and tools on every machine.
- **PHP 8.6.** It is still at the RC stage at the time of setup (`php:8.6-rc-cli` image). The
  Dockerfile switches to the GA image once it is released.
- **Vite with fixed asset names** (`/build/app.js`, `/build/app.css`). The hand-written HTML
  shell can reference them without a manifest. Cost: no cache-busting yet.
- **The legend is a static MapLibre control built from the frontend land type catalogue.** Plain `<details>` DOM,
  no map or DOM listener, so it costs nothing while panning or zooming.
- **On windows up to 800 px the legend is lifted above the zoom hint** (`bottom: 4.75rem`), so the two never overlap
  and the hint can stay at the bottom centre. The parcel panel's height cap reserves room for the open legend. The
  E2E test `layout.spec.ts` checks that no two map elements overlap at four viewport sizes.
- **The Playwright image is used for all Node tooling.** One platform (glibc) for `node_modules`.
  The image and `@playwright/test` must have the same version; bump them together.
- **TypeScript 6.0.3** until typescript-eslint supports TypeScript 7.
- **E2E tests never contact OpenStreetMap.** Tiles are stubbed in the browser and any other
  request that leaves the app fails the test (the OSM tile policy forbids automated traffic).
- **E2E reaches the app as `parcel-map`, not `app`.** Chromium treats the single-label host `app`
  as the HSTS-preloaded `.app` TLD and upgrades it to HTTPS.
- **PHP-CS-Fixer runs with `setUnsupportedPhpVersionAllowed(true)`** until it supports PHP 8.6.

### Surprises

_To be filled in._

### With more time

- A search of the whole Czech Republic from the ČÚZK address CSV
  (`https://vdp.cuzk.gov.cz/vymenny_format/csv/<date>_OB_ADR_csv.zip`: 63 MB zipped, about 2.95 million address points,
  CC BY 4.0, monthly). Measured while planning: a synthetic index of 2.97 million places builds in 18 s and takes 369 MB;
  queries stay under 25 ms at p95 when addresses sit in their own full-text table and are searched only when the
  query has a number. Outside the covered area it would find places only and move the map there.
- An external geocoder with points of interest (query text would leave the server; Nominatim forbids autocomplete,
  Mapy.com needs an API key and its branding, a self-hosted Photon needs Java and a 1 GB Czech dump).
- Typo tolerance in the search (none today: "jicn" finds nothing).
- Generate the frontend code catalogues and the PHP known-code lists of land types and land uses from one source;
  today they are pinned by separate tests and a code-list change must update both by hand.
- A generalised land-cover overview layer (parcels dissolved by land type) for low zooms, instead of leaving out
  small parcels there.
- Serve `/build/languages/` with immutable caching in a production server: the file names are content-hashed, but
  the PHP built-in server sends no cache headers.
- Docker layer cache in CI (BuildKit GitHub Actions cache), to avoid rebuilding the PHP image
  in several jobs and pulling the Playwright image on every run.
- Activate the ruleset once the repository is public.
- Separate tool containers for GDAL and tippecanoe, and a pinned
  S-JTSK to WGS84 coordinate transformation (GDAL's default is used today).
