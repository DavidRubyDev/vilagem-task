# 001: OSM base map centred on Jičín

- **Status:** approved
- **Created:** 2026-11-19
- **Request:** "The first end-user MVP: a web page showing an OpenStreetMap base map centred on
  Jičín (the district town, Czech Republic), with tests. No parcels, no ČÚZK data, no import
  pipeline yet." The task also sets up all project tooling in Docker (Composer, PHPUnit, PHPStan
  max, PHP-CS-Fixer PER-CS, deptrac, Vitest, Playwright, one entry point for test and QA
  commands). Added during planning: "use ts" (TypeScript frontend) and "lets add git actions for
  tests and all the code quality tools. They must succeed before the branch can be merged."

## Goal

After `docker compose up` on a clean clone, http://localhost:8000 shows an interactive MapLibre
map with OpenStreetMap raster tiles. It is centred on Jičín at zoom 12, can be panned and
zoomed, and shows OSM attribution that is always visible. The map configuration (initial view,
base map definition) comes from the backend through all hexagonal layers. This also builds the
walking skeleton (front controller, router, kernel, Bootstrap) that every later endpoint
reuses. The task also delivers all PHP and TypeScript tooling in Docker, one Makefile entry
point, and GitHub Actions CI running every test level and QA check in parallel.

## Scope

**In scope:**

- PHP tooling: Composer project, PHPUnit, PHPStan (max), PHP-CS-Fixer (PER-CS), deptrac with
  the CLAUDE.md layer rule. Dockerfile gains `mbstring`.
- HTTP walking skeleton: `public/index.php`, `Bootstrap\Container`, `Handler/Http/` plumbing,
  JSON 404 / 405 / 500.
- `GET /api/map-config`: initial map view (Jičín, zoom 12) and the OSM base map, through
  Domain → Application → Infrastructure → Handler.
- `GET /`: the HTML page.
- Frontend in TypeScript: Vite build into `public/build/`, MapLibre GL JS from npm (bundled,
  no CDN), zoom buttons, attribution that never collapses.
- Error state when the configuration cannot be loaded.
- Node tooling in Docker: TypeScript `tsc -b`, ESLint (type-aware), Prettier, Vitest, Playwright.
- Makefile as the single entry point. README, CLAUDE.md "Commands" and Glossary updates.
- GitHub Actions CI with one aggregate required check `check`. README instructions for the
  developer to make it required (Claude does not change GitHub settings).

**Out of scope:**

- Parcels, ČÚZK data, import commands, `bin/console` (no CLI in this task).
- Base map switching (ČÚZK orthophoto, Mapy.cz). Restricting the map to the district bounds.
  Fitting the view to the whole district.
- Production web server, HTTPS, asset cache-busting (file names are fixed in this task).
- Code coverage. Docker layer cache in CI. Visual (screenshot) regression tests.
- Changing GitHub repository settings (rulesets). The developer does that.

## Decisions

Decisions made with the developer during planning, each with a short reason.

1. **The map configuration is served by the backend through all layers** (`GET /api/map-config`).
   It builds the HTTP plumbing every later endpoint reuses (tiles, parcel detail). It gives a
   clear place to add later configuration, such as district bounds from MBTiles metadata or
   more base maps. It also follows the OSM tile policy's advice not to hard-code the tile URL
   in the client.
2. **The page shell is served by PHP** (`GET /` → `ShowMapPageHandler` returns
   `frontend/index.html`). With `php -S … -t public`, `/` always resolves to `public/index.php`
   (index.php takes precedence over index.html), so a static `public/index.html` cannot serve
   `/`. The handler has no use case because there is no application behaviour, only a static
   page. That is an allowed degenerate inbound adapter.
3. **Vite 8.3.8 builds the frontend with fixed file names** (`/build/app.js`, `/build/app.css`,
   `/build/maplibre-gl-worker.mjs`). MapLibre comes from npm and is bundled, so the app has no
   external runtime dependency except the tile server. With fixed names the hand-written HTML
   shell can reference the assets without a manifest, and the API test of `GET /` does not
   need a frontend build. Cost: no cache-busting yet.
4. **One Node image for all Node tooling:** `mcr.microsoft.com/playwright:v1.65.1-noble`
   (Node 26.13.1, Chromium included). `node_modules` is then always installed for one platform
   (glibc). Packages installed on Alpine (musl) would break Vite's native bundler (rolldown)
   in the Playwright image. `@playwright/test` must match the image version exactly. Both are
   pinned at 1.65.1 and must be bumped together.
5. **`docker compose up` works from a clean clone.** The one-shot `node` service runs
   `npm ci && npm run build` and exits. `app` waits for it to finish
   (`service_completed_successfully`), runs `composer install`, then starts the server. Dev and
   test runs do not differ in this task: the stub tiles exist only inside Playwright's browser.
6. **Makefile is the single entry point.** It wraps `docker compose`. `make` is a host tool,
   so README and CLAUDE.md also list the plain `docker compose …` command for the main targets.
   There are no composer scripts duplicating the Makefile.
7. **Initial view: Jičín town centre, zoom 12** (about 15 × 9 km in a 1280×720 viewport: the
   town and its surroundings). A later task may switch to a whole-district view.
8. **TypeScript 6.0.3, strict.** TypeScript 7.0.2 (npm `latest`, the native compiler) is not
   supported by typescript-eslint 8.73.0 (peer `typescript >=4.8.4 <6.1.0`). Upgrade when it is.
9. **ESLint (typescript-eslint `strictTypeChecked`) and Prettier are part of this task.** Strict
   type-aware linting catches unsafe `any` use that `tsc` alone lets through. Adding them later
   would mean reformatting code in an extra commit.
10. **CI = parallel jobs generated from the Makefile.** The Makefile variable `CHECK_TARGETS`
    is the only list of check groups. `make check` runs them one after another locally. CI
    reads the list (`make ci-targets`) into a matrix and runs one job per group in parallel. A
    final aggregate job `check` is the only required status check, so the required-check list
    never changes when groups are added. CI and local runs cannot drift.
11. **actionlint runs in `make qa`** (group `lint-ci`). It is the only way to catch workflow
    errors before pushing, because the agents never push and cannot run CI.
12. **No Docker layer cache in CI for now.** CI runs the same `docker compose build` as local
    development. Composer and npm download caches are kept with `actions/cache`. Docker layer
    caching is noted under README "With more time".
13. **The repository is private on a Free plan.** Rulesets / required status checks cannot be
    enforced until the repository becomes public (planned) or moves to a paid plan. Until then
    CI reports on every PR but merging is not technically blocked. The developer accepts this.
    README words the ruleset steps accordingly.
14. **Unit tests also cover the pure HTTP plumbing** (`Router`, `HttpKernel`, `Response`,
    `Request`) in `tests/Unit/Handler/Http/`. These classes do no I/O, so the "Unit" rules (no
    I/O, fast) hold. The page and configuration handlers are covered by API tests only.
15. **`StaticMapSettings` (Infrastructure) has no separate test.** It only holds constants and
    uses no external resource. Its exact values are asserted by the API test of
    `GET /api/map-config`, which drives its creation.
16. **E2E tests never reach the internet.** A Playwright context route answers every
    `https://tile.openstreetmap.org/{z}/{x}/{y}.png` request with a stub PNG and aborts any
    other request that leaves the app origin, recording it so the test fails. The OSM tile
    policy prohibits automated or headless traffic that forces tile rendering. No test hooks
    are added to production code: the centre, zoom and pan are asserted through which tiles are
    requested.
17. **US spelling `center`** in code and JSON, matching MapLibre's API.
18. **No Cache-Control header on `/api/map-config`** in this task. Browser defaults are fine for
    a tiny local response.

## Verified facts

All verified on 2026-11-19.

**PHP and Composer**

- `php:8.6-rc-cli` (the current Dockerfile base) is **PHP 8.6.0RC7**. It has `curl`, `dom`,
  `json`, `tokenizer`, `xmlwriter`, `pdo_sqlite`, `sqlite3`, but **no `mbstring`**, which
  PHPUnit requires. `apt-get install libonig-dev` + `docker-php-ext-install mbstring` builds on
  RC7 and PHPUnit then runs. Verified by building a scratch image and running PHPUnit.
