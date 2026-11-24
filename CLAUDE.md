# CLAUDE.md

Rules for every Claude session and agent working in this repository. They are binding.
If a rule blocks you, stop and ask the developer; do not work around it.

## Project

A web application that shows land parcels of the Jičín district (Czech Republic) on a map.
Clicking a parcel shows its details. The application must stay smooth when the whole district
is visible. This is the main quality criterion; treat it as a hard requirement in every
design decision.

- Backend: PHP 8.6, no framework, Composer for autoloading and libraries.
- Frontend: MapLibre GL JS (open choice, decided per task).
- Data: ČÚZK open data (RÚIAN / INSPIRE), downloaded and preprocessed ahead of time,
  not fetched live at request time.
- Everything runs in Docker. Do not rely on tools installed on the host.
- Language: English everywhere (code, comments, tests, commits, docs, task files).
  Czech terms appear only where they name source data (see Glossary).
  Exception: user-facing texts are data. `frontend/languages/<language>.json` holds them in its language,
  `frontend/index.html` carries the default language's title and fallback error, and tests and task files may
  quote these texts verbatim. Everything else stays English.

## Architecture: hexagonal + DDD

```
src/
  Domain/          Entities, value objects, domain services, domain exceptions,
                   repository interfaces (outbound ports owned by the domain).
  Application/     Use cases (one class per use case, one public method),
                   input/output DTOs, application exceptions,
                   outbound port interfaces that are not repositories.
  Infrastructure/  Outbound adapters implementing the ports: SQLite/MBTiles readers,
                   ČÚZK downloaders, external process runners (GDAL, tippecanoe), clock, etc.
  Handler/         Inbound HTTP adapters. One handler per endpoint.
                   HTTP plumbing (request, response, router) lives in Handler/Http/.
  Command/         Inbound CLI adapters. One command per CLI action, run via bin/console.
                   CLI plumbing lives in Command/Console/.
  Bootstrap/       Composition root: wires adapters to ports by hand. No other logic.
public/index.php   HTTP front controller (calls Bootstrap, dispatches to Handler).
bin/console        CLI entry point (calls Bootstrap, dispatches to Command).
frontend/          Frontend source and its unit tests.
tests/             Unit/, Integration/, Api/, E2E/ (see Testing).
config/            Committed configuration (e.g. coverage.csv, the cadastral areas to import).
data/              Downloaded and generated data. Git-ignored. Test fixtures live in tests/Fixtures/.
.tasks/            Task files (see Workflow).
```

### Dependency rule

| Layer          | May depend on                                    |
|----------------|--------------------------------------------------|
| Domain         | PHP standard library only                        |
| Application    | Domain                                           |
| Infrastructure | Domain, Application, third-party libraries       |
| Handler        | Application (use cases, DTOs, exceptions)        |
| Command        | Application (use cases, DTOs, exceptions)        |
| Bootstrap      | Everything (wiring only)                         |

Handlers and commands never touch Domain objects or Infrastructure directly. They translate
input into a use case input DTO, call the use case, and translate the output DTO or exception
into an HTTP response / CLI output and exit code. They contain no business logic.

The rule is enforced by a static check (deptrac or equivalent, set up in the first task).
A violation fails the build.

### DDD rules

- Model the domain explicitly: `Parcel`, `CadastralArea`, `ParcelNumber`, `LandType`, … are
  types, not arrays or strings. Validate invariants in constructors / named constructors;
  an invalid object must not be constructible.
- Value objects are `final readonly` and compared by value (`equals()`).
- Entities have identity and protect their own invariants. No public setters.
- No primitive obsession across layer boundaries: DTOs carry primitives *into* and *out of*
  use cases; inside Application and Domain, use domain types.
- Use the Glossary names. If a new domain concept appears, add it to the Glossary in the same
  change.

### Code rules

