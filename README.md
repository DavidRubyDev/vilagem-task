# Jičín Parcel Map

A web application that shows land parcels of the Jičín district (Czech Republic) on a map.
Clicking a parcel shows its details. The application stays smooth even when the whole
district is in view.

Built as an interview task for Viagem a.s.

> **Status:** repository setup. The application itself is built in the tasks in
> [`.tasks/`](.tasks/). This README is updated as features land.

## Prerequisites

- Docker 24+ with Docker Compose v2

Nothing else is required on the host. PHP 8.6, Composer and all other tooling run inside the
container.

## Running

```bash
docker compose build
docker compose up        # http://localhost:8000
```

Commands for tests, QA and data import are added with the first tasks and documented here.

## Architecture

Hexagonal architecture with DDD, written in plain PHP (no framework):

| Layer            | Role                                                                |
|------------------|---------------------------------------------------------------------|
| `Domain`         | Parcels, cadastral areas and their rules. No dependencies.          |
| `Application`    | Use cases and the ports they need.                                  |
| `Infrastructure` | Outbound adapters: data storage, ČÚZK data download, geo tooling.   |
| `Handler`        | Inbound HTTP adapters (map tiles, parcel detail API).               |
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

### Surprises

_To be filled in._

### With more time

_To be filled in._
