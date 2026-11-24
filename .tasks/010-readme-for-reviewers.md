# 010: README for reviewers

- **Status:** done
- **Created:** 2026-11-24
- **Branch:** task/010-readme-for-reviewers
- **Request:** "Lets plan 010. The instructions for reviewer"

  Context: the project is about to be handed to the interviewer (the "reviewer" here is the person who evaluates the
  submission, not the review agent). The developer asked whether the README makes it clear, at first sight, how to run
  the application. It does not: the run steps exist but sit below a stale status block, `docker compose up` stays in
  the foreground so `make data` needs a second terminal (not said), the first-run cost and "reload after make data"
  are buried, and "Running" is full of feature details.

  Answers to the plan's questions, verbatim: "Q1: sure. Lets start with make data. Q2: make as main path. But directly
  under it a section withou make. Q3: I've put the screenshot to @docs/screenshot.png . Q4: leave it out. It is not
  relevant. Q5: keep the line. Q6: sure". The updated draft was then "approved".

  They mean:
  - Q1: the quick start runs `make data` first, then `make up`, in one terminal; `make data` gains the
    `install-php` prerequisite because a fresh clone has no `vendor/`.
  - Q2: `make` is the main path; a `### Without make` section follows directly under the quick start.
  - Q3: the developer's `docs/screenshot.png` is committed unchanged and shown under the intro.
  - Q4: the `### Surprises` placeholder section is deleted, with nothing in its place.
  - Q5: one line under Testing and QA names the known flaky E2E test.
  - Q6: the implement agent stops the developer's dev stack, runs the quick start on fresh clones (downloading the
    ČÚZK data again) and puts the measured times into the README.

## Goal

Someone who opens the repository sees on the first screen what the project is (with a screenshot), how to start it,
what it costs (time, download, disk) and where to look next. The README becomes accurate again: the stale status
block and stale table rows go, and the feature details move to a "Using the map" section.

This is a documentation change. The only executable change is one Makefile prerequisite; it is verified by running
the quick start on a fresh clone (red before, green after), not by an automated test.

## Scope

**In scope:**

- `README.md`: new top (intro, screenshot, Quick start, Without make), new "Where to look" and "Using the map"
  sections, "Running" renamed to "How it runs", trims and factual fixes listed in the steps.
- `docs/screenshot.png`: the developer's file, committed unchanged.
- `Makefile`: `data: build install-php`.
- `CLAUDE.md` Commands section: the `make data` line (CLAUDE.md requires it when a prerequisite changes).
- Verification of the quick start and of the without-make commands on fresh clones, with measured times.

**Out of scope:**

- The flaky E2E test `tests/E2E/parcel-detail.spec.ts` "ignores the parcel of the URL" (backlog); 010 only mentions it.
- Any application behaviour, any test code.
- The "Continuous integration" section and its ruleset instructions (unchanged).
- A `git clone` line in the quick start (the reader already has the checkout).
- Recompressing or regenerating the screenshot.
- Translating the README; `tests/Fixtures/README.md`.

## Decisions

1. **`make data` first, then `make up`, in one terminal** (Q1). The map shows the parcels the first time it opens:
   no second terminal, no reload. `make data` gets the prerequisite `install-php` (`composer install`), because
   `bin/console` requires `vendor/autoload.php` and a fresh clone has no `vendor/` (git-ignored; today only `make up`
   installs it).
2. **`make` is the main path, `### Without make` directly under the quick start** (Q2). The Makefile exports the host
   UID/GID; plain `docker compose` runs the containers as uid 1000 (`${UID:-1000}` in `compose.yaml`), which cannot
   write `vendor/` and `data/` on a Linux host whose user is not uid 1000. The without-make section says so in one
   line, with the bash fix `export UID GID=$(id -g)`.
3. **The screenshot is committed unchanged in step 1** (Q3) and shown under the intro, above the quick start. It shows
   the Czech interface, so a caption says so.
4. **The Surprises section is deleted** (Q4); the Notes intro line no longer mentions surprises.
5. **The flaky E2E test is named in one line under Testing and QA** (Q5), so a reviewer who runs `make check` and hits
   it knows it is known.
6. **Times are measured, not guessed** (Q6), on fresh clones in the implement agent's scratchpad, with the
   developer's dev stack stopped (port 8000). Every number gets the date it was measured. The cached Playwright image
   is not deleted; its first pull is given as its size, not a time.