- `declare(strict_types=1);` in every PHP file. PSR-4 autoloading, namespace root `ParcelMap\`.
- Classes are `final` unless designed for extension. Prefer `readonly` properties.
- Constructor injection only. No service locator, no static mutable state, no globals.
- Full type declarations on all parameters, returns, and properties. No `mixed` unless
  unavoidable and justified in a comment.
- Small classes, small methods, intention-revealing names. No comments that restate the code;
  comment the *why*.
- Errors: throw specific exceptions; never return `null`/`false` to signal failure.
- Coding style PER-CS (PHP-CS-Fixer), static analysis PHPStan at max level. Both must pass.
- Runtime Composer dependencies are allowed when the task file justifies them. Prefer the
  standard library for small things.

Naming:

- Use cases are named `<Verb><Subject>UseCase` and have one public method `execute()`.
- Other Application classes are named after their role: ports (`…Converter`, `…Builder`,
  `…Publisher`, `…Downloader`, `Clock`), DTOs (`…Input`, `…Output`), exceptions (what went wrong).
- Infrastructure classes that load, save or update domain data are named
  `<Subject>RepositoryUsing<Service>` and implement the Domain repository interface
  `<Subject>Repository`.
- Other Infrastructure adapters are named `<Port>Using<Service>`.
- `<Service>` is the technology (Csv, Mbtiles, Http, Ogr2ogr, Tippecanoe, Constants, SystemTime),
  not the data provider.
- Test fakes follow the same rule with `Memory` (`MapSettingsRepositoryUsingMemory`); a fixed
  clock is `ClockUsingFixedTime`.
- Infrastructure helpers that implement no port keep a role name (`ProcessRunner`).
- Handlers and commands are named `<Action>Handler` / `<Action>Command`.

## Testing: TDD, always

**Tests come first. Every code change starts with a failing test.** No production code is
written without a test that fails for the right reason first. This applies to new features,
bug fixes (first a test that reproduces the bug), and frontend code.

Cycle: **red** (write a test, run it, watch it fail for the expected reason) → **green**
(the smallest change that makes it pass) → **refactor** (clean up with all tests green).

Work outside-in (double loop): for each step of a task, first write the outer acceptance
test (API or E2E) that describes the step, then drive the inner layers with unit and
integration tests until the outer test passes.

| Level       | Location                | What it tests                                              | Rules                                                                                  |
|-------------|-------------------------|------------------------------------------------------------|----------------------------------------------------------------------------------------|
| Unit        | `tests/Unit/`           | Domain and Application (use cases with in-memory fakes of ports) | No I/O, no filesystem, no network, no Docker services. Fast.                     |
| Integration | `tests/Integration/`    | Infrastructure adapters against real resources             | Real SQLite / MBTiles fixture files, real external binaries. No network: recorded ČÚZK responses as fixtures. |
| API         | `tests/Api/`            | HTTP endpoints and CLI commands, end to end on the backend | HTTP: real HTTP requests to the running app over the network. CLI: run `bin/console` as a subprocess and assert output and exit code. Uses a small fixture dataset. |
| Frontend    | `frontend/` (unit), `tests/E2E/` | JS logic (unit) and the app in a real browser over HTTP | Unit: Vitest for pure JS modules. E2E: Playwright against the running app with the fixture dataset. |

- Test names describe behaviour: `it_rejects_a_parcel_number_with_a_zero_base_number`.
- Unit tests mirror the `src/` structure.
- Fakes over mocks: write in-memory implementations of ports for unit tests.
- Never delete, skip, or weaken a test to make the suite pass. If a test is wrong, say so in
  the task log and fix it deliberately.
- Fixture data must be small, committed, and documented (where it came from).

## Workflow

### Vertical slices and small steps

Every change is a vertical slice: it delivers one piece of user-visible (or operator-visible)
behaviour end to end, through every layer it needs (Domain → Application → Infrastructure →
Handler/Command → frontend). Never build a whole layer first. Each slice is a small MVP that
works on its own; the next slice extends it.

### Task files (`.tasks/`)

Every feature request and every bug fix gets a task file **before any code is written**:
`.tasks/NNN-kebab-case-title.md` (NNN = next free number, zero-padded), based on
`.tasks/TEMPLATE.md`. The task file is the single source of truth for that change.

Status lifecycle (in the file header): `approved` → `in-progress` → `done` (or `blocked`).

### Agents

- **plan** (`.claude/agents/plan.md`, strong model): analyses the request, the code and these
  rules, iterates with the developer, and writes the task file once the developer approves.
  It defines the architecture of the change; it writes no production code or tests.
- **implement** (`.claude/agents/implement.md`, cheaper model): takes an approved task file
  and turns it into tested code, following these rules.
- **review** (`.claude/agents/review.md`, strong model, read-only): reviews the commits of a task
  branch against the architecture and language rules in this file and reports numbered
  findings. It changes nothing; its Bash use is limited to read-only git commands by a hook.

Main session procedure for a new feature or bug:

1. Spawn **plan** with the developer's request.
2. Relay plan's draft and open questions to the developer **verbatim** (do not summarize away
   questions). Send the developer's answers back to the *same* plan agent (SendMessage).
   Repeat until the developer approves.
3. Plan creates the task branch and commits the task file on it.
4. Spawn **implement** with the task file path. It works on the task branch.
5. Report implement's result to the developer: what was done, commits, deviations, open
   issues. If implement reports a problem with the plan, go back to step 1/2 to fix the task
   file (amending the task-file commit on the branch); do not patch around it.
6. **Review loop.** After implement reports a finished task:
   - Spawn a **fresh** review agent with base `main`, the task branch checked out, and the round
     number (1, 2, ...).
   - Relay its report to the developer **verbatim**.
   - The developer answers by number: confirm or reject, optionally with a reason or an adjusted
     fix.
   - Confirmed findings marked `Task-file contract: yes` go to **plan** first, which amends the
     task-file commit.
   - Then send to **implement**, verbatim: the confirmed findings with the developer's notes, the
     rejected findings with their reasons, the round number and the reviewed head SHA.
   - Implement folds the fixes into their commits, records the round in the task file's
     `## Review` section, and verifies every commit.
   - Repeat with a new review agent.
   - The loop stops when a round reports `No findings.`, when the developer confirms none
     (implement then only records that round), or when the developer says stop.
   - If a finding comes back with `Repeats:`, point this out to the developer instead of looping
     silently.
