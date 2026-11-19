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
data/              Downloaded and generated data. Git-ignored, except small test fixtures.
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
6. Review feedback from the developer on an existing branch goes back to implement (or plan,
   if the architecture changes), which folds it into the existing commits (see Git).

The main session does not write production code itself; code changes go through a task file
and the implement agent.

## Git

### Branches

- Every task has its own branch: `task/NNN-kebab-case-title`, matching the task file name.
  The plan agent creates it from local `main`; the task file is its first commit.
- **Never commit to `main`.** This is enforced by a hook in `.claude/settings.json` that denies
  commit, merge, cherry-pick, revert, am and rebase while `main` is checked out. Switch to the
  task branch in a separate command before committing; the hook checks the branch that is
  current *before* the command runs.
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

All commands run inside Docker. The test/QA scripts are created by the first task (walking
skeleton); until then only the Docker image exists.

```bash
docker compose build                 # build the image
docker compose up                    # run the app at http://localhost:8000
docker compose run --rm app <cmd>    # run any command in the app container
```

When the first task adds Composer/npm scripts or a Makefile, update this section in the same
commit.

## Glossary

Source-data term (Czech) → name used in code.

| Czech (source data)                   | Code name            | Notes                                                          |
|---------------------------------------|----------------------|----------------------------------------------------------------|
| parcela                               | `Parcel`             |                                                                |
| katastrální území (KÚ)                | `CadastralArea`      | Identified by a 6-digit code.                                  |
| obec                                  | `Municipality`       |                                                                |
| okres                                 | `District`           | Jičín district.                                                |
| parcelní číslo                        | `ParcelNumber`       | Base number (kmenové číslo) + optional subdivision (poddělení), e.g. `123/4`. |
| stavební / pozemková parcela          | `ParcelKind::Building` / `ParcelKind::Land` | Building plots are displayed with the `st.` prefix. |
| druh pozemku                          | `LandType`           | Arable land, forest, permanent grassland, …                    |
| způsob využití pozemku                | `LandUse`            |                                                                |
| výměra                                | `Area`               | Square metres.                                                 |
| list vlastnictví (LV)                 | `OwnershipSheet`     | Ownership data itself is not in open data.                     |
| zjednodušená evidence (PK / EN)       | `SimplifiedRecordParcel` | Parcels without their own geometry in the cadastral map.   |
| katastr nemovitostí (KN)              | Cadastre             |                                                                |
| RÚIAN, VFR                            | —                    | ČÚZK register and its exchange format (data source).           |
| S-JTSK (EPSG:5514)                    | —                    | Source coordinate system; the map uses WGS84 / Web Mercator.   |
