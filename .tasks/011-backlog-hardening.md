# 011: Backlog hardening: stable E2E runs, stronger parcel tests, programs that cannot start

- **Status:** in-progress
- **Created:** 2026-11-25
- **Branch:** `refactor/011-backlog-hardening`
- **Request:** "merged. Lets do all the backlog stuff", then "lets plan 011, 012 and 013 as one refactor/fix task".
  This task merges three backlog drafts: the flaky E2E test (former draft 011), the hardening of the 006 parcel
  detail tests with a review round 2 of the 006 code (former draft 012), and reporting programs that cannot start as
  port failures (former draft 013, the follow-up left by task 005). Answers during planning, verbatim: "all open
  question as recommended" (Q1–Q11 below), on the 006 review round 2: "the review is correct. Lets fix this", and
  on the final draft: "approved".

  The confirmed finding of review round 2 of task 006 (base 4ac6152, head 531cf2e), verbatim:

  > 1. src/Domain/Cadastre/ParcelBoundary.php:11
  >    Commit: 834d71a feat(import): publish a parcel store with each tileset (#006)
  >    Rule (DDD rules): "Value objects are `final readonly` and compared by value (`equals()`)."
  >    Problem: ParcelBoundary is a `final readonly` value object (the parcel polygon, made with `fromRings()`) but it has no `equals()`. The other value objects this commit adds (`ParcelId`, `ParcelNumber`, `LandType`, `LandUse`, `Area`) all have one.
  >    Fix: Add `equals(self $other): bool` that compares the rings position by position with `Coordinates::equals()`, ring count and ring lengths included, and a unit test in tests/Unit/Domain/Cadastre/ParcelBoundaryTest.php.
  >    Task-file contract: no
  >    On main (3e3729c): still applies. `git show main:src/Domain/Cadastre/ParcelBoundary.php` is the same as at 531cf2e and still has no `equals()`.

## Goal

The E2E suite stops failing at random: its Playwright client no longer waits on the DNS servers of the host network,
and the E2E tests no longer share `node_modules` and the frontend build with the dev stack. Every 006 behaviour named
in the backlog gets a test that fails when the behaviour is broken, and the one confirmed finding of the 006 review
round 2 is fixed. A program that cannot be started (ogr2ogr, tippecanoe) is reported through the port's own failure
exception, so the operator sees the normal one-line command failure instead of "Unexpected error" and a stack trace.

## Scope

**In scope:**

- `dns_search: .` for the `node` service (`compose.yaml`) and a guard test for it (step 1); README fixes that follow
  from the root cause.
- Own `node_modules` and frontend build for the test project under `var/e2e/` (step 2); Makefile, CLAUDE.md Commands
  section, README.
- Stronger tests for: cadastral area vs municipality names, the 15-digit id limit, the frontend status check, the
  fit on a changed hash (steps 3–6); a mutation check of `adopt` (step 8).
- `ParcelBoundary::equals()` (step 7).
- Recording review round 2 in the 006 task file (step 8).
- `ProcessNotStarted` translated into `StructureReadFailed`, `ConversionFailed` and `TileBuildFailed` by the four
  process adapters (steps 9–10).

**Out of scope:**

- Expect timeouts, Playwright worker count, retries, and any existing assertion (none is weakened or loosened).
- The CPU load of the E2E suite (load average about 3× the core count); it is not the cause of the flake.
- The `selectedId` half of `adopt` (clicking the linked parcel again does not reload it) (Decision 7).
- `ProcessRunner` and `ProcessNotStarted` themselves; the dev stack's `node` service command.
- The task 005 file (not edited; precedent: 005's Decision 8).

## Decisions

1. **DNS fix in the environment** (Q1): `dns_search: .` on the `node` service in `compose.yaml`, so it applies to both
   compose projects (the test project and `make perf` against the dev app). Rejected: raising the 5 s expect
   timeout (stalls reach 10 s and the timeout would only hide them), fewer workers, retries, an `http://parcel-map.:8000`
   base URL (changes the origin every test sees), IPv6 on the Docker network or extra `/etc/hosts` entries (heavier).
   The `app` service is not changed: PHP resolves both address families in one lookup, which is answered at once.
2. **Guard test for the DNS fix** (Q2): `tests/E2E/environment.spec.ts`, a timing check of the lookups Playwright's
   request context makes. It is the failing test of step 1. It tests the test environment, not the app.
3. **Isolated E2E dependencies** (Q3 = A): the test project mounts `var/e2e/node_modules` over `/app/node_modules`
   and `var/e2e/build` over `/app/public/build`; `make test-e2e` builds its own frontend there. Reason: the dev
   stack's `node` service (`npm ci && npm run build`), `make assets` and `make watch` delete and rewrite the shared
   `node_modules/` and `public/build/` and broke a running E2E test with 404s; `npm ci` can also kill a running
   Playwright.
4. **Verification of step 2 by a scripted reproduction** (Q4): no automated test can race the suite against its own
   working tree, so step 2 (build configuration only, no production code) is verified by running `make test-e2e`
   while `make assets` runs in a loop: red before the change, green after; both runs recorded in the log. This is a
   deliberate, developer-approved deviation from "every code change starts with a failing test".
5. **Review round 2 of 006** (Q5): run by the main session before approval (base 4ac6152, head 531cf2e). One finding,
   confirmed; it is small, so it is fixed here (step 7) and not in a follow-up task.
6. **Where round 2 is recorded** (Q6): in the `## Review` section of `.tasks/006-parcel-detail-panel.md`, plus a
   one-line pointer to task 011 under its "Ineffective tests found" list; the finding is also quoted in this file's
   Request header.
7. **`adopt` is checked by mutation only** (Q7): the existing E2E test "opens a link to a parcel fitted to it,
   without loading the default view first" already waits for more than 100 highlight pixels. No new test. The
   `selectedId` half of `adopt` stays untested (a click at a computed position right after a fit is fragile).
8. **Outer tests of step 9–10** (Q8): one CLI API test per command, covering the first program the command runs
   (ogr2ogr in both). The place converter and tippecanoe are covered by integration tests only (as 005's Decision 7):
   with every program hidden, `parcels:import` only ever reaches the parcel converter's ogr2ogr.
9. **Granularity of the process part** (Q9): two commits, one per CLI command.
10. **Branch and commit types** (Q10): `refactor/011-backlog-hardening`. CLAUDE.md knows only `task/` and `refactor/`;
    all three parts are review follow-ups, code-rule fixes or test/environment hardening, and the map behaves as
    before. Each commit has the type that fits it (`test`, `build`, `refactor`, `docs`, `fix`); the two process
    commits are `fix(...)` because the CLI output changes.
11. **Task numbers 012 and 013 stay unused** (Q11): the other parallel drafts keep 014 and 015; this file's Request
    header records the merge.
12. **Red step of test-only steps (3–6, 8)**: these steps change no production code, so "red first" is a mutation:
    break the production code in the working tree (never committed), run the old test (it stays green, which shows
    the gap, where the plan says so), run the new or strengthened test (it fails for the expected reason), restore the
    code, see it pass. Every mutation and its result goes into the implementation log.
13. **Process adapters translate the exception themselves** (not `ProcessRunner`, a generic helper; the HTTP
    downloaders do the same with `RemoteFileUnavailable` → `SourceDownloadFailed::because()`). The message of
    `ProcessNotStarted` is passed through unchanged; `because(string $reason)` takes no previous exception, so the
    stack trace printed today disappears.
14. **A program is made unstartable in tests by hiding `PATH`**; no production code changes for that. Restoring
    `PATH` in `tearDown` is mandatory.

## Verified facts

- **Root cause of the flake, reproduced on 2026-11-24** (test project `parcel-map-test` only):
  - Full suite `--repeat-each=10` (8 workers): 1 of 1,100 failed, the same test ("ignores the parcel of the URL") with
    the same error, plus `route.fetch: Test ended ... GET http://parcel-map:8000/api/map-config`.
  - Its trace: the route handler's `route.fetch()` of `/api/map-config` started 121 ms into the test and was still
    pending 5 s later; the page's own requests (HTML, CSS, JS, `cs-*.json`) were answered (texts in 8 ms). The PHP
    server log has no connection for the pending request. The canvas is created only after the map configuration
    arrives, so the expect timed out.
  - The page snapshot of a failure is taken when the context closes, after the trace is saved
    (`willCloseBrowserContext` → `_takePageSnapshot` in `node_modules/playwright/lib/index.js`), so controls in it do
    not mean the map was up within the 5 s.
  - Playwright 1.65.1's request context (`dualStackLookup` in `node_modules/playwright-core/lib/coreBundle.js`)
    looks the host up for IPv6 and IPv4 separately and waits for both (`Promise.allSettled`). `parcel-map` has no
    IPv6 address; the node container's `/etc/resolv.conf` carries the host's `search westcom.local home` (Docker
    copies it), so the failing IPv6 lookup walks the search domains, which Docker's embedded DNS forwards to the
    host's upstream DNS servers.
  - Measured in the node container: IPv6 lookup of `parcel-map` p50 108 ms, 38 of 300 at 2 s or more, 12 of 300 at 5 s
    or more, max 10.2 s (ENOTFOUND); IPv4 max 1.4 ms; IPv6 lookup of `parcel-map.` (absolute) max 1 ms.
    `GET /api/map-config` with the request context p50 211 ms, max 5.7 s; Chromium `fetch` p50 2.9 ms; Node `fetch`
    p50 3.1 ms.
  - With `dns_search: .` on `node` (temporary override): `resolv.conf` has no `search` line; IPv6 lookup max 4 ms over
    300; request context p50 3.4 ms, max 14 ms; full suite `--repeat-each=10`: 1,100 passed.
  - Ruled out: WebGL context creation (MapLibre 6.19 creates the canvas before `getContext`; a failure throws, and
    `main.ts` then removes the map container and shows the error, so no controls would exist), CPU load (the request
    never reached the server), idle preconnections blocking PHP's built-in server (10 idle sockets: a request still
    took 3 ms).
  - Any test that routes `/api/map-config` through `route.fetch()` can hit it (about ten call sites); which one fails
    is random.
  - The 009 statement "Playwright's request context and Chromium add about 40 ms" (README Smoothness evidence item 3,
    comment in `tests/Perf/search.spec.ts`) is the same IPv6 lookup; Chromium needs about 3 ms.
- `dns_search: .` removes the search domains: documented for `--dns-search=.` in moby PR #8839
  (https://github.com/moby/moby/pull/8839); verified locally with Docker 29.10.0 and Docker Compose 5.8.0.
- `npm ci` with the warm cache (`var/cache/npm`) into a separate directory: 1.6 s, 162 MB.
- `docker compose config` merges the proposed `compose.test.yaml` volume entries after `.:/app` (checked with a
  temporary override). Missing bind-mount sources are created by Docker as root, hence the `mkdir -p` first.
- 006 code on `main` 3e3729c: `GetParcelDetailHandler::MAX_DIGITS = 15` with `strlen($id) > self::MAX_DIGITS`; the
  use case test fixtures use area 659541 "Jičín" with municipality 572659 "Jičín"; the frontend "the status is 500"
  case answers `{ error: 'internal_error' }`, and `parcelDetail.ts` has an `!response.ok` check;
  `ParcelSelection.onHashChange()` calls `select(id, { fit: true })`; `task/006-parcel-detail-panel` = 531cf2e,
  9 commits on 4ac6152 (= `d4cd6f3^1`); area 740225 "Robousy" appears in `tests/Api/ParcelDetailTest.php` and parcel
  99225194010 in the fixtures. `ParcelBoundary` has no `equals()`; `Coordinates::equals()` compares both floats with
  `===`.
- Process code on `main`: exactly four adapters use `ProcessRunner` (`DistrictAreaExtractorUsingOgr2ogr`,
  `ParcelConverterUsingOgr2ogr`, `PlaceConverterUsingOgr2ogr`, `TilesetBuilderUsingTippecanoe`); nothing outside
  `src/Infrastructure/Process/` references `ProcessNotStarted`. `ConsoleApplication::runCommand()` catches
  `Throwable` and prints `Unexpected error: <message>` and the whole exception. `ConversionFailed` and
  `TileBuildFailed` extend `ImportFailed`, `StructureReadFailed` extends `CoverageGenerationFailed`; all three have
  `because(string $reason)`. The commands print `Import failed: ` / `Coverage generation failed: ` + message.
  `tests/Api/ConsoleRunner::run(array $arguments, array $environment = [])` starts `PHP_BINARY` with the inherited
  environment plus `$environment`.
- PATH hiding, run in the test project's app container: after `putenv("PATH=/parcel-map-test-no-programs")`,
  `proc_open(["ogr2ogr", "--version"], …)` returns false with `proc_open(): posix_spawn() failed: No such file or
  directory` (before: it starts).

## Steps

Each step is one commit; `make check` passes at every commit. The DNS fix comes first so that the new E2E test of
step 6 does not inherit the flake.

1. **`test(e2e): look the app up without the host's DNS search domains (#011)`**: the E2E suite no longer waits on
   the host network's DNS.
2. **`build(e2e): give the E2E tests their own node_modules and frontend build (#011)`**: rebuilding the frontend or
   starting the dev stack no longer breaks a running E2E test.
3. **`test(parcel): tell the cadastral area from the municipality in the detail tests (#011)`**
4. **`test(parcel): pin the 15-digit limit of a parcel id in the URL (#011)`**
5. **`test(map): reject a failed parcel detail response whatever its body (#011)`**
6. **`test(map): fit the map to the parcel of a changed hash (#011)`**
7. **`refactor(parcel): compare parcel boundaries by value (#011)`**: fixes confirmed finding 1 of 006 review round 2.
8. **`docs(tasks): record review round 2 of task 006 (#011)`**: includes the `adopt` mutation check.
9. **`fix(coverage): report an ogr2ogr that cannot start as a coverage generation failure (#011)`**
10. **`fix(import): report a program that cannot start as an import failure (#011)`**

### Step 1 in detail

- Red: `tests/E2E/environment.spec.ts` (see Test scenarios). Run it before the compose change and record that it
  fails (and the slow lookup it reports).
- Green: in `compose.yaml`, service `node`: `dns_search: .`, with a comment giving the reason (Playwright's request
  context looks hosts up for IPv6 as well and waits for it; the single-label container name has no IPv6 address, and
  the host's search domains sent that lookup to the host's DNS servers, which took up to 10 s).
- README:
  - Remove the "Known issue: the E2E test `tests/E2E/parcel-detail.spec.ts` …" paragraph under Testing and QA.
  - Add a design-notes bullet after "E2E reaches the app as `parcel-map`, not `app`": the Node container has no DNS
    search domains, and why (one or two sentences, the facts above).
  - Smoothness evidence item 3: replace "because Playwright's request context and Chromium add about 40 ms to every
    response of PHP's built-in server (even a 404) in the Docker setup" with a reason that is true, e.g. that Node's
    fetch measures only the server and the network.
- `tests/Perf/search.spec.ts`: correct its header comment in the same way. The spec keeps using Node's `fetch`.
- Verification (log): the full suite with `--repeat-each=10` in the test project
  (`docker compose -p parcel-map-test -f compose.yaml -f compose.test.yaml run --rm --no-deps node npx playwright test --repeat-each=10`,
  test app up via `make up-app`), all green; record the count and the duration.

### Step 2 in detail

- `compose.test.yaml` (test project only):
  - `app.volumes`: add `./var/e2e/build:/app/public/build:ro`.
  - `node.volumes`: add `./var/e2e/node_modules:/app/node_modules` and `./var/e2e/build:/app/public/build`.
  - Extend the file's header comment: the test project also has its own `node_modules` and frontend build, so the
    dev stack, `make assets` and `make watch` cannot change them during a run.
- `Makefile`:
  - `E2E_DIR := var/e2e` (or literal paths; local naming is free).
  - `e2e-dirs:` `mkdir -p var/e2e/node_modules var/e2e/build` (a comment: Docker would create missing bind-mount
    sources owned by root).
  - `e2e-assets: e2e-dirs` → `$(TEST_COMPOSE) run --rm --no-deps node sh -c 'npm ci --no-audit --no-fund && npm run build'`.
  - `up-app: e2e-dirs` (recipe unchanged).
  - `test-e2e: e2e-assets up-app` (recipe unchanged).
  - `e2e: install-php test-e2e` (no longer `assets`).
  - Add `e2e-dirs` and `e2e-assets` to `.PHONY`. `CHECK_TARGETS` and the CI workflow stay unchanged.
- CLAUDE.md Commands section (required when targets change): `make test` loses "(run make assets first)" (its
  `test-e2e` now builds); `make test-e2e` becomes "builds the frontend into var/e2e/ (make e2e-assets), starts the test
  app, then NODE npm run e2e in the test project"; add `make e2e-assets` (npm ci and build in the test project into
  `var/e2e/`). `make assets` stays (dev app).
- README: the same changes to the targets table (`make test-e2e` no longer "needs make assets first"; add
  `make e2e-assets`), and the paragraph on the test project mentions its own `node_modules` and build.
- Verification (log, Decision 4): with the test app up, run `make test-e2e` in one shell while another shell runs
  `make assets` in a loop until `make test-e2e` ends. Before the change: record the failures (404s, missing modules).
  After the change: the run is green. Run this only when no other agent uses the working tree, because it rewrites
  the shared `public/build/` and `node_modules/`.

### Steps 3–6 in detail

3. Use case and handler tests learn a parcel whose cadastral area and municipality have different names:
   area 740225 "Robousy", municipality 572659 "Jičín" (the values of fixture parcel 99225194010, `867/121`, land
   parcel). Mutations: in `GetParcelDetailUseCase` (the output mapping), the area name ↔ the municipality name, both
   directions; the same swap in the handler's JSON (`cadastralArea.name` / `municipality.name`). Each must fail the
   new test; record that the old tests stay green.
4. `GetParcelDetailHandlerTest`: the in-memory store also holds parcels with ids `1234567890123456` (the existing
   "sixteen digits" bad id) and `123456789012345`. New test: a 15-digit id is answered with 200. Mutations: remove
   the length check → the "sixteen digits" case answers 200 (red); `MAX_DIGITS = 14` → the new test answers 404
   (red). The API case "id with 16 digits" stays as it is (the fixture store cannot hold such an id); the unit test
   pins the limit.
5. `frontend/tests/parcelDetail.test.ts`, "the status is 500": answer `validBody` with status 500 instead of
   `{ error: 'internal_error' }`, so only the status check can reject it (a deliberate change of an existing test;
   it gets stronger, note it in the log). Mutation: remove the `if (!response.ok)` block in `parcelDetail.ts` → red.
6. New E2E test in `tests/E2E/parcel-detail.spec.ts`, group "the selected parcel in the URL" (uses its `requested`
   list), see Test scenarios. Mutations: `{ fit: true }` → `{ fit: false }` in `ParcelSelection.onHashChange()`; an
   empty `fitTo()` body. Each must fail the new test; record that "selects the parcel of a changed hash" stays green.

### Step 7 in detail

- `src/Domain/Cadastre/ParcelBoundary.php`: add `public function equals(self $other): bool`. True only when both have
  the same number of rings, each ring has the same number of positions as its counterpart, and every position equals
  its counterpart by `Coordinates::equals()`, in order.
- No production caller is added (the method exists for the rule, like the other value objects). No Application,
  Infrastructure, Handler or wiring change; no Glossary change (`ParcelBoundary` is in it).
- Red: the new cases in `tests/Unit/Domain/Cadastre/ParcelBoundaryTest.php` fail because the method is undefined.

### Step 8 in detail

- `.tasks/006-parcel-detail-panel.md`:
  - `## Review`: add "Round 2" after round 1: base 4ac6152, head 531cf2e, run on 2026-11-25 by the main session for
    task 011; 1 finding (quote its location, rule and fix briefly), confirmed by the developer ("the review is
    correct. Lets fix this"), fixed on branch `refactor/011-backlog-hardening` by
    `refactor(parcel): compare parcel boundaries by value (#011)`; no rejected or superseded findings.
  - Under "Ineffective tests found": one line pointing to task 011, which strengthens the listed tests.
- The `adopt` mutation check (Decision 7), recorded in this file's implementation log and committed here:
  - Mutation A: remove the `map.once('load', …)` block that calls `selection.adopt(linked.detail)` in `main.ts`. The
    test "opens a link to a parcel fitted to it, without loading the default view first" must fail (highlight
    pixels stay at 0). If it stays green, stop and report to the coordinator: the highlight then comes from
    somewhere else and the plan is wrong.
  - Mutation B: call `selection.adopt(linked.detail)` at once instead of on `load`. Only record the result.
  - Restore the code; no production change is committed.

### Steps 9–10 in detail

9. `DistrictAreaExtractorUsingOgr2ogr`: wrap the `$this->runner->run(...)` call, catch `ProcessNotStarted`, throw
   `StructureReadFailed::because($exception->getMessage())`. Add the test helper `tests/Support/ProgramSearchPath`
   (see Infrastructure). Outer test first: the CLI API test of `coverage:generate`.
10. `ParcelConverterUsingOgr2ogr` and `PlaceConverterUsingOgr2ogr` → `ConversionFailed::because($exception->getMessage())`;
    `TilesetBuilderUsingTippecanoe` → `TileBuildFailed::because($exception->getMessage())`. Outer test first: the CLI
    API test of `parcels:import`. No cleanup is needed on this path: the parcel converter has written no output yet,
    the place converter deletes its earlier output before it calls the runner, tippecanoe writes nothing.

## Architecture

| Area | Change |
|---|---|
| Domain | `ParcelBoundary::equals(self $other): bool` (step 7). No new types, exceptions or Glossary entries. |
| Application, Handler, Bootstrap, wiring, Glossary | None |
| Infrastructure | The four process adapters each get one `try`/`catch (ProcessNotStarted $exception)` (steps 9–10). `ProcessRunner` and `ProcessNotStarted` unchanged. |
| Commands | No code change. Output becomes one line `Import failed: …` / `Coverage generation failed: …`, exit code 1. |
| Frontend production code | None |
| Test-only files | New `tests/E2E/environment.spec.ts`, `tests/Support/ProgramSearchPath`; existing tests extended (steps 3–7). |
| Config | `compose.yaml` (step 1); `compose.test.yaml`, `Makefile`, CLAUDE.md Commands section, README (step 2) |

### Domain

- `ParcelBoundary` (value object, `final readonly`, unchanged invariants): new `equals(self $other): bool` as in
  step 7.

### Application

No change. The ports already declare the exceptions the adapters now throw: `DistrictAreaExtractor` →
`StructureReadFailed`, `ParcelConverter` and `PlaceConverter` → `ConversionFailed`, `TilesetBuilder` →
`TileBuildFailed`.

### Infrastructure

- `DistrictAreaExtractorUsingOgr2ogr`, `ParcelConverterUsingOgr2ogr`, `PlaceConverterUsingOgr2ogr`,
  `TilesetBuilderUsingTippecanoe`: catch `ProcessNotStarted` around the runner call and rethrow the port's exception
  with the same message (Decision 13). Their `@throws` tags stay as the ports declare them.
- Test helper `tests/Support/ProgramSearchPath` (namespace as the other `tests/Support` classes):
  `hideAllPrograms(): self` saves the current `PATH` and sets it with `putenv()` to a directory that does not exist
  (e.g. `/parcel-map-test-no-programs`); `restore(): void` puts the saved value back. Integration tests call
  `restore()` in `tearDown()`. API tests do not use it; they pass `['PATH' => '/parcel-map-test-no-programs']` to
  `ConsoleRunner::run()`.

### Handlers (HTTP)

No change.

### Commands (CLI)

No code change. With ogr2ogr missing from `PATH`:

```
$ bin/console coverage:generate --district=3604 ...
Coverage generation failed: Cannot start "ogr2ogr": proc_open(): posix_spawn() failed: No such file or directory
(exit code 1)

$ bin/console parcels:import ...
Import failed: Cannot start "ogr2ogr": proc_open(): posix_spawn() failed: No such file or directory
(exit code 1)
```

No `Unexpected error` line and no stack trace.

### Frontend

No production change.

### Wiring

No change.

### Environment and build

- `compose.yaml`, service `node`: `dns_search: .` (step 1).
- `compose.test.yaml`, `Makefile`: as in step 2. `var/` is git-ignored, ESLint, Prettier and the Docker build context
  already ignore it.

## Test scenarios

### Unit

- `tests/Unit/Domain/Cadastre/ParcelBoundaryTest.php` (step 7):
  - it equals a boundary built separately from the same rings (outer ring plus one hole, new `Coordinates`
    instances, not shared ones);
  - it differs from a boundary in which one position inside a ring differs (not the closing position);
  - it differs from a boundary with an extra hole (ring count);
  - it differs from a boundary whose ring has more positions but starts with the same ones (ring length);
  - it differs from a boundary with the same rings in another order (the hole first).
- `GetParcelDetailUseCaseTest` (step 3): it maps the cadastral area and the municipality of a parcel separately (area
  740225 "Robousy", municipality 572659 "Jičín": both codes and both names asserted).
- `GetParcelDetailHandlerTest` (step 3): it answers the cadastral area and the municipality as separate members
  (`cadastralArea` = `{code: 740225, name: "Robousy"}`, `municipality` = `{code: 572659, name: "Jičín"}`).
- `GetParcelDetailHandlerTest` (step 4): it answers the details of a parcel with a fifteen-digit id (200); the store
  also holds `1234567890123456`, so the "sixteen digits" bad-id case can only pass through the length limit.

### Integration

All with the real `ProcessRunner` and `PATH` hidden by `ProgramSearchPath`, restored in `tearDown()`; each red first
because the raw `ProcessNotStarted` reaches the test:

- `DistrictAreaExtractorUsingOgr2ogrTest::it_reports_that_ogr2ogr_cannot_be_started` (step 9): `StructureReadFailed`,
  message contains `Cannot start "ogr2ogr"`.
- `ParcelConverterUsingOgr2ogrTest::it_reports_that_ogr2ogr_cannot_be_started_and_leaves_no_file` (step 10):
  `ConversionFailed`; its output directory (`municipalities/`) is empty.
- `PlaceConverterUsingOgr2ogrTest::it_reports_that_ogr2ogr_cannot_be_started_and_leaves_no_file` (step 10): the same
  for `places/`.
- `TilesetBuilderUsingTippecanoeTest::it_reports_that_tippecanoe_cannot_be_started` (step 10): `TileBuildFailed`,
  message contains `Cannot start "tippecanoe"`.

### API (HTTP and CLI)

- `GenerateCoverageCommandTest::it_fails_when_ogr2ogr_cannot_be_started_and_writes_nothing` (step 9): run with
  `PATH` hidden; exit code 1; stderr contains `Coverage generation failed: Cannot start "ogr2ogr":` and not
  `Unexpected error`; no coverage file written. Red today (`Unexpected error`).
- `ImportParcelsCommandTest::it_fails_when_ogr2ogr_cannot_be_started_and_publishes_nothing` (step 10): run with `PATH`
  hidden (downloads come from the test's local HTTP server as in the existing cases); exit code 1; stderr contains
  `Import failed: Cannot start "ogr2ogr":` and not `Unexpected error`; no `tiles/parcels.mbtiles` published. Red
  today.

### Frontend (unit and E2E)

- `tests/E2E/environment.spec.ts` (step 1), plain `test` from `@playwright/test` (no page, so not the `offlineTiles`
  fixture): "looks up the app host at once, for IPv6 as well as IPv4, as Playwright's request context does". The
  hostname comes from `baseURL`. 30 lookups each with `dns.promises.lookup(host, { all: true, family: 6 })` and
  `family: 4`; a rejection (ENOTFOUND for IPv6) counts as an answer; the test fails at the first lookup that takes
  500 ms or more, naming the family and the time. Red before the compose change on the developer's machine (about
  99.7 % probability with 30 IPv6 lookups); after it, lookups take a few milliseconds.
- `frontend/tests/parcelDetail.test.ts` (step 5): "the status is 500" rejects a 500 response with a valid detail body.
- `tests/E2E/parcel-detail.spec.ts` (step 6), group "the selected parcel in the URL": "fits the map to the parcel of
  a changed hash": open the default view with `openAt(page, null, null, null)` and wait for the response of
  `/tiles/parcels/{FIXTURE_VERSION}/12/2222/1381.pbf` (set up the wait before opening); assert that no zoom-16 parcel
  tile (`/tiles/parcels/{FIXTURE_VERSION}/16/`) has been requested; set `window.location.hash` to
  `#parcel=1753295604` (ST_72); wait for the response of `/tiles/parcels/{FIXTURE_VERSION}/16/35562/22101.pbf`.
  ST_72 is used because the fit to it (455 m², `maxZoom` 18) is known to request that tile (the existing link test).
- Step 8: mutations A and B of `adopt` against the existing link test (no new test).

## Acceptance criteria

- [x] `tests/E2E/environment.spec.ts` fails before the `compose.yaml` change and passes after it (both logged; red with simulated search domains).
- [x] The full E2E suite with `--repeat-each=10` passes in the test project after step 1 (logged with count and
      duration).
- [x] The README no longer names a flaky E2E test, and no longer claims that Playwright's request context and
      Chromium add about 40 ms; `tests/Perf/search.spec.ts` says the same.
- [x] `make test-e2e` builds into `var/e2e/` and does not read `node_modules/` or `public/build/` of the working tree;
      the scripted reproduction of step 2 is red before and green after (logged).
- [x] CLAUDE.md Commands section and the README targets table match the Makefile.
- [ ] Every mutation of steps 3–6 fails its new or strengthened test, and each is logged with its result.
- [ ] `adopt` mutation A fails the existing link test (or implement stopped and reported); mutation B is logged.
- [ ] `ParcelBoundary` has `equals()`, covered by the five unit scenarios of step 7.
- [ ] `.tasks/006-parcel-detail-panel.md` records review round 2 with the confirmed finding and its fixing commit.
- [ ] `git grep ProcessNotStarted -- src` outside `src/Infrastructure/Process/` lists only the four adapters (import
      and catch).
- [ ] With ogr2ogr missing, both commands print the one-line failure shown above, exit code 1, no stack trace.
- [ ] Steps 3–6 and 8 change no production file. The only production changes are `compose.yaml`,
      `compose.test.yaml` and `Makefile` (steps 1–2), `ParcelBoundary` (step 7) and the four process adapters
      (steps 9–10).
- [ ] Exit codes, HTTP status codes and the map's behaviour are unchanged; no existing test is weakened.
- [ ] `make check` passes at every commit (`git rebase --exec "make check" main`).

## Risks and assumptions

- **The guard test depends on the network for its red run.** It is red on the developer's machine (search domains
  with slow upstream DNS). On a network without search domains it would pass before the fix too; that is correct,
  there is nothing to fix there.
- **Timing margin of the guard test:** about 4 ms after the fix against a 500 ms limit; DNS lookups under full suite
  load stayed under 11 ms (18,000 lookups measured).
- **Step 2's reproduction touches the shared working tree** (`public/build/`, `node_modules/`); run it only when no
  other agent runs checks. After step 2, `make assets` is needed only for the dev app.
- **Stale test app container:** the first `make up-app` after step 2 recreates the test app because its mounts
  change; expected.
- **`PATH` not restored** would break later tests in the same PHPUnit process; the `tearDown()` restore is mandatory.
- **`adopt` mutation A could stay green**: then the plan's assumption is wrong; implement stops and reports instead
  of adding a test.
- **Size:** ten commits plus the review loop, with the submission deadline on 2026-11-26.
- **Assumption:** Docker Compose keeps honouring `dns_search: .` (documented Docker behaviour since PR #8839).

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: `test(e2e): look the app up without the host's DNS search domains (#011)`

- Red: `tests/E2E/environment.spec.ts` passes on the current network, because the host's `/etc/resolv.conf` has no
  `search` line today (the developer is on a different network than on 2026-11-24; the node container's resolv.conf
  had only `nameserver 127.0.0.11` and `options ndots:0`). The plan's risk "on a network without search domains it
  would pass before the fix too" applies. Red was therefore shown by simulating the host's search domains with a
  temporary compose override (`dns_search: [westcom.local, home]` on `node`, kept outside the repo): the test failed
  with `IPv6 lookup of parcel-map took 8019 ms`, 5 of 5 runs failed with `--repeat-each=5`.
- Green: `dns_search: .` on the `node` service in `compose.yaml` (with the reason as a comment); on the real network
  the test passes in about 30 ms (5 of 5). README: removed the "Known issue" paragraph, added the design-notes bullet,
  rewrote Smoothness evidence item 3; `tests/Perf/search.spec.ts` header comment corrected.
- Verification: full suite in the test project, `--repeat-each=10`: 1110 passed (111 tests x 10), 3.9 minutes, 0 failed.
  (This network has no search domains, so this run could not show the flake itself; the 2026-11-24 measurements in
  the plan did.)

### Step 2: `build(e2e): give the E2E tests their own node_modules and frontend build (#011)`

- Done as planned: `compose.test.yaml` mounts `var/e2e/node_modules` and `var/e2e/build` into the node service (build
  also read-only into the app service); Makefile `e2e-dirs`, `e2e-assets`; `up-app: e2e-dirs`;
  `test-e2e: e2e-assets up-app`; `e2e` no longer depends on `assets`. CLAUDE.md Commands and the README table and test
  project paragraph updated.
- Scripted reproduction (Decision 4), `make test-e2e` with `make assets` looping in a second process
  (`/tmp/.../scratchpad/repro.sh`):
  - Before the change (commit a459def): red at once, `sh: 1: playwright: not found` (`npm ci` of `make assets` had
    deleted `node_modules/`), `make: *** [Makefile:94: test-e2e] Error 127`; 1 asset rebuild ran.
  - After the change: green, 111 passed in 38.4 s while `make assets` ran to completion 10 times.
  - Plain `make test-e2e` after the change (first run, builds `var/e2e/` from scratch): 111 passed.
- `npm ci` works with a bind-mounted `/app/node_modules` (npm empties it instead of removing it).
- The reproduction ran `make assets` on the shared tree; it was left rebuilt.

## Review

Filled in during the review loop (implement agent), one entry per round with findings.
