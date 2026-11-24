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
> with its details.
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
   municipality file once into `data/build/municipalities/{municipality}.geojsonl`;
3. builds one vector tileset with tippecanoe and checks it against the tile size budget;
4. builds the parcel store, a SQLite file of parcel details (number, area, land type, land use, boundary), and
   publishes it together with the tileset under one version, atomically.

The list of cadastral areas, `config/coverage.csv`, is generated from the RÚIAN state structure file
(`{YYYYMMDD}_ST_UZSZ.xml.zip`, same directory as the municipality files) by `make coverage`, which runs
`bin/console coverage:generate --district=3604 [--export-date=YYYY-MM-DD]`. Only cadastral areas with a digital
map are listed (areas without one have no parcel polygons). `make data` never rewrites the list: run
`make coverage` deliberately, review the diff and commit it.

Data lives in `data/` (git-ignored): `cache/ruian/` holds the downloads (a cached file is never
downloaded again, so a rerun is offline), `build/` the intermediate files, `tiles/parcels.mbtiles` the
published tileset and `parcels/{version}.sqlite` the published parcel store (about 46 MB for the district,
zlib-compressed boundaries). `config/coverage.csv` lists the whole district: 240 cadastral areas of 111
municipalities, 272,011 parcels. The first `make data` downloads 111 municipality files (54 MB per monthly
export) and takes about 1 minute; a rerun from the cache about 45 seconds. The tileset is about 56 MB and
the intermediate GeoJSON in `build/` about 130 MB. The tile build needs about 1 to 2 GB of memory (Docker Desktop
users: allow at least 4 GB). Smoothness gate: a vector tile is limited to 200 kB (gzip; at most about 20 tiles are visible on a 1920x1080
screen, so a view stays under 4 MB). The limit is passed to tippecanoe, and after the build the import reads the
tileset back: if any tile is larger it fails with "Tile z/x/y has N bytes, over the budget of 200000 bytes; the
tileset was not published." and nothing is published. On success it prints the tile count and the largest tile
per zoom level. The import prints its progress (one line per municipality download and conversion,
then the tile build and the parcel store build). A failed run keeps the completed downloads in the cache, so a rerun resumes. ČÚZK publishes
the monthly files over about an hour on the 1st; if a later municipality is not published yet the import stops
with "The RÚIAN export of … for municipality … is not published": rerun it later or pass `--export-date` of the
month before. Old cached months and build files are kept; delete them by hand when needed.

The store of the previous tileset is kept next to the new one, because requests in flight may still read it; older
stores are deleted. An unknown land type or land use code (one outside the published ČÚZK code list) does not fail
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
`/api/parcels/20261123140206/{id}`, or `null` when the parcel store of the current tileset is missing.

### Frontend

TypeScript, built with Vite, MapLibre GL JS from npm (bundled, no CDN). Edit `frontend/`, then run
`make assets` or keep `make watch` running. The build goes to `public/build/` (git-ignored).

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
| `make data`       | `PHP bin/console parcels:import`                                              | Download the RÚIAN export and build the parcel tileset |
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
3. **A manual check in Chrome** on real hardware (performance panel while panning and zooming over the district
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
- **English interface, ready for translations.** Every string of the panel lives in `frontend/src/text.ts`, and the
  land type and land use names in `landTypes.ts` and `landUses.ts`, keyed by the RÚIAN codes the API returns. A later
  language switch replaces these lookups, not the components.
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

- Generate the frontend code catalogues and the PHP known-code lists of land types and land uses from one source;
  today they are pinned by separate tests and a code-list change must update both by hand.
- A generalised land-cover overview layer (parcels dissolved by land type) for low zooms, instead of leaving out
  small parcels there.
- Docker layer cache in CI (BuildKit GitHub Actions cache), to avoid rebuilding the PHP image
  in several jobs and pulling the Playwright image on every run.
- Activate the ruleset once the repository is public.
- Separate tool containers for GDAL and tippecanoe, and a pinned
  S-JTSK to WGS84 coordinate transformation (GDAL's default is used today).
