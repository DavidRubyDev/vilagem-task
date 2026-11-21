# NNN: Title

- **Status:** approved | in-progress | done | blocked
- **Created:** YYYY-MM-DD
- **Request:** the developer's request, in their words.

## Goal

What the change delivers and why, in two or three sentences.

## Scope

**In scope:**

-

**Out of scope:**

-

## Decisions

Decisions made with the developer during planning, each with a short reason.

1.

## Verified facts

External facts the plan relies on (endpoints, formats, versions) and where they were verified.

-

## Steps

Ordered vertical slices. Each one works end to end and is committed on its own with all tests green.

1. **Step title**: what the user or operator can do after this step.
2.

## Architecture

### Domain

Entities, value objects (with invariants), domain services, domain exceptions, repository interfaces.

### Application

Use cases: input DTO, output DTO, exceptions. Outbound ports: interface and method signatures.

### Infrastructure

Adapters: which port, which resource (file, table schema, binary, endpoint), format details.

### Handlers (HTTP)

Routes, request/response contracts with JSON examples, status codes, error mapping.

### Commands (CLI)

`bin/console` signatures, arguments and options, output examples, exit codes.

### Frontend

UI changes and behaviour.

### Wiring

What gets registered in Bootstrap.

## Test scenarios

Behaviours to test, described in words, including edge cases and errors.

### Unit

-

### Integration

-

### API (HTTP and CLI)

-

### Frontend (unit and E2E)

-

## Acceptance criteria

Observable, checkable outcomes, including performance where relevant.

- [ ]

## Risks and assumptions

-

## Implementation log

Filled in by the implement agent, one entry per step.

## Review

Filled in during the review loop (implement agent), one entry per round with findings.