7. **Branch prefix `task/`**: a documentation change is not a refactor of code. Commit type `docs(readme)`.
8. **No fact is dropped, only moved**, except the status block (stale, duplicated elsewhere), the measurement detail of
   old line 92 and the Surprises placeholder.
9. **Section order**: intro and screenshot, Quick start, Without make, Where to look, Using the map, How it runs (with
   the existing Data, Parcel tiles, Parcel details, Search, Frontend subsections), Testing and QA, Continuous
   integration, Architecture, Development workflow, Notes. "Where to look" comes before "Using the map" because it is
   short and the reviewer needs it at first sight; "Using the map" is long.

## Verified facts

Verified in the repository on 2026-11-24 (local `main` at a33fd4f, which includes task 009).

- `.gitignore` ignores `/vendor/`, `/data/`, `/node_modules/`, `/public/build/`, `/var/`; a fresh clone has none of them.
- `bin/console` starts with `require dirname(__DIR__) . '/vendor/autoload.php';`, so `make data` on a fresh clone fails
  today: the `data` target only depends on `build` (`docker compose build app`).
- `make up` is `docker compose up` (foreground). The `node` service (`npm ci && npm run build`) runs first; then `app`
  runs `composer install` and `php -S 0.0.0.0:8000 -t public`. `make down` stops the dev project and the test project.
- `compose.yaml` runs both services as `${UID:-1000}:${GID:-1000}`; the Makefile exports `UID`/`GID` from `id -u`/`id -g`.
  In bash, `export UID GID=$(id -g)` exports both (tested; `UID` is read-only but exportable).
- `docker compose run --rm --no-deps app …` does not start the `node` service.
- Images on the developer's machine: `parcel-map-app` 899 MB, `mcr.microsoft.com/playwright:v1.65.1-noble` 2.57 GB
  (on disk). `data/` after a full import: 528 MB. Docker 29.10.0, Docker Compose 5.8.0, GNU Make 4.4.1, Linux.
- The README's figures (240 cadastral areas, 111 municipalities, 272,011 parcels, 54 MB per monthly export, rerun from
  cache about 85 s) come from tasks 003 and 009.
- `docs/screenshot.png`: PNG, 1919x964, 935 KB, untracked; parcel 233 in Studeňany (Radim) selected, Czech interface,
  search box, detail panel, legend and flags visible.
- The "parcels missing" notice of the page reads (en) "Parcel data has not been imported yet. Run make data, then
  reload this page." (`frontend/languages/en.json`); with the new order a reviewer does not see it.

## Steps

1. **Quick start at first sight**: a reviewer can start the app from the top of the README on a fresh clone in one
   terminal, and knows what it costs. One commit:
   `docs(readme): put a quick start at the top of the README (#010)`.
   - Red: before any change, clone local `main` into the scratchpad (see Verification) and run `make data`; record in
     the implementation log that it fails because `vendor/autoload.php` is missing.
   - `Makefile`: `data: build install-php`.
   - `CLAUDE.md` Commands: the `make data` line becomes
     `make data           # PHP composer install, then PHP bin/console parcels:import: download the RÚIAN export (cached) and build data/tiles/parcels.mbtiles, the parcel store and the search index`.
   - `docs/screenshot.png` added unchanged.
   - README top rewritten (see Architecture > README). The status block (old lines 9–15) and the "Prerequisites"
     section (old lines 17–23) are replaced; the old "Running" code block (lines 27–31) is replaced by the quick start.
     The rest of old "Running" stays where it is in this step.
   - Green: commit, clone the branch fresh, run the quick start word for word and the without-make commands
     (see Verification), then amend the commit with the measured numbers.
2. **Using the map and where to look**: a reviewer finds the features and the project's documents without reading the
   operational details. One commit:
   `docs(readme): group the usage notes and point reviewers to the docs (#010)`.
   - New `## Where to look` and `## Using the map` (see Architecture > README).
   - `## Running` renamed `## How it runs`; its remaining intro becomes the compose and endpoint paragraph (old lines
     57–61), its subsections stay.
   - The rest of the restructuring, trims and fixes listed under Architecture > README.

## Architecture