7. Review feedback from the developer on an existing branch goes back to implement (or plan,
   if the architecture changes), which folds it into the existing commits (see Git). After such
   a change, run the review loop again before reporting back, unless the developer says to skip
   it.

The main session does not write production code itself; code changes go through a task file
and the implement agent.

## Git

### Branches

- Every task has its own branch, named after the task file: `refactor/NNN-kebab-case-title` for a
  refactor task (it changes how the code is written, not what the application does: code-rule
  fixes, review follow-ups, restructuring), `task/NNN-kebab-case-title` for every other task.
  Both prefixes share the NNN sequence. The plan agent picks the prefix, writes the branch into
  the task file header, and creates the branch from local `main`; the task file is its first
  commit.
- **Never commit to `main`.** This is enforced by a hook in `.claude/settings.json` that denies
  commit, merge, cherry-pick, revert, am and rebase while `main` is checked out. Switch to the
  task branch in a separate command before committing; the hook checks the branch that is
  current *before* the command runs. The review agent is additionally limited to read-only git
  commands by `.claude/hooks/review-read-only.py`, a hook in its frontmatter.
- Claude commits locally only. Claude **never pushes** (denied in `.claude/settings.json`),
  never opens pull requests, never pulls or fetches, and never merges into `main`. The
  developer reviews, pushes, pulls and merges. If a branch needs changes from `main`, ask the
  developer.

### Commits

- **One logical step per commit**: a single coherent change that one subject line can
  describe. A step in the task file ends at a commit boundary but may span several commits.
- **Every commit is self-contained**: at that commit the app builds and the full test suite
  and QA checks pass, without relying on any later commit. A commit includes the code, its
  tests, and the docs / task-file updates that belong to that change. No "WIP", "fix tests"
  or "address review" commits.
