# 005: Specific exceptions

- **Status:** in-progress
- **Created:** 2026-11-23
- **Branch:** `refactor/005-specific-exceptions`
- **Request:** a refactor task that fixes three confirmed findings from a whole-repository review of
  `main` (a580ecd). "All three findings below are confirmed, with the fixes as proposed (you may
  refine names if the rules call for it)." "The branch must be named `refactor/*` (not `task/*`)."
  Answers during planning, verbatim: "1. as recommended. 2. The 004 branch has already been merged.
  Do it in this one. 3. as recommended." The revised draft was then "approved".

  The confirmed findings (reviewer's text, abbreviated):

  1. `src/Infrastructure/Process/ProcessRunner.php:20` (commit 99fa8f2, #002), Code rules: "Errors:
     throw specific exceptions; never return `null`/`false` to signal failure." `run()` throws a
     generic `RuntimeException('Cannot start …')`. Fix: `Infrastructure\Process\ProcessNotStarted`
     with a named constructor, thrown from `run()` and declared in `@throws`.
  2. `src/Handler/GetParcelTileHandler.php:59` (commit 510d5ba, #002), same rule. A stored tile that
     cannot be gunzipped raises a generic `\RuntimeException`. Fix: a specific exception next to
     `MapPageUnavailable` in `Handler/`; the kernel still maps it to 500.
  3. `src/Application/Import/AreaParcelCount.php:17`, `src/Application/Import/ConvertedParcels.php:27,
     :33` (commit 05596c2, #003), same rule. The invariant checks throw a generic
     `InvalidArgumentException`. Fix: `Application\Import\InvalidConvertedParcels` with
     `::noParcels(int $area)`, `::noAreas()`, `::duplicateArea(int $area)`. Task-file contract: yes
     (task 003 prescribes `\InvalidArgumentException`).

## Goal

Every failure in `src/` that is thrown today as a generic `\RuntimeException` or
`\InvalidArgumentException` is thrown as a named exception with a named constructor, as CLAUDE.md
requires. HTTP status codes, CLI exit codes and the rest of the behaviour stay the same. The task
also introduces the `refactor/NNN-slug` branch convention that this branch uses, and closes the
record of task 004 (its verification was done but not written down before it was merged).

## Scope

**In scope:**

- Step 1: the branch convention `refactor/NNN-kebab-case-title` in CLAUDE.md, `.tasks/TEMPLATE.md`,
  `.claude/agents/plan.md`, `.claude/agents/implement.md`, `.claude/agents/review.md` and README.
- Steps 2–4: the three findings, each with a test first.
- Step 5: the verification record of task 004 in `.tasks/004-review-agent.md`.

**Out of scope:**

- Translating `ProcessNotStarted` into the port failures of its callers (`ParcelConverterUsingOgr2ogr`
  → `ConversionFailed`, `TilesetBuilderUsingTippecanoe` → `TileBuildFailed`,
  `DistrictAreaExtractorUsingOgr2ogr` → `StructureReadFailed`). Follow-up (Decision 3). Until then a
  missing binary still ends as "Unexpected error" with exit 1, as today.
- Moving the gzip decompression out of `GetParcelTileHandler`.
- `ConvertedParcels::parcelCountOf()` throwing `\OutOfBoundsException` (a contract violation by the
  adapter, not part of the confirmed findings).
- Generic exceptions in test helpers (`tests/Support/LocalHttpServer.php`, `tests/Api/ConsoleRunner.php`).
- Editing `.tasks/003-whole-jicin-district.md` (Decision 8) or task files 001–003 in any way.
- Backfilling a `Branch:` header line into task files 001–004.
- The illustrative names in `review.md` and `implement.md` (`task/005-parcel-detail-panel`,
  `(#005)` in the examples). The parcel detail panel is now task 006, but these are examples only.
- Tuning `review.md` because the task 003 dry run missed finding 3 (Decision 6).
- Any change to the Glossary, Bootstrap, deptrac, `.claude/settings.json`, the hooks, CI.

## Decisions

1. **Branch convention (Q1: "as recommended").** Task branches are named by the kind of task:
   - `refactor/NNN-kebab-case-title` for a refactor task: it changes how the code is written, not
     what the application does (code-rule fixes, review follow-ups, restructuring);
   - `task/NNN-kebab-case-title` for everything else (features, bug fixes, tooling, workflow).

   Both share the NNN sequence; the slug always equals the task file name. The plan agent picks the
   prefix and writes the branch into the task file header (`- **Branch:**`). A one-off was rejected
   because `implement.md` stops when there is no `task/NNN-…` branch and `review.md` would not find
   the task file (losing the `## Review` memory between rounds). The convention lands in step 1, so
   the review loop of this branch already runs with the updated `review.md`.
2. **This branch is `refactor/005-specific-exceptions`.** It was created by the plan agent before the
   convention exists in `plan.md` / `implement.md`, on the developer's explicit instruction. Until
   step 1 is committed, this header is the authority on the branch name; the main session names the
   branch in the implement prompt.
3. **No translation of `ProcessNotStarted` in the adapters (Q3: "as recommended").** The findings ask
   only for the specific type; the binaries are part of the image, so a missing one is a broken
   deployment; translating would change CLI messages that the port contracts define. Follow-up.
4. **PHP warnings on the two failure paths are suppressed and folded into the exception.** Both
   `proc_open` and `gzdecode` raise an `E_WARNING` before returning `false` (Verified facts).
   `phpunit.xml.dist` has `failOnWarning="true"`, so a new test would fail on the leaked warning, and
   the image has `display_errors=STDOUT`, so the warning text also leaks into CLI and HTTP output
   today. The adapters call the function with `@` (with a comment saying why) and throw the specific
   exception instead. The codebase already does this (`@mkdir`, `@unlink` in
   `ParcelConverterUsingOgr2ogr`).
5. **Names.**
   - Finding 1: `ProcessNotStarted::program(string $program, string $reason)`. The `$reason` argument
     is a refinement of the proposed `::program(string $program)`: it carries the PHP warning text
     (e.g. `posix_spawn() failed: No such file or directory`), which is the only information about
     why the program did not start.
   - Finding 2: `StoredTileCorrupt::cannotDecompress()` instead of the proposed `StoredTileNotGzip`.
     The stored data does pass the gzip magic-byte check of `VectorTile::fromGzippedData`; what fails
     is decompression. CLAUDE.md names exceptions after what went wrong.
   - Finding 3: `InvalidConvertedParcels` as proposed. One class for both value objects, because
     `AreaParcelCount` exists only as a part of `ConvertedParcels`. `int $area` follows the existing
     `InvalidCoverage::duplicateArea(int $code)`.
6. **Task 004 record (Q2: "Do it in this one").** Step 5 records the verification in
   `.tasks/004-review-agent.md`, sets it to `done`, ticks its acceptance criteria, and records review
   round 1 with the date 2026-11-21 (the date of the reviewed head e689c99). It also records,
   factually, that the task 003 dry run missed a violation that the later whole-repository review
   found (finding 3 of this task). `review.md` is not tuned for it.
7. **No outer API / E2E test.** Nothing changes at the HTTP or CLI boundary: a corrupt tile still
   gives 500, a missing binary still gives "Unexpected error" with exit 1, and the invariants of
   `ConvertedParcels` are not reachable from the CLI with the real adapter. The integration and unit
   tests of steps 2–4 are the acceptance tests of their steps. Steps 1 and 5 are documentation only
   and have no tests (as in task 004 step 2).
8. **Task 003 is superseded, not edited.** This task supersedes the 003 contract in "Architecture",
   *Step 2: one conversion per municipality*: the bullets on `AreaParcelCount` ("otherwise
   `\InvalidArgumentException`") and `ConvertedParcels` ("Invariants: non-empty, unique areas,
   otherwise `\InvalidArgumentException`"). They now throw `InvalidConvertedParcels`, a subclass of
   `\InvalidArgumentException`, so any code catching the parent keeps working. The merged 003 file
   stays a historical record.
9. **Commit types.** Steps 2 and 3 are `fix:` (a leaked PHP warning disappears and a specific type
   replaces the generic one); step 4 is `refactor:` (only the type changes); steps 1 and 5 are
   `docs:`.

## Verified facts

All verified on 2026-11-22 in the app image (`docker compose run --rm --no-deps app`, PHP 8.6.0RC7,
PHPUnit 13.4.8).

- `proc_open(['no-such-program-xyz'], …)` and `proc_open(['/nonexistent/program'], …)` return `false`
  and raise `E_WARNING` `proc_open(): posix_spawn() failed: No such file or directory`. A
  non-executable path (`/etc/passwd`, `/tmp`) returns `false` with `… failed: Permission denied`. So
  the "cannot start" branch of `ProcessRunner::run()` is reachable with a non-existent program name.
- With `@proc_open(...)`, `error_get_last()['message']` is
  `proc_open(): posix_spawn() failed: No such file or directory`.
- `gzdecode("\x1f\x8bnot gzip at all")` and `gzdecode("\x1f\x8b")` return `false` and raise
  `E_WARNING` `gzdecode(): data error`. Data with the gzip magic bytes but a broken body passes
  `VectorTile::fromGzippedData` and reaches this path.
- A scratch PHPUnit test run with `phpunit.xml.dist`: an unsuppressed `proc_open` warning makes
  PHPUnit exit 1 ("1 test triggered 1 PHP warning"); with `@` it exits 0.
- `php -i` in the image: `display_errors => STDOUT`, `log_errors => Off`.
- Code facts (main, a580ecd):
  - `git grep -nE "new \\\\?(RuntimeException|InvalidArgumentException)\(" -- src` lists exactly the
    four lines of the findings.
  - No caller of `ProcessRunner::run()` catches its exception; `ConsoleApplication` catches
    `Throwable` ("Unexpected error", exit 1); `HttpKernel` catches `Throwable` (500, reported to the
    `ErrorReporter`).
  - `ParcelConverterUsingOgr2ogr` builds `AreaParcelCount` / `ConvertedParcels` and catches only
    `ConversionFailed|CadastralAreaNotInExport` around them.
  - The branch-name assumptions: CLAUDE.md Git > Branches (first bullet), `plan.md` "Writing the task
    file" step 1, `implement.md` Start step 3, `review.md` Start step 2. `protect-main.py` checks only
    `main`/`master`; CI triggers on `main`; neither depends on the `task/` prefix.

## Steps

The task-file commit `docs(tasks): add 005 Specific exceptions` comes first. Then one commit per step,
in this order. Each commit is self-contained with `make check` green.

1. **Refactor task branches are named `refactor/NNN-slug`**: the agents and the developer follow and
   find refactor branches as they do task branches. Documentation only.
   Commit: `docs(workflow): name refactor task branches refactor/NNN-slug (#005)`. This commit also
   sets this task's status to `in-progress`.
2. **A program that cannot be started is reported as `ProcessNotStarted`**, without a PHP warning.
   Commit: `fix(process): report a program that cannot start as ProcessNotStarted (#005)`.
3. **A stored tile that cannot be decompressed is reported as `StoredTileCorrupt`**, without a PHP
   warning; the response is still 500.
   Commit: `fix(tiles): report a stored tile that cannot be decompressed as StoredTileCorrupt (#005)`.
4. **Invalid converted parcels are rejected with `InvalidConvertedParcels`.**
   Commit: `refactor(import): reject invalid converted parcels with InvalidConvertedParcels (#005)`.
5. **Task 004 is recorded as verified and done.** Documentation only.
   Commit: `docs(tasks): record the verification of task 004 (#005)`. This commit also sets this
   task's status to `done`.

## Architecture

### Domain

No change.

### Application

New: `src/Application/Import/InvalidConvertedParcels.php`

- `final class InvalidConvertedParcels extends \InvalidArgumentException`.
- Named constructors, all `public static function …: self`:

  | Constructor                 | Message                                                       |
  |-----------------------------|---------------------------------------------------------------|
  | `noParcels(int $area)`      | `The cadastral area %d has no converted parcels.`             |
  | `noAreas()`                 | `Converted parcels must cover at least one cadastral area.`   |
  | `duplicateArea(int $area)`  | `The cadastral area %d is counted more than once.`            |

Changed:

- `AreaParcelCount::__construct`: `parcelCount < 1` → `throw InvalidConvertedParcels::noParcels($area->value)`.
- `ConvertedParcels::__construct`: empty list → `InvalidConvertedParcels::noAreas()`; an area seen twice
  → `InvalidConvertedParcels::duplicateArea($count->area->value)`.
- Both constructors get a `@throws InvalidConvertedParcels` docblock. The `use InvalidArgumentException;`
  imports are removed. No other signature changes; ports and DTOs are unchanged.

### Infrastructure

New: `src/Infrastructure/Process/ProcessNotStarted.php`

- `final class ProcessNotStarted extends \RuntimeException`.
- `public static function program(string $program, string $reason): self`, message
  `Cannot start "<program>": <reason>`, e.g.
  `Cannot start "ogr2ogr": proc_open(): posix_spawn() failed: No such file or directory`.

Changed: `ProcessRunner::run(array $command): ProcessResult`

- Signature unchanged. Docblock: `@throws ProcessNotStarted when the program cannot be started`
  (replaces `@throws RuntimeException`; the `use RuntimeException;` import goes).
- Calls `error_clear_last()`, then `@proc_open(...)` with the same arguments as today. A comment says
  why: PHP raises a warning before returning `false`, and the failure is reported as an exception
  instead.
- When the result is not a resource: `throw ProcessNotStarted::program($command[0], $reason)`, where
  `$reason` is `error_get_last()['message']`, or `unknown reason` when there is no last error.
- The callers (`ParcelConverterUsingOgr2ogr`, `TilesetBuilderUsingTippecanoe`,
  `DistrictAreaExtractorUsingOgr2ogr`) do not change (Decision 3).

### Handlers (HTTP)

New: `src/Handler/StoredTileCorrupt.php`, next to `MapPageUnavailable`

- `final class StoredTileCorrupt extends \RuntimeException`.
- `public static function cannotDecompress(): self`, message
  `A stored tile cannot be decompressed.`

Changed: `GetParcelTileHandler::handle()`

- The no-gzip branch calls `@gzdecode(...)` (comment: PHP raises a warning before returning `false`)
  and on `false` throws `StoredTileCorrupt::cannotDecompress()`.
- Add a `@throws StoredTileCorrupt` docblock to `handle()`.
- Routes, status codes, headers and the gzip branch are unchanged. `HttpKernel` still maps the
  exception to 500 (`Response::internalError()`) and reports it; no kernel change.

### Commands (CLI)

No change. A missing binary still ends as `Unexpected error: Cannot start "<program>": …` with exit 1
(the message text changes from `Cannot start <program>.`).

### Frontend

No change.

### Wiring

No change in Bootstrap.

### Documentation changes (step 1)

- **CLAUDE.md**, Git > Branches, replace the first bullet with:
  - Every task has its own branch, named after the task file: `refactor/NNN-kebab-case-title` for a
    refactor task (it changes how the code is written, not what the application does: code-rule
    fixes, review follow-ups, restructuring), `task/NNN-kebab-case-title` for every other task.
    Both prefixes share the NNN sequence. The plan agent picks the prefix, writes the branch into
    the task file header, and creates the branch from local `main`; the task file is its first
    commit.

  The rest of CLAUDE.md ("Switch to the task branch", "task branches", the review procedure) keeps
  "task branch" as the generic term for both prefixes.
- **`.tasks/TEMPLATE.md`**: after the `Created` line, add
  `- **Branch:** task/NNN-kebab-case-title | refactor/NNN-kebab-case-title`.
- **`.claude/agents/plan.md`**, "Writing the task file", step 1: create the branch with
  `git switch -c <prefix>/NNN-kebab-case-title main`, where the prefix is `task` or `refactor` per
  CLAUDE.md Git > Branches (next free number, same slug as the file). Step 2 additionally says to
  fill in the `Branch:` header line.
- **`.claude/agents/implement.md`**, Start step 3: switch to the task branch named in the task file
  header (`task/NNN-…` or `refactor/NNN-…`; it must already exist, the plan agent created it). For a
  task file without a `Branch:` line, the branch is `task/NNN-slug`. Keep the rest of the step
  (confirm with `git branch --show-current`, never `main`, stop if it does not exist).
- **`.claude/agents/review.md`**, Start step 2: "A branch `task/NNN-slug` or `refactor/NNN-slug`
  gives `.tasks/NNN-slug.md` at `head`." The rest of the step and the output-format example stay.
- **README**, "Development workflow": after the numbered list (before the review-loop paragraph),
  one sentence: each task runs on its own local branch, `task/NNN-slug`, or `refactor/NNN-slug` for
  a task that only changes how the code is written.

### Task 004 record (step 5)

In `.tasks/004-review-agent.md` only:

- Header: `- **Status:** done`.
- Implementation log, Step 2 entry: replace the line "Status stays `in-progress`: awaiting
  verification (smoke test, task 003 dry run, review loop)." with "Verification is recorded below
  (written in task 005, after the merge)."
- Implementation log: add a `### Verification` entry after the Step 2 entry, with:
  1. `make check` was green on every commit (`git rebase --exec "make check" main`).
  2. Guard smoke test passed: the hook denied `git -C … status` live in the reviewer's session; a
     pipe test denied `touch` and `git commit`; no side effects (no file created, `git log -1`
     unchanged).
  3. Dry run on task 003 (base d4a2ef0, head 8f5c575): `No findings.` Spot-checked by the developer
     and accepted.
  4. Review round 1 on this branch (base e2b0686, head e689c99): `No findings.`
  5. Later note: the whole-repository review of `main` (a580ecd) found a violation in task 003's
     commit 05596c2 (generic `\InvalidArgumentException` in `AreaParcelCount` / `ConvertedParcels`)
     that the dry run had missed. It is fixed in task 005. `review.md` was not tuned for it.
- Acceptance criteria: tick every `- [ ]` (`- [x]`).
- `## Review`: after the placeholder sentence, add

  ```
  ### Round 1: head e689c99, 2026-11-21

  No findings.
  ```

## Test scenarios

### Unit

`tests/Unit/Application/Import/AreaParcelCountTest.php` (existing):

- `it_rejects_an_area_without_parcels`: expect `InvalidConvertedParcels` (instead of
  `InvalidArgumentException`) and a message containing `659541`.

`tests/Unit/Application/Import/ConvertedParcelsTest.php` (existing):

- `it_rejects_having_no_area_counts`: expect `InvalidConvertedParcels`.
- `it_rejects_an_area_listed_twice`: expect `InvalidConvertedParcels`, message containing `659541`
  (the message assertion stays).
- The other tests are unchanged.

Red: the tests fail because `InvalidConvertedParcels` does not exist. Tightening the expected type
is not weakening a test.

`tests/Unit/Handler/GetParcelTileHandlerTest.php` (new; mirrors `src/Handler/`):

- Setup: the real `GetParcelTileUseCase` with `ParcelTilesetRepositoryUsingMemory`, the tileset built
  as in `GetParcelTileUseCaseTest` (zoom 9–16), and a tile at `12/2222/1381` with the data
  `"\x1f\x8b" . 'corrupt'` (passes `VectorTile::fromGzippedData`, fails `gzdecode`). The request is a
  `Request('GET', …)` with path parameters `version`, `z`, `x`, `y`.
- `it_reports_a_stored_tile_that_cannot_be_decompressed`: a request without `Accept-Encoding` throws
  `StoredTileCorrupt`. The test passes without a PHP warning (`failOnWarning` makes a leaked warning
  fail the run). Red: the class does not exist / a generic `RuntimeException` plus the warning.
- `it_passes_a_stored_tile_through_unchanged_to_a_client_that_accepts_gzip`: with
  `accept-encoding: gzip` (lower-case header key, as `Request` expects) the same tile gives status
  200, `Content-Encoding: gzip`, and a body identical to the stored bytes. (This documents that the
  corrupt-data check happens only when the handler decompresses.)

### Integration

`tests/Integration/Infrastructure/Process/ProcessRunnerTest.php` (existing):

- `it_reports_a_program_that_cannot_be_started`: running `['parcel-map-no-such-program']` throws
  `ProcessNotStarted` with a message containing `parcel-map-no-such-program` and
  `No such file or directory`. The test passes without a PHP warning. Red: the class does not exist;
  today the run also raises the `proc_open` warning.
- The two existing tests are unchanged.

### API (HTTP and CLI)

None (Decision 7). The existing `ParcelTileTest`, `ImportParcelsCommandTest` and
`GenerateCoverageCommandTest` must stay green unchanged.

### Frontend (unit and E2E)

None.

## Acceptance criteria

- [ ] `git grep -nE "new \\\\?(RuntimeException|InvalidArgumentException)\(" -- src` prints nothing.
- [ ] `ProcessNotStarted`, `StoredTileCorrupt` and `InvalidConvertedParcels` exist with exactly the
      named constructors and messages in "Architecture", and are thrown where specified.
- [ ] The new and changed tests in "Test scenarios" exist and pass, with no PHP warning in the
      PHPUnit output.
- [ ] HTTP status codes, headers and CLI exit codes are unchanged; the API and E2E suites pass
      without changes.
- [ ] The documentation matches "Documentation changes (step 1)"; `refactor/005-specific-exceptions`
      maps to `.tasks/005-specific-exceptions.md` under the new `review.md` rule.
- [ ] `.tasks/004-review-agent.md` matches "Task 004 record (step 5)"; no other part of 004 and no
      other task file (001–003) is changed.
- [ ] No change in `frontend/`, `public/`, `config/`, `bin/`, `src/Bootstrap/`, `.github/`,
      `.claude/settings.json`, `.claude/hooks/`, `deptrac.yaml`, the Glossary.
- [ ] Five commits after the task-file commit, in the order and with the subjects of "Steps".
- [ ] `make check` passes at every commit (`git rebase --exec "make check" main`).
- [ ] Task status `done`.
- [ ] No performance impact: only failure paths change.

## Risks and assumptions

- **Implement starts before the convention exists.** At spawn time `implement.md` on the branch
  still says `task/NNN-…`. Mitigation: this header and Decision 2 name the branch, and the main
  session names `refactor/005-specific-exceptions` in the implement prompt.
- **Agent definitions may not reload.** The review loop must use the `review.md` from step 1.
  Claude Code hot-reloads `.claude/agents/`; if the reviewer reports "Task file: none", restart
  Claude Code before the round (as in task 004).
- **The `@` operator hides only the warning of the one call.** `error_clear_last()` before
  `proc_open` ensures the reason is not a stale earlier error. If PHP ever stops setting a last
  error, the message says `unknown reason`; the exception type stays correct.
- **The `proc_open` warning text is platform-specific** (`posix_spawn() failed: …` on this image).
  The integration test asserts only the program name and `No such file or directory`, both of
  which come from the verified behaviour in the app image.
- **Message text changes.** `Cannot start <program>.` becomes `Cannot start "<program>": <reason>`;
  the corrupt-tile message changes. No test or code depends on the old texts (verified with
  `git grep`).

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: refactor branch convention

Documentation only, as planned: CLAUDE.md, TEMPLATE.md, plan.md, implement.md, review.md and README. Status set to `in-progress`.

### Step 2: ProcessNotStarted

`ProcessNotStarted::program()` is thrown by `ProcessRunner::run()`; the `proc_open` warning is suppressed with `@` and folded into the message. Red first: the new integration test failed on the missing class plus the leaked warning.

## Review

Filled in during the review loop (implement agent), one entry per round with findings.