No Domain, Application, Infrastructure, Handler, Command or Frontend code changes.

### README

Writing rules: English; quote user-facing texts in English (the interface is Czech by default, the README says so
once); short sentences; keep every fact that is not explicitly dropped below. Numbers marked `<measured>` come from
Verification, rounded up (minutes to the whole minute, sizes as measured), with "(measured on YYYY-MM-DD)".

**Top of the file (step 1)**, in this order:

1. `# Jičín Parcel Map`.
2. Intro, at most 4 lines: a web map of all 272,011 land parcels of the Jičín district (Czech Republic), built from
   ČÚZK open data; click a parcel for its details, search places, addresses and parcel numbers; stays smooth with the
   whole district in view. Then: "Built as an interview task for Viagem a.s."
3. The screenshot:
   `![The map with parcel 233 in Studeňany selected: detail panel, search box, land type legend and language flags](docs/screenshot.png)`
   and under it one italic caption line: the interface in Czech, its default; the flags switch it to English.
4. `## Quick start`
   - Requirements in one or two lines: Docker 24+ with Docker Compose v2 or later (Docker Desktop: at least 4 GB of
     memory), GNU make, internet access (ČÚZK data, OpenStreetMap tiles, images), about 5 GB of disk. Nothing else on
     the host: PHP 8.6, Composer, Node and the geo tools run in containers.
   - The command block, exactly these two commands with short trailing comments:
     ```
     make data    # once, about <measured> min: builds the image, downloads the ČÚZK data (54 MB), builds the map data
     make up      # serves http://localhost:8000; the first run also builds the frontend (about <measured> min)
     ```
     The first run of `make up` also pulls the Node/Playwright image (2.6 GB on disk); say it on the comment line or
     in the sentence after the block.
   - One sentence: open http://localhost:8000 once the log shows `<the ready line, verbatim from the run>`. Ctrl+C
     stops the app; `make down` removes the containers.
   - One sentence: `make data` again later takes a newer monthly export (reruns read the download cache).
   - One sentence: run every test and check with `make check` (about `<measured>` min; it uses its own app with
     fixture data on no published port, so it needs no import and does not touch the app of `make up`).
5. `### Without make`, directly under the quick start:
   ```
   docker compose build
   docker compose run --rm --no-deps app sh -c "composer install --no-interaction && bin/console parcels:import"
   docker compose up
   ```
   then `docker compose down` to remove the containers, and the one-line caveat: on Linux, if your user is not uid
   1000, run `export UID GID=$(id -g)` (bash) first, or use make, because the containers run as uid 1000 otherwise and
   cannot write `vendor/` and `data/`. The `make` targets of "Testing and QA" list their plain commands.
6. Layout limit: the first command block starts at or before line 25 and the next `##` heading after the quick start
   (`## Where to look` after step 2; the old `## Running` text after step 1) is at or before line 45.

**Step 2 changes:**

- `## Where to look`, at most 7 bullets, each one line with a link:
  - `CLAUDE.md`: the binding rules for every agent (architecture, TDD, git, glossary).
  - `.tasks/`: one task file per feature, 001 to 010: the request and the developer's answers verbatim, decisions,
    steps, test scenarios, implementation log and review rounds.
  - Architecture (anchor link): hexagonal layers in `src/`, enforced by deptrac.
  - Smoothness evidence (anchor link): the main quality criterion and how it is checked.
  - Notes > Decisions and With more time (anchor links).
  - Development workflow (anchor link): plan, implement and review agents; one commit per step.
- `## Using the map`:
  - First "Things to try", at most 6 bullets: zoom out to the whole district (zoom 10); from zoom 14 click a parcel;
    search `Valdštejnovo náměstí 58`, `867/121 rob` or `st. 72`; switch the language with the flags above the legend;
    open a `#parcel=<id>` link (copy the URL with a parcel selected).
  - Then the details, as short paragraphs or bullets, moved from old lines 33–38 (language), 39–40 and 43–56
    (notice before data, panel, ownership link, URL hash, zoom hint, legend) and 151–157 (the search box in the
    browser). The sentence "The messages quoted below are the English texts of the Czech-by-default interface." is
    replaced by: the interface is in Czech; the flags switch it to English; texts are quoted here in English.
  - The note about data imported before task 006 (old lines 41–43, "Parcel details have not been imported yet…")
    moves to `### Data` as a note on old imports.