- In PHP 8.6 the predefined `$http_response_header` variable is deprecated (deprecation notice
  seen on RC7). The API test client therefore uses the curl extension.
- Composer installed and ran these on PHP 8.6.0RC7 (scratch project, `composer require --dev`):
  - **phpunit/phpunit 13.4.8.** Exits 0 with "No tests executed!" on an empty suite.
  - **phpstan/phpstan 2.3.7.** Level max passes on sample code using PHP 8.4+ syntax
    (`new Foo()->x`). Exits 1 with "No files found to analyse" when the paths are empty.
  - **friendsofphp/php-cs-fixer 3.95.32.** Refuses to run on 8.6 ("PHP CS Fixer currently
    supports PHP syntax only up to PHP 8.5 … Add `Config::setUnsupportedPhpVersionAllowed(true)`")
    unless the config sets `setUnsupportedPhpVersionAllowed(true)`. The `@PER-CS` rule set is
    accepted.
  - **deptrac/deptrac 4.7.2.** A `Vendor` layer defined as a `bool` collector (`must`:
    `classLike` `^(?!ParcelMap\\).*`, `must_not`: `php_internal` `.*`) flags a Domain class
    depending on `PHPUnit\Framework\TestCase` and allows `\InvalidArgumentException`. Exits 1 on
    violations. `--fail-on-uncovered` and `--report-uncovered` exist.
  - A root constraint `"php": "^8.6"` resolves on 8.6.0RC7.
- **PHP built-in server** ([php.net CLI web server](https://www.php.net/manual/en/features.commandline.webserver.php)):
  - If the URI does not match a file, it falls back to `index.php` / `index.html` in the
    docroot (and since PHP 8.4 also for missing paths whose last part contains a dot).
    Existing files are served directly.
  - It is single-threaded unless `PHP_CLI_SERVER_WORKERS` is set.
  - Verified on RC7: it serves `.mjs` files as `Content-Type: application/javascript`, which
    module scripts and module workers accept.

**npm packages** (npm registry, `npm view`; behaviour checked in scratch projects)

- **maplibre-gl 6.19.0** (BSD-3-Clause):
  - ESM only (`dist/maplibre-gl.mjs`, separate `dist/maplibre-gl-worker.mjs`,
    `dist/maplibre-gl.css`). It does not import any other package.
  - Exports are named (`Map`, `NavigationControl`, `setWorkerUrl`, types such as
    `MapOptions`, `StyleSpecification`). There is no default export.
  - Without a bundler it loads its worker from `./maplibre-gl-worker.mjs` next to its own
    module. When bundled, the worker URL must be set with `setWorkerUrl()`.
  - `attributionControl?: false | { compact?: boolean; customAttribution?: … }`. The default
    collapses the attribution on maps narrower than 640 px. `compact: false` forces it
    expanded.
  - The NavigationControl zoom-in button has the accessible name "Zoom in"
    (`NavigationControl.ZoomIn`).
  - MapLibre needs WebGL2.
- **vite 8.3.8** (Node `^20.19.0 || >=22.12.0`), **vitest 5.1.1** (Node
  `^22.12.0 || ^24.0.0 || >=26.0.0`, peer vite 6–8), **@playwright/test 1.65.1**,
  **typescript 6.0.3** (last 6.x; npm `latest` is 7.0.2, the native compiler),
  **typescript-eslint 8.73.0** (peers: eslint `^8.57.0 || ^9.0.0 || ^10.0.0`, typescript
  `>=4.8.4 <6.1.0`), **eslint 10.15.0** (Node `^20.19.0 || ^22.13.0 || >=24`),
  **@eslint/js 10.0.1**, **prettier 3.10.1**, **eslint-config-prettier 10.1.8**,
  **@types/node 26.6.6**.
- **Vite build** (scratch run in the Playwright image) with `vite.config.ts`, `publicDir: false`,
  `base: '/build/'`, `build.outDir: 'public/build'`, `build.rolldownOptions.input:
  { app: 'frontend/src/main.ts' }` and output names `[name].js` / `[name][extname]`:
  - It produces `app.js` (~1.05 MB, 291 kB gzip), `app.js.map`, `app.css` (~71 kB) and
    `maplibre-gl-worker.mjs`.
  - The worker comes from
    `import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url'` +
    `setWorkerUrl(workerUrl)`.
  - Vite prints a non-failing ">500 kB chunk" warning.
- **TypeScript setup** (same scratch run):
  - `tsc -b` with a solution `tsconfig.json` referencing `tsconfig.app.json` (with
    `types: ["vite/client"]`, so `?url` and CSS imports type-check) and `tsconfig.node.json`
    (`types: ["node"]`) passes.
  - Relative imports ending in `.ts` fail with TS5097 unless `allowImportingTsExtensions` is
    set, so imports are extensionless.
  - Vitest runs `.test.ts` files, using the `test` block of `vite.config.ts` with
    `defineConfig` from `vitest/config`.
  - Playwright runs `playwright.config.ts` and `.spec.ts` files.
- **ESLint** (same scratch run): `eslint.config.js` (JavaScript; a `.ts` config file needs the
  extra `jiti` package) with:
  - `defineConfig` from `eslint/config`, `js.configs.recommended`,
    `tseslint.configs.strictTypeChecked`.
  - `languageOptions.parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname }`.
  - `{ files: ['**/*.js'], extends: [tseslint.configs.disableTypeChecked] }`, needed or the
    config file itself fails with "not found by the project service".
  - `eslint-config-prettier` last.

  It passes on clean code and reports `no-explicit-any`, `no-unsafe-return` and
  `no-unsafe-member-access` on unsafe code. `prettier --check .` passes after
  `prettier --write .`.

**Playwright and Docker**

- Image `mcr.microsoft.com/playwright:v1.65.1-noble` exists on MCR (also `-jammy`) and
  contains **Node v26.13.1**. [Playwright Docker docs](https://playwright.dev/docs/docker): pin
  the image, keep the `@playwright/test` version equal to the image version, use `--init` and
  `--ipc=host`. Running as root disables the Chromium sandbox, which is acceptable for trusted
  E2E tests.
- Verified end to end in Docker. Setup: a PHP 8.6 built-in server container and a Playwright
  container (uid 1000, `HOME=/tmp`) on one network. All
  `https://tile.openstreetmap.org/**` requests were fulfilled with a 1×1 PNG.
  - MapLibre loaded and WebGL2 worked in headless Chromium with no extra flags, both unbundled
    and bundled with Vite.
  - `getCenter()` and `getZoom()` returned `[15.3516528, 50.4370452]` and 12.
  - 24 tiles were requested. The first was `13/4445/2762`: with `tileSize: 256` MapLibre
    requests tiles one zoom level above the map zoom.
  - Chromium logs `GL Driver Message (OpenGL, Performance, …): GPU stall due to ReadPixels`
    console messages under software rendering. These are harmless.
- Tile maths for the configured centre (lon 15.351653, lat 50.437045): z13 tile x = 4445.34,
  y = 2762.73 → `13/4445/2762`; z14 tile x = 8890.67, y = 5525.46 → `14/8890/5525`.
- Docker Compose (local 5.8; GitHub runner 2.38.2):
  - Inside a `docker compose run --rm --no-deps app` container, the host name `app` resolves
    only to the running `app` service container, not to the run container itself.
  - `docker compose up -d --wait app` exits 0 with a one-shot dependency using
    `condition: service_completed_successfully` and a healthcheck
    `php -r "exit(@fsockopen('127.0.0.1', 8000) ? 0 : 1);"`.

**OSM**

- [OSM tile usage policy](https://operations.osmfoundation.org/policies/tiles/):
  - Use exactly `https://tile.openstreetmap.org/{z}/{x}/{y}.png`. Do not hard-code it in the
    client.
  - Attribution must be clearly visible on the map with a link to the copyright page, and not
    hidden.
  - Websites must send a valid Referer, so do not set a Referrer-Policy that suppresses it.
  - Never send `no-cache` request headers.
  - Prohibited: bulk downloading or prefetching tiles; headless bots that pan or zoom the map
    to force tile rendering; offline use.
- [OSMF attribution guidelines](https://osmfoundation.org/wiki/Licence/Attribution_Guidelines):
  "© OpenStreetMap contributors" is acceptable, linked to
  `https://www.openstreetmap.org/copyright`. It must be visible without interaction and
  legible.
- Nominatim (`nominatim.openstreetmap.org/search`, one request each):
  - Jičín town: lat 50.4370452, lon 15.3516528 (used rounded to 50.437045, 15.351653).
  - okres Jičín bounding box: lat 50.2752954–50.5469582, lon 15.1042301–15.7415627 (not used
    in this task).

**GitHub Actions**

- [Runner image Ubuntu 24.04](https://github.com/actions/runner-images/blob/main/images/ubuntu/Ubuntu2404-Readme.md),
  version 20261111.370.1: Docker 28.0.4, Docker Compose v2 2.38.2, Buildx 0.38.2, GNU make 4.3,
  Node 22.23.4. `ubuntu-latest` is announced to move to Ubuntu 26.04 in November 2026, so the
  workflow pins `ubuntu-24.04`.
- Latest releases (GitHub API): actions/checkout **v7.0.1**, actions/cache **v6.1.0**,
  actions/upload-artifact **v7.0.2**. actionlint **v1.7.12** (Docker image
  `rhysd/actionlint:1.7.12`).
- actionlint 1.7.12 passed (exit 0) the draft workflow in this file: a matrix built with
  `fromJSON(needs.targets.outputs.list)` plus an aggregate job using `needs.*.result`. It
  rejected an invalid job key with exit 1. It must be given the file paths explicitly
  (`.github/workflows/ci.yml`) when the repository mount is not detected as a git project. The
  colour flag is `-no-color`.
- A plain-make target printing `CHECK_TARGETS` as JSON via `$(foreach)` / `$(subst)` produced
  valid JSON (`["lint-ci","php-qa",…]`).
- Rulesets
  ([about rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/about-rulesets),
  [creating rulesets](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/creating-rulesets-for-a-repository),
  [available rules](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-rulesets/available-rules-for-rulesets)):
  - Availability: public repositories on GitHub Free; private repositories only on Pro, Team
    or Enterprise.
  - UI path: Settings → Rules → Rulesets → New ruleset → New branch ruleset.
  - Each required status check is added by typing its name and clicking **+**.
  - "Require branches to be up to date before merging" is the strict option.
- The repository `DavidRubyDev/vilagem-task` is **private** (`gh repo view`). Default branch
  is `main`.

## Steps

Ordered vertical slices. Each one works end to end and is committed on its own with all tests green.
Commit 1 on the branch is this task file (`docs(tasks): add 001 OSM base map centred on Jičín`).

1. **Walking skeleton** (commit `feat(http): answer unknown routes with a JSON 404 (#001)`).
   The app runs in Docker and answers every path with `404 {"error":"not_found"}`. All PHP
   tooling, the Makefile and CI exist. Contents:
   - Dockerfile `mbstring`; `compose.yaml` app command, healthcheck and environment;
     `composer.json` / `composer.lock`.
   - Config files: `phpunit.xml.dist`, `phpstan.neon.dist`, `.php-cs-fixer.dist.php`,
     `deptrac.yaml`.
   - `Makefile` with `CHECK_TARGETS := lint-ci php-qa php-tests`.
   - `.github/workflows/ci.yml` (jobs `targets`, `run`, `check`; Composer cache only).
   - Code: `public/index.php`, `src/Bootstrap/Container.php`, `src/Handler/Http/*`.
   - Tests: `tests/Unit/Handler/Http/*`, `tests/Api/*` (unknown route → 404).
   - `.gitignore` / `.dockerignore`.
   - README: Prerequisites, Running, Testing and QA, Continuous integration, With more time.
   - CLAUDE.md "Commands" section.
2. **Map configuration API** (commit `feat(map): serve the initial map configuration (#001)`).
   `GET /api/map-config` returns the Jičín view and the OSM base map. `POST` to it returns
   405. Contents: the Domain `Map` value objects and exceptions; Application port, use case and
   DTOs; `StaticMapSettings`; `GetMapConfigurationHandler`; route in `Container`; unit tests,
   fake, API tests; CLAUDE.md Glossary additions. CI is unchanged.
3. **Map page** (commit `feat(map): show the OpenStreetMap base map centred on Jičín (#001)`).
   http://localhost:8000 shows the OSM map centred on Jičín at zoom 12, with zoom buttons,
   pan and zoom, and full attribution. Contents:
   - `ShowMapPageHandler` and its route.
   - The frontend: `frontend/index.html`, `frontend/src/*.ts`, `frontend/src/app.css`,
     `frontend/tests/*.test.ts`.
   - Node tooling: `package.json`, `package-lock.json`, `tsconfig*.json`, `vite.config.ts`,
     `eslint.config.js`, `.prettierrc.json`, `.prettierignore`, `playwright.config.ts`,
     `tests/E2E/*`.
   - Compose `node` service and the `app` → `node` dependency.
   - Makefile: Node targets, and `frontend-checks e2e` added to `CHECK_TARGETS`.
   - CI: npm cache step and Playwright artifact upload.
   - `.gitignore` / `.dockerignore` additions; README and CLAUDE.md Commands updates.
   - API test for `GET /`.
4. **Error state** (commit `feat(map): show an error when the map configuration cannot be loaded (#001)`).
   If `/api/map-config` fails, the page shows "The map could not be loaded. Please try again
   later." in an alert instead of a blank page. Contents: `MapConfigurationError` and the
   validation in `loadMapConfiguration`, the error rendering in `main.ts`, its CSS, Vitest
   cases, and an E2E case.

Every commit must pass `make check`, and actionlint must pass the workflow at every commit.

## Architecture

Paths and namespaces are binding. Method bodies and private helpers are left to the
implementation.

### Domain

Namespace `ParcelMap\Domain\Map` (directory `src/Domain/Map/`). All value objects are
`final readonly class`, constructed through the public constructor with named arguments (no
static factories needed), and have `equals(self $other): bool` comparing all fields by value.
Exceptions are `final class … extends \InvalidArgumentException`, each with named
constructors that build the message.

| Class | Constructor | Invariants (violations throw) |
|---|---|---|
| `Coordinates` | `__construct(public float $longitude, public float $latitude)` | both finite; `-180 ≤ longitude ≤ 180`; `-90 ≤ latitude ≤ 90` → `InvalidCoordinates` (`::longitudeOutOfRange(float)`, `::latitudeOutOfRange(float)`, `::notFinite()`) |
| `ZoomLevel` | `__construct(public float $value)` | finite; `0 ≤ value ≤ 24` → `InvalidZoomLevel` (`::outOfRange(float)`) |
| `MapView` | `__construct(public Coordinates $center, public ZoomLevel $zoom)` | none beyond its parts |
| `TileUrlTemplate` | `__construct(public string $value)` | starts with `https://`; is a valid URL once placeholders are replaced (e.g. by `0`, checked with `filter_var(…, FILTER_VALIDATE_URL)`); contains each of `{z}`, `{x}`, `{y}` → `InvalidTileUrlTemplate` (`::notHttps(string)`, `::missingPlaceholder(string $template, string $placeholder)`, `::notAUrl(string)`) |
| `Attribution` | `__construct(public string $text, public string $url)` | `trim(text) !== ''`; `url` passes `FILTER_VALIDATE_URL` and has scheme `https` → `InvalidAttribution` (`::emptyText()`, `::invalidUrl(string)`) |
| `BaseMap` | `__construct(public TileUrlTemplate $tileUrlTemplate, public int $tileSize, public int $maxZoom, public Attribution $attribution)` | `tileSize ∈ {256, 512}`; `0 ≤ maxZoom ≤ 24` → `InvalidBaseMap` (`::unsupportedTileSize(int)`, `::maxZoomOutOfRange(int)`) |

No entities, domain services or repositories in this task.

Glossary additions (CLAUDE.md, in the step 2 commit; Czech column `—`, because these are not
source-data terms):

| Czech (source data) | Code name | Notes |
|---|---|---|
| — | `Coordinates` | WGS84 longitude/latitude in degrees. Longitude first, as in MapLibre and GeoJSON. |
| — | `ZoomLevel` | Web map zoom, 0–24 (MapLibre zoom, fractional allowed). |
| — | `MapView` | Camera position the map opens with: center + zoom. |
| — | `BaseMap` | Background raster tile layer under the data (OSM in task 001). |
| — | `TileUrlTemplate` | `https` URL with `{z}`, `{x}`, `{y}` placeholders. |
| — | `Attribution` | Required data credit shown on the map: text + link. |
| — | Map configuration | What the frontend needs to start the map: initial `MapView` + `BaseMap` (`GET /api/map-config`). |

### Application

Namespace `ParcelMap\Application\Map` (directory `src/Application/Map/`).

**Outbound port** (not a repository):

```php
interface MapSettings
{
    public function initialView(): MapView;
    public function baseMap(): BaseMap;
}
```

**Use case** `final readonly class GetMapConfiguration`
- Constructor: `__construct(private MapSettings $settings)`.
- Public method: `execute(): MapConfigurationOutput`.
- No input DTO, because the use case takes no input.
- Throws nothing of its own. Domain exceptions from an invalid configuration are not caught;
  they become HTTP 500 through the kernel.

**Output DTOs**: `final readonly class`, public promoted properties, primitives only.

```php
final readonly class MapConfigurationOutput
{
    public function __construct(public MapViewOutput $initialView, public BaseMapOutput $baseMap) {}
}

final readonly class MapViewOutput
{
    public function __construct(public float $centerLongitude, public float $centerLatitude, public float $zoom) {}
}

final readonly class BaseMapOutput
{
    public function __construct(
        public string $tileUrlTemplate,
        public int $tileSize,
        public int $maxZoom,
        public string $attributionText,
        public string $attributionUrl,
    ) {}
}
```

### Infrastructure

`ParcelMap\Infrastructure\Map\StaticMapSettings` (`final readonly`) implements
`Application\Map\MapSettings`. It uses no external resource. It builds the domain objects from
private constants:

| Value | Constant |
|---|---|
| center longitude | `15.351653` |
| center latitude | `50.437045` |
| initial zoom | `12.0` |
| tile URL template | `https://tile.openstreetmap.org/{z}/{x}/{y}.png` |
| tile size | `256` |
| max zoom | `19` |
| attribution text | `© OpenStreetMap contributors` |
| attribution URL | `https://www.openstreetmap.org/copyright` |

A class comment says why the values live here: this is the configuration adapter, and later
tasks may replace parts with values from data (e.g. MBTiles metadata).

### Handlers (HTTP)

**HTTP plumbing**: namespace `ParcelMap\Handler\Http` (`src/Handler/Http/`).

- `final readonly class Request`
  - Fields: `public string $method` (upper-case), `public string $path`.
  - `static fromServer(array $server): self`, documented `@param array<string, mixed> $server`.
    It reads `REQUEST_METHOD` (default `GET`) and `REQUEST_URI` (default `/`). The path is the
    URI without the query string (`parse_url(…, PHP_URL_PATH)`; fallback `/`).
- `final readonly class Response`
  - Fields: `public int $status`, `public array $headers` (`array<string, string>`),
    `public string $body`.
  - Named constructors:
    - `json(array $data, int $status = 200)`: `Content-Type: application/json`, body
      `json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`.
    - `html(string $html)`: 200, `Content-Type: text/html; charset=utf-8`.
    - `notFound()`: 404 `{"error":"not_found"}`.
    - `methodNotAllowed(list<string> $allowedMethods)`: 405 `{"error":"method_not_allowed"}`
      plus `Allow: GET` (comma-separated list).
    - `internalError()`: 500 `{"error":"internal_error"}`.
  - The `json()` data parameter is typed `array<string, mixed>`, with a comment that the
    payload is a JSON tree (justified `mixed`).
- `interface RequestHandler { public function handle(Request $request): Response; }`
- `final readonly class Route`: `__construct(public string $method, public string $path, public RequestHandler $handler)`.
- `final readonly class Router`
  - `__construct(Route ...$routes)`; `dispatch(Request $request): Response`.
  - Exact path match, no parameters, no trailing-slash normalisation.
  - Path matches with the method too → that handler. Path matches with another method only →
    `Response::methodNotAllowed(<methods registered for the path>)`. No match →
    `Response::notFound()`.
  - `HEAD` is not special: it gets 405 on GET-only routes.
- `interface ErrorReporter { public function report(\Throwable $error): void; }`, and
  `final class StderrErrorReporter implements ErrorReporter`, which writes the throwable
  (`(string) $error`) with `error_log()`. It appears in `docker compose logs app`.
- `final readonly class HttpKernel`
  - `__construct(private Router $router, private ErrorReporter $errorReporter)`.
  - `handle(Request $request): Response` returns the router's response. It catches any
    `\Throwable`, reports it and returns `Response::internalError()`.
- `final class ResponseEmitter`: `emit(Response $response): void` calls
  `http_response_code()`, `header()` for each header, then `echo`es the body.

**Endpoint handlers**: namespace `ParcelMap\Handler` (`src/Handler/`), one per endpoint, each
`final readonly` and implementing `RequestHandler`.

- `GetMapConfigurationHandler(GetMapConfiguration $getMapConfiguration)` handles
  **`GET /api/map-config`**. It calls the use case and maps the output DTO to JSON:

  ```
  200 OK
  Content-Type: application/json

  {
    "initialView": {
      "center": { "longitude": 15.351653, "latitude": 50.437045 },
      "zoom": 12
    },
    "baseMap": {
      "tileUrlTemplate": "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
      "tileSize": 256,
      "maxZoom": 19,
      "attribution": {
        "text": "© OpenStreetMap contributors",
        "url": "https://www.openstreetmap.org/copyright"
      }
    }
  }
  ```

  `zoom` is a float in PHP; `json_encode` writes `12.0` as `12`. Clients and tests compare
  numerically.
- `ShowMapPageHandler(string $pageFile)` handles **`GET /`**. It returns `Response::html()`
  with the file's contents. If the file cannot be read it throws `MapPageUnavailable`
  (`final class … extends \RuntimeException`, in `ParcelMap\Handler`, named constructor
  `::cannotRead(string $path)`), which the kernel turns into 500.

**Route table** (registered in `Container`):

| Method | Path | Handler | Step |
|---|---|---|---|
| GET | `/api/map-config` | `GetMapConfigurationHandler` | 2 |
| GET | `/` | `ShowMapPageHandler` | 3 |

**Error mapping** (all bodies JSON, `Content-Type: application/json`):

| Situation | Status | Body |
|---|---|---|
| Unknown path (any method) | 404 | `{"error":"not_found"}` |
| Known path, wrong method | 405 + `Allow: GET` | `{"error":"method_not_allowed"}` |
| Any uncaught throwable | 500 | `{"error":"internal_error"}` (throwable reported to stderr) |

Static files that exist under `public/` (e.g. `/build/app.js`) are served directly by the PHP
built-in server and never reach `index.php`. A missing `/build/…` file reaches `index.php` →
404 JSON.

**`public/index.php`**
1. Declares strict types and requires `dirname(__DIR__) . '/vendor/autoload.php'`.
2. Calls `(new Container(dirname(__DIR__)))->httpKernel()->handle(Request::fromServer($_SERVER))`.
3. Calls `(new ResponseEmitter())->emit($response)`.

No other logic.

### Commands (CLI)

None in this task (`bin/console` is introduced by the first task that needs a CLI action).

### Frontend

TypeScript, ES modules, built by Vite. Relative imports are extensionless.

**`frontend/index.html`** (served by `ShowMapPageHandler`):
- `<!doctype html>`, `<html lang="en">`, `<meta charset="utf-8">`,
  `<meta name="viewport" content="width=device-width, initial-scale=1">`,
  `<title>Jičín Parcel Map</title>`.
- `<link rel="stylesheet" href="/build/app.css">`.
- Body: `<div id="map"></div>` and `<script type="module" src="/build/app.js"></script>`.
- No `<meta name="referrer">`: OSM requires a Referer, and the browser default policy sends
  the origin.

**`frontend/src/app.css`**: `html, body` margin 0 and full height; `#map` fills the viewport
(`position: absolute; inset: 0`); `.map-error` (step 4) is a readable message box centred on
the page.

**`frontend/src/mapConfiguration.ts`**

```ts
export interface MapConfiguration {
  readonly initialView: {
    readonly center: { readonly longitude: number; readonly latitude: number };
    readonly zoom: number;
  };
  readonly baseMap: {
    readonly tileUrlTemplate: string;
    readonly tileSize: number;
    readonly maxZoom: number;
    readonly attribution: { readonly text: string; readonly url: string };
  };
}

export class MapConfigurationError extends Error {}            // step 4

export function loadMapConfiguration(fetchFn: typeof fetch, url: string): Promise<MapConfiguration>;
```

`loadMapConfiguration` calls `fetchFn(url, { headers: { Accept: 'application/json' } })` and
parses the JSON as `unknown`.
- Step 3: it returns the parsed value narrowed to `MapConfiguration` by a runtime check of
  every field's type (numbers finite, strings).
- Step 4: it rejects with `MapConfigurationError` (message says what went wrong) when the
  fetch rejects (network error), the status is not 2xx, the body is not JSON, or any field is
  missing or has the wrong type.

**`frontend/src/mapOptions.ts`**

```ts
export function createMapOptions(configuration: MapConfiguration, container: HTMLElement): MapOptions;
```

Pure function, no MapLibre runtime import (`import type` only). Returns:
- `container`
- `center: [longitude, latitude]`
- `zoom`
- `attributionControl: { compact: false }`
- `style`: a `StyleSpecification` with `version: 8`, one source `base-map` and one layer:
  - source `{ type: 'raster', tiles: [tileUrlTemplate], tileSize, maxzoom: maxZoom, attribution: <html> }`
  - layer `{ id: 'base-map', type: 'raster', source: 'base-map' }`

The attribution HTML is `<a href="URL">TEXT</a>`. `&`, `<`, `>`, `"` and `'` are escaped in
both URL and text.

**`frontend/src/main.ts`** is the entry point. It is not unit-tested; E2E covers it.
1. Imports `maplibre-gl/dist/maplibre-gl.css` and `./app.css`.
2. Imports `workerUrl` from `maplibre-gl/dist/maplibre-gl-worker.mjs?url` and calls
   `setWorkerUrl(workerUrl)` before creating the map.
3. Finds `#map` and awaits `loadMapConfiguration(window.fetch.bind(window), '/api/map-config')`.
4. Creates `new Map(createMapOptions(config, container))` and adds
   `new NavigationControl({ showCompass: false })` at `top-right`.
5. Step 4: on any error it removes the `#map` element and appends
   `<p class="map-error" role="alert">The map could not be loaded. Please try again later.</p>`
   to `body`. It does not create a map.

It registers no handlers on `move`, `render` or similar frequent events, so panning stays
fully within MapLibre's GPU rendering.

**Build and config files:**
- `vite.config.ts` uses `defineConfig` from `vitest/config`:
  - `publicDir: false`, `base: '/build/'`.
  - `build`:
    - `outDir: 'public/build'`, `emptyOutDir: true`, `sourcemap: true`
    - `chunkSizeWarningLimit: 1200`, with a comment: MapLibre alone is ~1 MB minified
    - `rolldownOptions`:
      - `input: { app: 'frontend/src/main.ts' }`
      - `output: { entryFileNames: '[name].js', chunkFileNames: '[name].js', assetFileNames: '[name][extname]' }`
  - `test: { include: ['frontend/tests/**/*.test.ts'], environment: 'node' }`
- `tsconfig.json`: `{ "files": [], "references": [{ "path": "./tsconfig.app.json" }, { "path": "./tsconfig.node.json" }] }`.
- `tsconfig.app.json`:
  - `include: ["frontend"]`
  - `composite: true`, `tsBuildInfoFile: "var/cache/tsc/app.tsbuildinfo"`
  - `target: "ES2023"`, `lib: ["ES2023", "DOM", "DOM.Iterable"]`
  - `module: ESNext`, `moduleResolution: bundler`
  - `strict`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes`, `verbatimModuleSyntax`,
    `isolatedModules`, `skipLibCheck`, `noEmit`
  - `types: ["vite/client"]`
- `tsconfig.node.json`: the same strictness without `exactOptionalPropertyTypes` and
  `DOM.Iterable`; `lib: ["ES2023", "DOM"]` (Playwright types reference DOM),
  `types: ["node"]`, `include: ["vite.config.ts", "playwright.config.ts", "tests/E2E"]`,
  `tsBuildInfoFile: "var/cache/tsc/node.tsbuildinfo"`.
- `eslint.config.js`:
  - `ignores: ['vendor/', 'node_modules/', 'public/build/', 'var/']`
  - `js.configs.recommended`, `tseslint.configs.strictTypeChecked`
  - `parserOptions: { projectService: true, tsconfigRootDir: import.meta.dirname }`
  - `disableTypeChecked` for `**/*.js`
  - `eslint-config-prettier` last
  - All through `defineConfig` from `eslint/config`.
- `.prettierrc.json`: `{ "singleQuote": true, "printWidth": 120 }`. `.prettierignore`:
  `*.md`, `composer.json`, `composer.lock`, `package-lock.json`, `vendor/`, `node_modules/`,
  `public/build/`, `var/`, `data/`, `.tasks/`. Prettier also formats the YAML files
  (`compose.yaml`, `deptrac.yaml`, `.github/workflows/ci.yml`), `frontend/index.html`, CSS,
  JSON and TS. Run `make format` once when Prettier is introduced and commit the result in the
  same commit.
- `package.json`:
  - `"private": true`, `"type": "module"`, exact versions (no `^`).
  - `dependencies`: `maplibre-gl` `6.19.0`.
  - `devDependencies`: `vite` 8.3.8, `vitest` 5.1.1, `@playwright/test` 1.65.1, `typescript`
    6.0.3, `typescript-eslint` 8.73.0, `eslint` 10.15.0, `@eslint/js` 10.0.1, `prettier`
    3.10.1, `eslint-config-prettier` 10.1.8, `@types/node` 26.6.6.
  - `scripts`:

    | Script | Command |
    |---|---|
    | `build` | `vite build` |
    | `watch` | `vite build --watch` |
    | `typecheck` | `tsc -b` |
    | `lint` | `eslint .` |
    | `format:check` | `prettier --check .` |
    | `format` | `prettier --write .` |
    | `test` | `vitest run` |
    | `e2e` | `playwright test` |

  - `package-lock.json` is committed.
- `playwright.config.ts`:
  - `testDir: 'tests/E2E'`, `outputDir: 'var/playwright'`
  - `forbidOnly: !!process.env['CI']`, `retries: 0`, `reporter: 'list'`
  - `use: { baseURL: process.env['APP_URL'] ?? 'http://app:8000', trace: 'retain-on-failure' }`
  - one project `chromium` with `devices['Desktop Chrome']` (1280×720)

**E2E support**: `tests/E2E/support/offlineTiles.ts` exports a Playwright `test` extended with
an automatic fixture. The fixture:
- Installs a context route on `**/*`:
  - requests to the app origin (`baseURL`) continue;
  - requests matching `https://tile.openstreetmap.org/<z>/<x>/<y>.png` are fulfilled with a 1×1
    PNG (`image/png`, from the base64 constant
    `iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==`,
    commented as "generated 1×1 PNG, no external origin") and their `z/x/y` paths are
    recorded;
  - every other request is aborted and recorded as unexpected.
- Records page errors (`pageerror`) and console messages of type `error`, ignoring Chromium's
  software-GL messages that contain `GL Driver Message`.
- After each test, asserts there were no unexpected requests, page errors or console errors.
- Gives tests a way to wait for a specific tile (`z/x/y`) to be requested, and to read the set
  of tiles requested so far.

Helper names below this level are up to the implementation.

### Wiring

`ParcelMap\Bootstrap\Container` (`final readonly`):
- `__construct(private string $projectDir)`.
- `public function httpKernel(): HttpKernel` builds:
  - Step 1: `new HttpKernel(new Router(), new StderrErrorReporter())`.
  - Step 2: adds `new Route('GET', '/api/map-config', new GetMapConfigurationHandler(new GetMapConfiguration(new StaticMapSettings())))`.
  - Step 3: adds `new Route('GET', '/', new ShowMapPageHandler($this->projectDir . '/frontend/index.html'))`.

Wiring only, no other logic.

### Tooling and repository files

**`Dockerfile`** (step 1): add `libonig-dev` to the existing `apt-get install` and
`docker-php-ext-install -j"$(nproc)" mbstring`. Keep the base image ARG and its RC comment,
`COMPOSER_HOME`, `WORKDIR`, `EXPOSE` and `CMD`.

**`compose.yaml`**
- Step 1, service `app`:
  - `build: .`
  - `user: "${UID:-1000}:${GID:-1000}"`
  - `volumes: [.:/app]`
  - `ports: ["8000:8000"]`
  - `environment: { COMPOSER_CACHE_DIR: /app/var/cache/composer }`
  - `command: ["sh", "-c", "composer install --no-interaction --no-progress && exec php -S 0.0.0.0:8000 -t public"]`
  - `healthcheck`: `test: ["CMD", "php", "-r", "exit(@fsockopen('127.0.0.1', 8000) ? 0 : 1);"]`,
    `interval: 2s`, `timeout: 2s`, `retries: 60`
- Step 3, service `node`:
  - `image: mcr.microsoft.com/playwright:v1.65.1-noble`, with a comment: must equal the
    `@playwright/test` version in `package.json`
  - `user: "${UID:-1000}:${GID:-1000}"`, `working_dir: /app`, `volumes: [.:/app]`
  - `environment: { HOME: /tmp, npm_config_cache: /app/var/cache/npm, CI: "${CI:-}" }`
  - `init: true`, `ipc: host`
  - `command: ["sh", "-c", "npm ci --no-audit --no-fund && npm run build"]`
- Step 3, `app` gains `depends_on: { node: { condition: service_completed_successfully } }`.

**`composer.json`** (step 1):
- `"name": "parcel-map/parcel-map"`, `"type": "project"`, `"license": "proprietary"`.
- `"require": { "php": "^8.6" }`.
- `"require-dev"`: `ext-curl` `*`, `phpunit/phpunit` `^13.4`, `phpstan/phpstan` `^2.3`,
  `friendsofphp/php-cs-fixer` `^3.95`, `deptrac/deptrac` `^4.7`.
- `"autoload": { "psr-4": { "ParcelMap\\": "src/" } }`,
  `"autoload-dev": { "psr-4": { "ParcelMap\\Tests\\": "tests/" } }`.
- `"config": { "sort-packages": true }`.
- `composer.lock` is committed. No runtime Composer dependencies.

**`phpunit.xml.dist`** (step 1):
- Bootstrap `vendor/autoload.php`, `cacheDirectory="var/cache/phpunit"`, `colors="true"`.
- `failOnWarning`, `failOnRisky` and `failOnDeprecation` all `true`.
- `<source><include><directory>src</directory></include></source>`.
- Test suites: `unit` → `tests/Unit`, `api` → `tests/Api`.
- `<php><env name="APP_URL" value="http://app:8000" force="false"/></php>`.

No `integration` suite yet: add it with the first integration test. If a third-party package
triggers deprecations on PHP 8.6 RC, set `ignoreIndirectDeprecations="true"` on `<source>`
and log it. Do not disable `failOnDeprecation`.

**`phpstan.neon.dist`** (step 1): `level: max`, `paths: [src, tests, public]`,
`tmpDir: var/cache/phpstan`. No extensions, no baseline.

**`.php-cs-fixer.dist.php`** (step 1):
- Rules `@PER-CS` and `declare_strict_types`; `setRiskyAllowed(true)`.
- `setUnsupportedPhpVersionAllowed(true)`, with a comment: PHP 8.6 RC is newer than the
  fixer's supported syntax; remove when supported.
- `setCacheFile('var/cache/php-cs-fixer.cache')`.
- Finder in `src`, `tests`, `public`, plus the config file itself.

**`deptrac.yaml`** (step 1):
- `paths: [./src]`.
- Layers by `classLike` regex: `Domain` `^ParcelMap\\Domain\\.*`, `Application`,
  `Infrastructure`, `Handler`, `Command`, `Bootstrap` (same pattern).
- `Vendor` layer: `bool` collector, `must: [{type: classLike, value: '^(?!ParcelMap\\).*'}]`,
  `must_not: [{type: php_internal, value: '.*'}]`.
- Ruleset:

  | Layer | May depend on |
  |---|---|
  | Domain | nothing |
  | Application | Domain |
  | Infrastructure | Domain, Application, Vendor |
  | Handler | Application |
  | Command | Application |
  | Bootstrap | Domain, Application, Infrastructure, Handler, Command, Vendor |
  | Vendor | nothing |

- Run with `--fail-on-uncovered --report-uncovered --no-progress`.

**`Makefile`** (step 1 creates it, step 3 extends it):
- Settings:
  - `export UID := $(shell id -u)` and `export GID := $(shell id -g)`, with a comment: compose
    runs containers as the host user; bash does not export `UID` and CI runners do not use
    uid 1000.
  - `COMPOSE := docker compose`, `PHP := $(COMPOSE) run --rm --no-deps app`,
    `NODE := $(COMPOSE) run --rm --no-deps node`, and the actionlint image tag variable
    `ACTIONLINT_IMAGE := rhysd/actionlint:1.7.12`.
  - `CHECK_TARGETS := lint-ci php-qa php-tests` (step 3 adds `frontend-checks e2e`).
  - Every target is `.PHONY`. This matters because `frontend` and `tests` are directories.
- Building blocks:

  | Target | Recipe |
  |---|---|
  | `build` | `$(COMPOSE) build app` |
  | `install-php` | needs `build`; `$(PHP) composer install --no-interaction --no-progress` |
  | `up-app` | `$(COMPOSE) up -d --wait --no-deps app` (`--no-deps` so API tests never need the frontend build) |
  | `up` | `$(COMPOSE) up` |
  | `down` | `$(COMPOSE) down` |
  | `install-node` (step 3) | `$(NODE) npm ci --no-audit --no-fund` |
  | `assets` (step 3) | needs `install-node`; `$(NODE) npm run build` |
  | `watch` (step 3) | `$(NODE) npm run watch` |

- Single tools:

  | Target | Recipe |
  |---|---|
  | `cs` | `$(PHP) vendor/bin/php-cs-fixer check --diff` |
  | `cs-fix` | `$(PHP) vendor/bin/php-cs-fixer fix` |
  | `phpstan` | `$(PHP) vendor/bin/phpstan analyse --no-progress` |
  | `deptrac` | `$(PHP) vendor/bin/deptrac analyse --no-progress --fail-on-uncovered --report-uncovered` |
  | `test-unit` | `$(PHP) vendor/bin/phpunit --testsuite unit` |
  | `test-api` | needs `up-app`; `$(PHP) vendor/bin/phpunit --testsuite api` |
  | `lint-ci` | `docker run --rm -u "$(UID):$(GID)" -v "$(CURDIR)":/repo -w /repo $(ACTIONLINT_IMAGE) -no-color .github/workflows/ci.yml` |
  | `typecheck` (step 3) | `$(NODE) npm run typecheck` |
  | `lint-frontend` (step 3) | `$(NODE) npm run lint` then `$(NODE) npm run format:check` |
  | `format` (step 3) | `cs-fix` + `$(NODE) npm run format` |
  | `test-frontend` (step 3) | `$(NODE) npm test` |
  | `test-e2e` (step 3) | needs `up-app`; `$(NODE) npm run e2e` |

- Groups (each is one CI job):

  | Group | Runs |
  |---|---|
  | `lint-ci` | actionlint |
  | `php-qa` | `install-php cs phpstan deptrac` |
  | `php-tests` | `install-php test-unit test-api` |
  | `frontend-checks` (step 3) | `install-node typecheck lint-frontend test-frontend` |
  | `e2e` (step 3) | `install-php assets up-app test-e2e` |

- Aggregates:
  - `check: $(CHECK_TARGETS)`, the full local check; also used for
    `git rebase --exec "make check" main`.
  - `qa` = `cs phpstan deptrac lint-ci` (+ `typecheck lint-frontend` in step 3).
  - `test` = `test-unit test-api` (+ `test-frontend test-e2e` in step 3).
- `ci-targets` prints `CHECK_TARGETS` as a compact JSON array of strings, built with make
  functions only (`comma := ,`, `space := $(empty) $(empty)`,
  `$(subst $(space),$(comma),$(foreach t,$(CHECK_TARGETS),"$(t)"))` wrapped in `[` `]`). Must
  print exactly e.g. `["lint-ci","php-qa","php-tests"]`.

**`.github/workflows/ci.yml`** (step 1 creates it, step 3 adds two steps):

```yaml
name: CI
on:
  pull_request:
    branches: [main]
  push:
    branches: [main]
  workflow_dispatch:
permissions:
  contents: read
concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true
jobs:
  targets:            # reads the check groups from the Makefile (single source of truth)
    name: targets
    runs-on: ubuntu-24.04
    timeout-minutes: 5
    outputs:
      list: ${{ steps.list.outputs.list }}
    steps:
      - uses: actions/checkout@v7
      - id: list
        run: echo "list=$(make --no-print-directory -s ci-targets)" >> "$GITHUB_OUTPUT"
  run:
    name: ${{ matrix.target }}
    needs: targets
    runs-on: ubuntu-24.04
    timeout-minutes: 30
    strategy:
      fail-fast: false
      matrix:
        target: ${{ fromJSON(needs.targets.outputs.list) }}
    steps:
      - uses: actions/checkout@v7
      - uses: actions/cache@v6
        with:
          path: var/cache/composer
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: composer-
      # step 3 adds: actions/cache@v6 for var/cache/npm, key npm-${{ hashFiles('package-lock.json') }}, restore-keys npm-
      - run: make ${{ matrix.target }}
      # step 3 adds: if: failure() && matrix.target == 'e2e' → actions/upload-artifact@v7
      #   with name: playwright-results, path: var/playwright, if-no-files-found: ignore
  check:              # the only required status check; stable when groups are added
    name: check
    needs: [targets, run]
    if: always()
    runs-on: ubuntu-24.04
    timeout-minutes: 5
    steps:
      - name: Require every job to succeed
        if: contains(needs.*.result, 'failure') || contains(needs.*.result, 'cancelled') || contains(needs.*.result, 'skipped')
        run: exit 1
```

The workflow above (with the step 3 additions written out as real steps, not comments) is the
contract. The job ids and names `targets`, `run` (displayed per matrix entry as the target
name) and `check` must not change. `make lint-ci` must pass at every commit.

**`.gitignore`**:
- Keep the existing entries.
- Step 1 adds `/vendor/` and `/var/`.
- Step 3 adds `/node_modules/` and `/public/build/`.

**`.dockerignore`**:
- Keep `.git`.
- Step 1 adds `vendor`, `var`.
- Step 3 adds `node_modules`, `public/build`.

### Documentation updates per commit

**Step 1 (README)**
- Status line: the walking skeleton exists.
- Prerequisites: Docker 24+ with Compose v2; GNU make optional (wraps the commands).
- Running: `docker compose up` (also `make up`). At this step it answers JSON 404.
- "Testing and QA": table of make targets with the plain `docker compose …` equivalent for:
  `check`, `qa`, `test`, `test-unit`, `test-api`, `cs`, `cs-fix`, `phpstan`, `deptrac`,
  `lint-ci`.
- "Continuous integration":
  - What runs: one parallel job per `CHECK_TARGETS` group plus the aggregate `check`, and that
    `make check` runs the same groups locally.
  - The ruleset steps below, introduced as applying once the repository is public (or on a
    paid plan). Until then CI reports on PRs but merging is not technically blocked.
- Ruleset steps:
  1. Push the branch and open a PR so the `check` job has run at least once.
  2. Settings → Rules → Rulesets → New ruleset → New branch ruleset. Name `main`, Enforcement
     status **Active**. Target branches → Add target → Include default branch.
  3. Enable **Require a pull request before merging**.
  4. Enable **Require status checks to pass**. Add the check `check` (source: GitHub Actions)
     with **+**. Optionally enable "Require branches to be up to date before merging".
  5. Enable **Block force pushes**. Click **Create**.
- "With more time" (replace `_To be filled in._`):
  - Docker layer cache in CI (BuildKit GitHub Actions cache), to avoid rebuilding the PHP image
    in several jobs and pulling the Playwright image on every run.
  - Activate the ruleset once the repository is public.
- "Decisions": PHP-CS-Fixer runs with `setUnsupportedPhpVersionAllowed(true)` until it
  supports PHP 8.6.

**Step 1 (CLAUDE.md "Commands")**
- Replace the "created by the first task" sentence with the real commands: `make check`
  (full test + QA, also for `git rebase --exec`), `make qa`, `make test`, the single targets,
  `make up`, `make down`, and the plain `docker compose run --rm --no-deps app …` forms.
- Note that `make check` is what CI runs, split into parallel jobs.

**Step 2**
- CLAUDE.md Glossary: the rows listed under Domain.
- README Architecture / Status: mention `GET /api/map-config`.

**Step 3**
- README:
  - Status: the map page works.
  - Running: `docker compose up` builds the frontend and opens the map at
    http://localhost:8000.
  - Frontend notes: TypeScript + Vite + MapLibre; edit `frontend/` then `make assets` or keep
    `make watch` running.
  - Testing table: add `typecheck`, `lint-frontend`, `format`, `test-frontend`, `test-e2e`,
    `assets`, `watch`.
  - "Decisions":
    - Vite with fixed asset names.
    - Playwright image used for all Node tooling; bump image and package together.
    - TypeScript 6.0.3 until typescript-eslint supports TypeScript 7.
    - E2E tests never contact OSM.
- CLAUDE.md "Commands": the same Node targets and their `docker compose run --rm --no-deps node …`
  forms.

**Step 4**: README status line only, if the wording changes.

## Test scenarios

Behaviours to test, described in words, including edge cases and errors. Write the outer test
of each step first (API for steps 1–2, E2E for steps 3–4) and watch it fail for the right
reason.

### Unit

`tests/Unit/` mirrors `src/`. Fakes over mocks.

**Step 1, `tests/Unit/Handler/Http/`**
- Router:
  - dispatches a GET request to the handler registered for that exact path;
  - returns 404 `not_found` for an unknown path;
  - returns 404 for a path that differs only by a trailing slash;
  - returns 405 `method_not_allowed` with `Allow: GET` for a POST to a GET-only path;
  - with no routes at all, returns 404 for `/`.
- HttpKernel:
  - returns the router's response unchanged when no error occurs;
  - when a handler throws, returns 500 `internal_error` and reports exactly that throwable to
    the error reporter. Use an in-memory recording `ErrorReporter` fake and a throwing
    `RequestHandler` fake.
- Response:
  - `json()` sets `Content-Type: application/json` and writes `/` and non-ASCII characters
    unescaped (`©`, `https://…`);
  - `html()` sets `text/html; charset=utf-8`;
  - `methodNotAllowed(['GET'])` sets `Allow: GET`.
- Request::fromServer:
  - takes method and path, dropping the query string (`/api/map-config?x=1` →
    `/api/map-config`);
  - upper-cases the method;
  - defaults to `GET` and `/` when the keys are missing.

**Step 2, `tests/Unit/Domain/Map/`**
- Coordinates:
  - accepts Jičín;
  - accepts the boundary values ±180 / ±90;
  - rejects longitude 180.000001 and −180.000001, latitude 90.000001 and −90.000001;
  - rejects NAN and INF;
  - equals another instance with the same values and not one with a different latitude.
- ZoomLevel: accepts 0, 12.5 and 24; rejects −0.1, 24.1 and NAN.
- TileUrlTemplate:
  - accepts the OSM template;
  - rejects an `http://` template;
  - rejects templates missing `{z}`, `{x}` or `{y}` (one case each);
  - rejects an empty string and a non-URL.
- Attribution: accepts the OSM attribution; rejects empty or whitespace-only text; rejects an
  `http://` URL and a non-URL.
- BaseMap: accepts tile size 256 and 512; rejects 300; accepts max zoom 0 and 24; rejects −1
  and 25.
- MapView: exposes center and zoom; equals by value.

**Step 2, `tests/Unit/Application/Map/`**
- GetMapConfiguration returns an output DTO with the fake's values, mapped field by field:
  - longitude goes to `centerLongitude` and latitude to `centerLatitude` (use different
    values so a swap fails);
  - zoom;
  - tile URL template, tile size, max zoom, attribution text and URL.
- The fake is `tests/Fake/InMemoryMapSettings.php` (`ParcelMap\Tests\Fake`). It is
  constructed with a `MapView` and a `BaseMap` and returns them.

### Integration

- None in this task. No adapter uses an external resource; see Decision 15.

### API (HTTP and CLI)

`tests/Api/`, real HTTP over the compose network to `APP_URL` (default `http://app:8000`),
with the curl extension. If the app is unreachable the test fails with a message saying to run
`make up-app` (never skip).

- Step 1: `GET /does-not-exist` → 404, `Content-Type: application/json`, body
  `{"error":"not_found"}`.
- Step 2:
  - `GET /api/map-config` → 200, `Content-Type: application/json`, and the decoded body equals
    exactly the JSON in Handlers (numbers compared numerically: zoom 12, center 15.351653 /
    50.437045, tileSize 256, maxZoom 19);
  - `POST /api/map-config` → 405, header `Allow: GET`, body `{"error":"method_not_allowed"}`;
  - `GET /api/map-config/` (trailing slash) → 404.
- Step 3: `GET /` → 200, `Content-Type: text/html; charset=utf-8`, and the body contains
  `<div id="map">`, `src="/build/app.js"`, `href="/build/app.css"` and
  `<title>Jičín Parcel Map</title>`. This passes without a frontend build.

No CLI in this task.

### Frontend (unit and E2E)

**Vitest** (`frontend/tests/`, pure modules, no DOM, no network; fetch is an in-test fake
function).

- Step 3, `mapOptions.test.ts`:
  - center is `[longitude, latitude]` (use distinct values so the order is checked);
  - zoom is passed through;
  - the container is passed through;
  - style version 8 with exactly one raster source `base-map` whose `tiles` is
    `[tileUrlTemplate]`, with `tileSize` and `maxzoom`;
  - exactly one raster layer `base-map` using that source;
  - attribution HTML is
    `<a href="https://www.openstreetmap.org/copyright">© OpenStreetMap contributors</a>`;
  - `<`, `>`, `&`, `"` and `'` in attribution text and URL are escaped;
  - `attributionControl` is `{ compact: false }`.
- Step 3, `mapConfiguration.test.ts`:
  - requests the given URL with header `Accept: application/json`;
  - returns the configuration for a valid 200 response (the JSON from Handlers).
- Step 4, `mapConfiguration.test.ts` adds: rejects with `MapConfigurationError` when
  - the fetch rejects (network error);
  - the status is 500;
  - the status is 404;
  - the body is not JSON;
  - `initialView.center.longitude` is missing;
  - `zoom` is a string;
  - `baseMap.attribution.url` is not a string.

**Playwright E2E** (`tests/E2E/`, uses the offline-tiles fixture; Chromium 1280×720; against
the running app).

- Step 3, `map.spec.ts`:
  - Opening `/` shows a visible `.maplibregl-canvas` and requests tile `13/4445/2762` (the tile
    containing the Jičín center at zoom 12).
  - The attribution link with text `© OpenStreetMap contributors` and href
    `https://www.openstreetmap.org/copyright` is visible without any interaction, and the
    attribution is not collapsed (no `.maplibregl-compact` class on the control).
  - Clicking the button named "Zoom in" requests tile `14/8890/5525`.
  - Dragging the map 400 px to the left with the mouse requests at least one tile that was not
    requested before the drag.
  - Across all tests: no request left the app origin except stubbed OSM tiles, and there were
    no page errors or console errors (fixture assertions).
- Step 4, `map-error.spec.ts`: when `/api/map-config` is answered with 500 (test-level route),
  the alert "The map could not be loaded. Please try again later." is visible and the page has
  no `.maplibregl-canvas`.

## Acceptance criteria

Observable, checkable outcomes, including performance where relevant.

- [ ] From a clean clone, `docker compose up` installs dependencies, builds the frontend and
      serves http://localhost:8000. The page shows the OSM map centred on Jičín's town square
      at zoom 12.
- [ ] The map pans by dragging and zooms with the mouse wheel and the +/− buttons.
- [ ] "© OpenStreetMap contributors" is always visible on the map (also at widths below 640 px)
      and links to https://www.openstreetmap.org/copyright.
- [ ] `curl -s localhost:8000/api/map-config` returns exactly the JSON in Handlers;
      `curl -si localhost:8000/nope` returns 404 `{"error":"not_found"}`.
- [ ] Stopping the API (or answering it with 500) shows the visible error message, not a blank
      page.
- [ ] The browser loads no third-party resources except `tile.openstreetmap.org` tiles (no CDN).
      The tile URL appears only in the backend configuration, not in frontend code.
- [ ] `make check` passes at every commit of the branch
      (`git rebase --exec "make check" main`).
  - [ ] PHPUnit unit + api, PHPStan max, PHP-CS-Fixer and deptrac (with uncovered dependencies
        failing) pass.
  - [ ] actionlint passes.
  - [ ] Step 3 on: `tsc -b`, ESLint, Prettier, Vitest and Playwright pass.
- [ ] deptrac fails if a Domain class references a vendor class or a Handler class references a
      Domain or Infrastructure class. Check once manually; do not commit the violation.
- [ ] E2E tests pass with networking to OSM impossible (any non-app request fails the test).
- [ ] `make ci-targets` prints `["lint-ci","php-qa","php-tests"]` in steps 1–2 and
      `["lint-ci","php-qa","php-tests","frontend-checks","e2e"]` from step 3.
- [ ] Performance, checked once manually in a browser and recorded in the implementation log:
  - [ ] on localhost the first tile request starts within 2 s of navigation (E2E waits at most
        5 s);
  - [ ] frontend code adds no listeners to `move`/`render` events;
  - [ ] `app.js` is minified (Vite production build).
- [ ] README (Prerequisites, Running, Testing and QA, Continuous integration with the ruleset
      steps, Decisions, With more time) and CLAUDE.md (Commands, Glossary) are updated in the
      commits listed above.

## Risks and assumptions

- **PHP 8.6 is a release candidate.**
  - PHP-CS-Fixer needs `setUnsupportedPhpVersionAllowed(true)` and may mis-handle new 8.6
    syntax; do not use syntax newer than 8.5 in this task.
  - Vendor deprecations may appear (see the PHPUnit config fallback).
  - When the GA image ships, the Dockerfile switches (separate task).
- **The Playwright image (~2 GB)** is pulled by `docker compose up` and by the
  `frontend-checks` and `e2e` CI jobs. Its version and `@playwright/test` must stay equal.
  Otherwise Playwright cannot find its browsers.
- **Headless WebGL** relies on Chromium's software rendering (no GPU on CI runners). Verified
  locally in Docker without a GPU. It is slower, so E2E timeouts are 5 s for tile waits, not
  less.
- **CI cannot be run by the agents** (they never push). The workflow is verified only by
  actionlint and by `make check`. The first real run happens when the developer pushes.
  Possible surprises:
  - Compose 2.38.2 on the runner versus 5.8 locally.
  - File ownership with the runner's uid (handled by exporting `UID`/`GID`).
- **Concurrent cache saves.** Several matrix jobs saving the same `actions/cache` key at once
  log a harmless "unable to reserve cache" warning.
- **The required check is not enforced** while the repository is private on a Free plan
  (accepted by the developer).
- **OSM may block traffic that breaks its policy.** Development use is normal browsing. Tests
  never contact OSM.
- **TypeScript 6.0.3 is not the latest major.** Upgrade to 7 once typescript-eslint supports it.
- **Assumption:** the developer's host user can run Docker. `make` is optional (plain commands
  are documented).
- **Assumption:** `frontend/index.html` is read from the bind-mounted project directory at
  request time. There is no deployment image in this task.

## Implementation log

Filled in by the implement agent, one entry per step.