- Conventional Commits: `feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `chore:`, `build:`.
  Mention the task number, e.g. `feat(parcel): show parcel detail on click (#003)`.
- Never use `--no-verify`. Never commit secrets or generated data from `data/`.

### Modifying an existing branch

- When changing work that is already committed on the branch (review feedback, a bug in an
  earlier step, a corrected plan), fold the change into the commit it logically belongs to
  instead of adding a new commit:
  - target is the last commit: `git commit --amend`;
  - target is an older commit: `git commit --fixup=<sha>`, then
    `git rebase --autosquash main` (non-interactive).
- Add a new commit only when the change is a genuinely new logical step.
- After rewriting, verify that every commit still works on its own:
  `git rebase --exec "<full test and QA command>" main`.
- History is rewritten only on task branches, never on `main`. If the developer has already
  pushed the branch, say so in the report: they will need to force-push
  (`--force-with-lease`).

## Commands

All commands run inside Docker. `make` (GNU make, a host tool) wraps `docker compose`; the plain
form is shown where useful. `PHP` below means `docker compose run --rm --no-deps app`, `NODE` means
`docker compose run --rm --no-deps node` (the Playwright image; Node, Vite and Chromium).

```bash
make check          # full test + QA (what CI runs, one after another); use for git rebase --exec
make qa             # cs phpstan deptrac lint-ci typecheck lint-frontend
make test           # test-hooks test-unit test-integration test-api test-frontend test-e2e (run make assets first)
make test-unit      # PHP vendor/bin/phpunit --testsuite unit
make test-integration  # PHP vendor/bin/phpunit --testsuite integration (real GDAL/tippecanoe/SQLite, local HTTP server)
make test-api       # starts the test app (project parcel-map-test, fixture data), then PHP vendor/bin/phpunit --testsuite api in it
make cs             # PHP vendor/bin/php-cs-fixer check --diff   (make cs-fix fixes)
make phpstan        # PHP vendor/bin/phpstan analyse --no-progress
make deptrac        # PHP vendor/bin/deptrac analyse --no-progress --fail-on-uncovered --report-uncovered
make typecheck      # NODE npm run typecheck (tsc -b)
make lint-frontend  # NODE npm run lint (ESLint) and npm run format:check (Prettier)
make format         # cs-fix + NODE npm run format
make test-frontend  # NODE npm test (Vitest)
make test-e2e       # starts the test app, then NODE npm run e2e in the test project (Playwright; needs make assets first)
make up-app         # starts the test app: docker compose -p parcel-map-test -f compose.yaml -f compose.test.yaml up -d --wait --no-deps app
make data           # PHP bin/console parcels:import: download the RÚIAN export (cached) and build data/tiles/parcels.mbtiles, the parcel store and the search index
make perf           # NODE npm run perf; Playwright measurement of the running app (make up) with real data (make data); not part of check
make coverage       # PHP bin/console coverage:generate --district=3604; regenerates config/coverage.csv (review the diff, commit it)
make assets         # NODE npm ci && npm run build -> public/build/   (make watch rebuilds on change)
make lint-ci        # actionlint on .github/workflows/ci.yml
make test-hooks     # Python unittest of the Claude Code hooks in .claude/hooks/ (python:3.14.10-slim image)
make up / make down # docker compose up / down (app at http://localhost:8000); down also stops the test project
docker compose build                 # build the image
docker compose run --rm app <cmd>    # run any command in the app container
docker compose run --rm app bin/console   # list the CLI commands
```

`make check` runs the groups in `CHECK_TARGETS` (Makefile); CI runs the same groups as parallel
jobs. Add a group there and CI follows.

When a task adds targets or prerequisites, update this section in the same commit.

## Glossary

Source-data term (Czech) → name used in code.

| Czech (source data)                   | Code name            | Notes                                                          |
|---------------------------------------|----------------------|----------------------------------------------------------------|
| parcela                               | `Parcel`             |                                                                |
| katastrální území (KÚ)                | `CadastralArea`      | Identified by a 6-digit code.                                  |
| obec                                  | `Municipality`       | Code (`MunicipalityCode`) and name; names come from `config/coverage.csv`. |
| okres                                 | `District`           | Jičín district, code 3604.                                     |
| kód okresu                            | `DistrictCode`       | 4 digits; Jičín is 3604.                                       |
| parcelní číslo                        | `ParcelNumber`       | Kind + base number (kmenové číslo) + optional subdivision (poddělení), e.g. `st. 80/2`, `867/121`. |
| stavební / pozemková parcela          | `ParcelKind::Building` / `ParcelKind::Land` | Building plots are displayed with the `st.` prefix. `DruhCislovaniKod` 1 = building, 2 = land. |
| druh pozemku                          | `LandType`           | RÚIAN `DruhPozemkuKod`: arable land, forest, permanent grassland, … A positive code outside the published code list is kept and shown as unknown. |
| způsob využití pozemku                | `LandUse`            | RÚIAN `ZpusobyVyuzitiPozemku`; optional. Unknown positive codes are kept and shown as unknown. |
| výměra                                | `Area`               | Square metres.                                                 |
| list vlastnictví (LV)                 | `OwnershipSheet`     | Neither the LV number nor ownership is in the open data (RÚIAN, SHP, INSPIRE, open VFK); the panel links to the cadastre viewer instead. |
| Nahlížení do katastru nemovitostí     | Cadastre viewer (`Parcel::cadastreViewerUrl()`) | ČÚZK's public web viewer; shows owners and the LV number after login or a CAPTCHA. |
| zjednodušená evidence (PK / EN)       | `SimplifiedRecordParcel` | Not in RÚIAN or any open source; no own geometry.             |
| katastr nemovitostí (KN)              | Cadastre             |                                                                |
| RÚIAN, VFR                            | —                    | ČÚZK register and its exchange format (data source).           |
| stavový soubor ČR VFR (`ST_UZSZ`)     | RÚIAN state structure file (`RuianStructureDownloader`, `DownloadedStructure`) | State-wide municipalities and cadastral areas; the source of the coverage list. |
| S-JTSK (EPSG:5514)                    | —                    | Source coordinate system; the map uses WGS84 / Web Mercator.   |
| —                                     | `TileSizeBudget`     | Maximum gzip size of one vector tile (200,000 bytes); keeps the map smooth. |
| —                                     | `TilesetStatistics`  | Tile count and largest tile per zoom level of a built tileset. |
| —                                     | `Coordinates`        | WGS84 longitude/latitude in degrees. Longitude first, as in MapLibre and GeoJSON. |
| —                                     | `ZoomLevel`          | Web map zoom, 0–24 (MapLibre zoom, fractional allowed).        |
| —                                     | `MapView`            | Camera position the map opens with: center + zoom.             |
| —                                     | `BaseMap`            | Background raster tile layer under the data (OSM in task 001). |
| —                                     | `TileUrlTemplate`    | `https` URL with `{z}`, `{x}`, `{y}` placeholders.             |
| —                                     | `Attribution`        | Required data credit shown on the map: text + link.            |
| kód obce                              | `MunicipalityCode`   | 6 digits.                                                      |
| kód katastrálního území               | `CadastralAreaCode`  | 6 digits.                                                      |
| měsíční stavový soubor obce VFR (`OB_UKSH`) | RÚIAN export (`RuianExportDownloader`, `DownloadedExport`) | Monthly per-municipality file with parcels and original boundaries. |
| datum souboru VFR                     | `ExportDate`         | Last day of a month; the file is published the next day.       |
| —                                     | `Coverage`           | The cadastral areas the map covers (`config/coverage.csv`).    |
| —                                     | `TilesetVersion`     | Identifies one published import (tileset and parcel store); part of the tile and parcel URLs. |
| —                                     | `ParcelId`           | RÚIAN parcel `Id`; positive, at most 2^53 − 1. Feature id in the tiles, key of the parcel store. |
| hranice parcely (`OriginalniHranice`) | `ParcelBoundary`     | Polygon rings in WGS84, outer ring first.                      |
| —                                     | Parcel store (`ParcelStoreBuilder`, `BuiltParcelStore`, `ParcelRepository`) | SQLite file of parcel details per `TilesetVersion`: `data/parcels/{version}.sqlite`. |
| —                                     | Parcel data (`ParcelDataPublisher`) | The tileset, parcel store and search index of one import, published together under one `TilesetVersion`. |
| —                                     | `TileCoordinate`     | Tile `zoom/x/y` in the XYZ scheme (y from the top).            |
| —                                     | `VectorTile`         | One gzip-compressed vector tile, or none (empty).              |
| —                                     | `SearchIndex`        | The search index of one tileset version (`data/search/{version}.sqlite`): parcel numbers and places of the covered area. |
| —                                     | `SearchText`         | Text normalised for search: lower case, no diacritics, only `a-z`, `0-9`, `/` and single spaces. |
| —                                     | `SearchQuery`        | A parsed search text: parcel number, cadastral area code, name terms. |
| —                                     | `ParcelNumberQuery`  | A parcel number as searched: base, optional subdivision, optional kind (`st.`). |
| —                                     | `ParcelMatch`        | A parcel found by its number: id, number, cadastral area. |
| —                                     | `Bounds`             | South-west and north-east `Coordinates` of a rectangle.        |
| —                                     | `ParcelTileset`      | The published parcel tileset: version, export date, bounds, zoom range; `attribution()` gives the ČÚZK credit. |
| —                                     | Selected parcel      | The parcel shown in the side panel and outlined on the map (source `selected-parcel`); in the URL as `#parcel={id}`. |
| —                                     | Parcel detail        | What the side panel shows about a parcel (`GET /api/parcels/{version}/{id}`). |
| —                                     | Map configuration    | What the frontend needs to start the map: initial `MapView` + `BaseMap` (`GET /api/map-config`). |
| —                                     | Language             | A language of the interface, identified by its ISO 639-1 code (`cs`, the default, and `en`); its texts are `frontend/languages/<code>.json` and its flag `<code>.svg`. |
| —                                     | Texts                | The interface strings of one language, keyed by text keys such as `panel.heading`; fetched at run time, never bundled into the JS. |
| —                                     | Language switcher    | The flag buttons above the legend (`LanguageSwitcherControl`); switching re-renders the texts in place, without reloading. |
| —                                     | Legend               | The land type key in the bottom-left corner of the map (`LegendControl`), built from the frontend land type catalogue. |