- `## Running` becomes `## How it runs`; its first paragraph is old lines 57–61 (compose services, routes, `404`),
  without the image size (now in the quick start). `### Data`, `### Parcel tiles`, `### Parcel details`, `### Search`
  (API part only, old lines 134–150) and `### Frontend` stay under it.
- `### Data`: old line 91–92 becomes: the first `make data` downloads 111 municipality files (54 MB per monthly export)
  and the import itself takes about `<measured import time without image build>`; a rerun from the cache about 85
  seconds. The parenthesis "measured on 2026-11-24 from the cache: 57 s before the places, 85 s after, so +28 s" is
  dropped.
- `## Testing and QA`: one line after the table or the test-project paragraph: known issue, the E2E test
  `tests/E2E/parcel-detail.spec.ts` "ignores the parcel of the URL" sometimes times out waiting for the map canvas;
  rerun `make test-e2e` (or `make check`).
- `## Architecture` table: `Handler` row "Inbound HTTP adapters (map configuration, parcel tiles, parcel details,
  search)."; `Command` row "Inbound CLI adapters (parcel import, coverage list generation)."
- `## Notes`: the intro line becomes "A running log of decisions and what I would do with more time."; the
  `### Surprises` heading and its placeholder are deleted.

### Commands (CLI)

- `Makefile`: `data: build install-php` (no other target changes). `install-php` is
  `docker compose run --rm --no-deps app composer install --no-interaction --no-progress`.

### Wiring

None.

## Test scenarios

No automated tests: no production code or test code changes. The Makefile change and the README commands are verified
by hand on fresh clones; the implement agent records each run in the implementation log.

### Verification (by hand, implement agent)

1. Run `make down` in the main checkout (stops the developer's dev stack `vilagem-task-app-1` and the test project;
   port 8000 must be free). Leave it stopped afterwards and say so in the report.
2. **Red** (before step 1's changes): `git clone /home/drb/Development/vilagem-task <scratchpad>/fresh-main`, then
   `make data` there. Expected: fails on the missing `vendor/autoload.php`. Delete the clone.
3. **Cold image build time**: `docker compose build --no-cache app` in the main checkout; record the time. This
   replaces the shared `parcel-map-app` image with an equivalent build. Do not remove the Playwright image.
4. **Green, make path** (after committing step 1): `git clone --branch task/010-readme-for-reviewers
   /home/drb/Development/vilagem-task <scratchpad>/parcel-map-fresh`, then in it, word for word from the README:
   `time make data` (empty `data/`, so the ČÚZK download is included), then `time`-measure `make up` until the ready
   line (copy the line verbatim). Open http://localhost:8000 (curl `GET /api/map-config` shows `parcels` not `null`;
   `GET /api/search/{version}?q=Jičín` answers places). Then `make down`.
   The README's first-run time of `make data` is the cold build time of 3 plus this `make data` run (its image build
   is cached after 3). The import time alone (for `### Data`) is the import part of this run, from the first download
   line to the end.
5. **Without-make path**: a second fresh clone of the branch; copy `data/cache/` from the clone of 4 (so the ČÚZK
   files are not downloaded a third time); run the three `### Without make` commands word for word; check
   `GET /api/map-config` as in 4; `docker compose down`. Delete both clones afterwards (about 1 GB of data).
6. **`make check` time**: in the main checkout on the branch, with the dev stack stopped, `time make check`; it must be
   green.
7. Amend step 1's commit with the numbers; run `make check` again on the amended commit.

### Unit / Integration / API / Frontend

None.

## Acceptance criteria

- [ ] On a fresh clone of the branch, `make data` then `make up`, typed exactly as in the README, serve the map with
      the parcels at http://localhost:8000 on first open, without a second terminal or a reload.
- [ ] On a fresh clone, the `### Without make` commands, typed exactly as in the README, do the same.
- [ ] On a fresh clone of `main` (before the change), `make data` fails on the missing `vendor/autoload.php`
      (recorded in the implementation log).
- [ ] README.md: the screenshot is shown under the intro; the first command block starts at or before line 25; the
      next `##` heading after the quick start is at or before line 45 (after step 2 that heading is `## Where to look`).
