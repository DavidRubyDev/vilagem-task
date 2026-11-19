# Jičín Parcel Map

A web application that shows land parcels of the Jičín district (Czech Republic) on a map.
Clicking a parcel shows its details. The application stays smooth even when the whole
district is in view.

Built as an interview task for Viagem a.s.

> **Status:** the walking skeleton exists (HTTP front controller, router, tooling, CI) and
> `GET /api/map-config` serves the initial map view (Jičín, zoom 12) and the OpenStreetMap base
> map. Every other path answers a JSON 404 for now. The application itself is built in the tasks in
> [`.tasks/`](.tasks/). This README is updated as features land.

## Prerequisites

- Docker 24+ with Docker Compose v2
- GNU make (optional; it only wraps the `docker compose` commands shown below)

Nothing else is required on the host. PHP 8.6, Composer and all other tooling run inside the
container.

## Running

```bash
docker compose build
docker compose up        # http://localhost:8000 (also: make up)
```

`GET /api/map-config` returns the map configuration; every other path answers
`404 {"error":"not_found"}`.

## Testing and QA

`make` wraps `docker compose`; the plain command is shown for each target. `PHP` below means
`docker compose run --rm --no-deps app`.

| Make target       | Plain command                                                                 | What it does                                  |
|-------------------|-------------------------------------------------------------------------------|-----------------------------------------------|
| `make check`      | (runs every check group, see below)                                           | Full local check, same groups as CI           |
| `make qa`         | `cs`, `phpstan`, `deptrac`, `lint-ci`                                          | All static checks                             |
| `make test`       | `test-unit`, `test-api`                                                       | All tests                                     |
| `make test-unit`  | `PHP vendor/bin/phpunit --testsuite unit`                                     | Unit tests                                    |
| `make test-api`   | `docker compose up -d --wait --no-deps app`, then `PHP vendor/bin/phpunit --testsuite api` | API tests over real HTTP         |
| `make cs`         | `PHP vendor/bin/php-cs-fixer check --diff`                                    | Code style (PER-CS)                           |
| `make cs-fix`     | `PHP vendor/bin/php-cs-fixer fix`                                             | Fix code style                                |
| `make phpstan`    | `PHP vendor/bin/phpstan analyse --no-progress`                                | Static analysis, level max                    |
| `make deptrac`    | `PHP vendor/bin/deptrac analyse --no-progress --fail-on-uncovered --report-uncovered` | Layer dependency rules                |
| `make lint-ci`    | `docker run --rm -v "$PWD":/repo -w /repo rhysd/actionlint:1.7.12 .github/workflows/ci.yml` | Lint the GitHub workflow        |

Run `composer install` first (`make install-php`); the `make` check groups do it for you.

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
- **Docker for everything.** Same PHP version and tools on every machine.
- **PHP 8.6.** It is still at the RC stage at the time of setup (`php:8.6-rc-cli` image). The
  Dockerfile switches to the GA image once it is released.
- **PHP-CS-Fixer runs with `setUnsupportedPhpVersionAllowed(true)`** until it supports PHP 8.6.

### Surprises

_To be filled in._

### With more time

- Docker layer cache in CI (BuildKit GitHub Actions cache), to avoid rebuilding the PHP image
  in several jobs and pulling the Playwright image on every run.
- Activate the ruleset once the repository is public.
