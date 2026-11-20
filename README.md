# Jičín Parcel Map

A web application that shows land parcels of the Jičín district (Czech Republic) on a map.
Clicking a parcel shows its details. The application stays smooth even when the whole
district is in view.

Built as an interview task for Viagem a.s.

> **Status:** the map page works: http://localhost:8000 shows an OpenStreetMap base map centred
> on Jičín (zoom 12) with the OSM attribution. `GET /api/map-config` serves the map
> configuration. `make data` downloads the ČÚZK parcels of the municipality Jičín and builds a
> vector tileset from them; the map then shows the parcels over OSM, filled by land type (outlines
> from zoom 14) with the credit "ČÚZK, 2026". Clicking a parcel is not implemented yet. The application is built in the tasks in
> [`.tasks/`](.tasks/). This README is updated as features land.

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
appear over Jičín.

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
2. converts the parcels of each listed cadastral area to WGS84 with GDAL (`ogr2ogr`);
3. builds one vector tileset with tippecanoe and publishes it atomically.

Data lives in `data/` (git-ignored): `cache/ruian/` holds the downloads (a cached file is never
downloaded again, so a rerun is offline), `build/` the intermediate files and `tiles/parcels.mbtiles`
the published tileset. Today `config/coverage.csv` lists the 5 cadastral areas of the municipality
Jičín (16,972 parcels, a 3.7 MB download, a 3.5 MB tileset); covering the whole district means
adding its areas to that file.

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
| `make test-e2e`   | starts the test app, then `npm run e2e` in the test project                   | Playwright E2E (needs `make assets` first)    |
| `make assets`     | `NODE npm ci`, `NODE npm run build`                                           | Build the frontend into `public/build/`       |
| `make watch`      | `NODE npm run watch`                                                          | Rebuild the frontend on change                |
| `make lint-ci`    | `docker run --rm -v "$PWD":/repo -w /repo rhysd/actionlint:1.7.12 .github/workflows/ci.yml` | Lint the GitHub workflow        |

Run `composer install` first (`make install-php`); the `make` check groups do it for you.

The API and E2E tests run against their own app: a separate compose project (`parcel-map-test`,
`compose.test.yaml`) with no published port and the fixture data in `tests/Fixtures/data`, so
`make check` never restarts or changes the app of `make up`. `make down` stops both projects.

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

Development uses Claude Code with two agents and strict TDD:

1. **plan** (`.claude/agents/plan.md`, stronger model): analyses a feature request, iterates on
   it with the developer, and writes a task file in `.tasks/` describing the architecture of
   the change, its vertical-slice steps and test scenarios.
2. **implement** (`.claude/agents/implement.md`, cheaper model): implements the task file
   test-first, step by step, and commits each step locally.
3. The developer reviews the commits and pushes. Claude never pushes; this is enforced in
   `.claude/settings.json`.

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
- **Immutable, versioned tile URLs.** The browser never asks the single-threaded PHP server for the
  same tile twice; empty tiles answer 204 (a 404 would be logged as a console error by the browser).
- **Docker for everything.** Same PHP version and tools on every machine.
- **PHP 8.6.** It is still at the RC stage at the time of setup (`php:8.6-rc-cli` image). The
  Dockerfile switches to the GA image once it is released.
- **Vite with fixed asset names** (`/build/app.js`, `/build/app.css`). The hand-written HTML
  shell can reference them without a manifest. Cost: no cache-busting yet.
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

- Docker layer cache in CI (BuildKit GitHub Actions cache), to avoid rebuilding the PHP image
  in several jobs and pulling the Playwright image on every run.
- Activate the ruleset once the repository is public.
- Separate tool containers for GDAL and tippecanoe, import progress output, and a pinned
  S-JTSK to WGS84 coordinate transformation (GDAL's default is used today).