- [ ] The quick start states the requirements, the first-run times of `make data` and `make up`, the image size, the
      ready log line, how to stop, and `make check` with its time; every time has a measurement date.
- [ ] README.md no longer contains: the `**Status:**` block, "This README is updated as features land", "The messages
      quoted below are the English texts", "later tiles and parcel detail", "57 s before the places", `### Surprises`,
      "_To be filled in._".
- [ ] Every other fact of the old README is still present (moved, not lost): endpoints, status codes, cache headers,
      file paths, data figures, budgets, decisions, with-more-time items.
- [ ] Testing and QA names the flaky E2E test and how to rerun.
- [ ] `Makefile` has `data: build install-php`; the CLAUDE.md Commands line for `make data` matches.
- [ ] `docs/screenshot.png` is committed byte-identical to the developer's file (same size, 935 KB).
- [ ] Two commits after the task-file commit, each with `make check` green
      (`git rebase --exec "make check" main` passes).

## Risks and assumptions

- Measured times depend on the developer's machine and connection; the README says where and when they were measured.
- `docker compose build --no-cache` gives the build time with base images cached (`php:8.6-rc-cli`, `composer:2`); a
  reviewer without them downloads those too (about 0.8 GB). The quick start's "about 5 GB of disk" covers it.
- ČÚZK must be reachable and the monthly export published (on the 1st of a month, for about an hour, a municipality may
  be missing). If the download fails during verification, rerun later; do not change the import.
- Stopping the dev stack interrupts the developer's running app (accepted in Q6).
- The flaky E2E test may fail a `make check` run; rerun, and record it in the implementation log if it happens.
- The PHP 8.6 RC image may move to GA before the reviewer runs it; out of scope here.

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: quick start at first sight

- Red (2026-11-24): a fresh clone of `main` (65dee8b), `make data`: the image builds, then
  `Failed opening required '/app/vendor/autoload.php'` in `bin/console` line 9, exit 255.
- Done: `Makefile` `data: build install-php`; CLAUDE.md Commands line; `docs/screenshot.png` (unchanged, 934,840 bytes);
  README top (intro, screenshot, quick start, without make); old status block and Prerequisites replaced.
- Green, make path (fresh clone of the branch, dev stack stopped, empty `data/`): `make data` 2 min 21 s (composer
  install, ČÚZK download of 111 files, import; image build cached); cold `docker compose build --no-cache app` 1 min
  26 s before it. First-run total about 3 min 50 s, rounded to 4 minutes in the README. `make up` until the server
  lines appeared: 23 s (Playwright image cached on this machine, so no pull time; the README gives its 2.6 GB size).
  Ready line, verbatim: `Development Server (http://0.0.0.0:8000) started` (preceded by `app-1   | [20] [... 2026] PHP 8.6.0RC7`).
  `GET /api/map-config` had `parcels` set; `GET /api/search/{version}?q=Jicin` answered places (Jičín first).
- Green, without make (second fresh clone, `data/cache/` copied): the three commands word for word worked; map-config
  had `parcels`. Both clones and their containers removed.
- `make check` on the branch with the dev stack stopped: green, 1 min 21 s (110 E2E tests passed; the flaky E2E test
  did not fail), rounded to 2 minutes.
- The import part of `make data` (from the first download line to the end) is about 2 minutes (2 min 21 s total
  including composer install); the README's `### Data` figures are adjusted in step 2.
- Dev stack: stopped with `make down` before the measurements and left stopped.

### Step 2: using the map and where to look

- Added `## Where to look` (6 bullets) and `## Using the map` (things to try, then the language, notice, panel, URL hash,
  zoom hint, legend and search box paragraphs moved from Running and Search). `## Running` is now `## How it runs`
  with the compose and endpoint paragraph, without the image size.
- `### Data`: the note on imports from before task 006 moved here; the timings now read: import about 2 minutes
  (measured on 2026-11-24), rerun from the cache about 85 seconds; the "57 s before the places" parenthesis is gone.
- Testing and QA names the flaky E2E test; the Architecture table rows for Handler and Command are current; Notes lost
  the Surprises placeholder.
- Deviation: Decisions and With more time share one "Where to look" bullet, as the task file lists them together.
  No fact was dropped except those the task file names.

## Review

Filled in during the review loop (implement agent), one entry per round with findings.
