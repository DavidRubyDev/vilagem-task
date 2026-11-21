# 004: Review agent

- **Status:** in-progress
- **Created:** 2026-11-21
- **Request:** "@plan another agent. Think about how capable it shold be (what model should be used).
  The agent will look at the current branch and using only the architecture and language rules
  described in @CLAUDE.md will perform a code review. The output from this review will be printed
  to the developer. He will confirm which points are valid and after confirming that, the
  implement agent will perform the fix. This will iterate, until there are no more issues"

  Answers during planning, verbatim: "use recommended approach to all open questions". The draft
  was then "approved". The open questions and the recommendations that were accepted are recorded
  as Decisions 1–9.

## Goal

A third agent, **review**, reads the commits of a task branch. It reports numbered findings
against the architecture and language rules of CLAUDE.md, and nothing else. It is read-only.

The developer confirms or rejects each finding by number. The implement agent folds the
confirmed fixes into the commits they belong to. A fresh review runs, and the loop repeats until
a round has no findings.

This is a tooling change: there is no production code. The only executable code is the
reviewer's read-only Bash guard (a Python hook), which is tested.

## Scope

**In scope:**

- `.claude/agents/review.md`: the agent definition (frontmatter and prompt).
- The read-only Bash guard:
  - the hook `.claude/hooks/review-read-only.py`, scoped to the review agent through its
    frontmatter;
  - its black-box tests in `.claude/hooks/tests/test_review_read_only.py`.
- `make test-hooks` (Python unittest in a pinned Docker image), added to `CHECK_TARGETS` (so CI
  runs it) and to `make test`.
- The review loop as the standard main-session procedure. Changes to:
  - CLAUDE.md: Agents list, main-session procedure, Commands;
  - `.tasks/TEMPLATE.md`: a new `## Review` section;
  - `.claude/agents/implement.md`: how to apply review findings;
  - `.claude/agents/plan.md`: one line on contract-changing findings;
  - README: development workflow.
- Verification by the main session with the developer: a guard smoke test, a dry run on task 003,
  and the loop on this branch itself.

**Out of scope:**

- Any change to `src/`, `tests/` (PHP/TS), `frontend/`, `public/`, `config/`.
- Tests for the existing `protect-main.py` hook (a possible follow-up; `make test-hooks` will pick
  them up when they are added).
- Changes to `.claude/settings.json` or `.github/workflows/ci.yml`. CI follows `CHECK_TARGETS`.
- Python linting or formatting tools.
- Fixing findings of the task 003 dry run. Task 003 is on `main`; confirmed findings are listed
  for a follow-up task.
- Editing the done task files 001–003, including their references to "task 004" for the parcel
  detail panel. That roadmap item becomes 005.
- A slash command or any automation that runs the loop without the developer's confirmation.
- Replacing the existing `git rebase … main` commands in CLAUDE.md with a merge-base form (see
  Risks).

## Decisions

1. **Model `opus`, effort `high`.**
   - The review must judge design (hexagonal layering, DDD modelling, naming) and keep false
     positives low, because every finding costs the developer a confirm/reject decision.
   - Sonnet is the implement model, so it would share the blind spots of the agent that wrote the
     code.
   - `xhigh` adds latency and cost on every round for little gain on a branch of this size
     (task 003: 98 files, +5,362 lines).
   - `inherit` would make the result depend on the main session's model.
2. **Numbering.** This task is 004, branch `task/004-review-agent` (next free number, as CLAUDE.md
   says). The parcel detail panel becomes 005. The done task files 002 and 003 keep saying
   "task 004"; they are historical records.
3. **The testing conventions that a diff shows are in scope**:
   - test names describe behaviour;
   - unit tests mirror `src/`;
   - fakes over mocks, and fake naming (`…UsingMemory`, `ClockUsingFixedTime`);
   - the per-level rules (no I/O in unit tests, no network in integration tests);
   - fixtures small, committed and documented.

   The process rules ("tests come first", "never weaken a test") are out: a diff cannot show them
   reliably.
4. **Frontend (TypeScript).**
   - These apply: the Language rule, intention-revealing names, "comment the why", and Glossary
     names.
   - The other Code rules apply to PHP only: strict_types, PSR-4, final/readonly, constructor
     injection, specific exceptions instead of null/false, Composer dependencies. CLAUDE.md
     phrases them in PHP terms and defines no frontend architecture.
5. **Verdicts are recorded in the task file. Each round uses a fresh reviewer.**
   - A `## Review` section in the task file records every round that has findings. The implement
     agent writes it and amends it into the branch's last commit.
   - Each round spawns a fresh review agent, which reads the earlier verdicts from that section.
   - Why durable: the record survives the session, and the task file is the single source of
     truth.
   - Why fresh: the history is rewritten after each round, so an old reviewer's file:line
     references and SHAs are stale. A fresh reviewer is also not anchored to its earlier view.
6. **Every round reviews the whole branch.**
   - A fix such as a rename can affect other places, and the developer asked for "until there are
     no more issues".
   - Findings rejected in an earlier round are not raised again (same rule, same code).
   - A confirmed finding that is still present is raised again with a "Repeats" line.
7. **Only code the branch added or changed is reviewed.** Violations in untouched code are not
   reported, even in touched files. This keeps every finding actionable within the task.
   - Exception: an omission that the branch's change requires is reported, e.g. a new domain
     concept that is missing from the Glossary.
8. **The reviewer is read-only through three layers.**
   - The tools allowlist: no Write, Edit or Agent.
   - A Bash allowlist hook, scoped to the review agent in its frontmatter. It fails closed and is
     tested by `make test-hooks`, which runs in CI.
   - The existing project-level guards: `protect-main.py` and the push deny.
9. **Verification.**
   - `make check` is green at every commit.
   - A guard smoke test in a real session.
   - A dry run on task 003, with at most 1 in 5 findings judged false positives by the developer.
   - The review loop on this branch itself, ending with `No findings.`
10. **No severity in findings.** Every reported finding breaks a binding rule, and the precision
    bar removes nits. A severity scale would invite "minor" findings, each of which still costs a
    decision. The one distinction that changes handling is `Task-file contract: yes`, which routes
    the finding to plan first.
11. **The reviewer stops on a dirty working tree** when it reviews `HEAD`. Findings must map to
    commits, and uncommitted changes mean implement is mid-work.
12. **The hook never grants permission.** An allowed command produces no output (exit 0, "no
    decision"), so the normal permission flow still applies. Only `deny` is ever emitted.
13. **Clean rounds are not recorded in the task file.** The main session reports them to the
    developer. Recording them would cost an extra implement run and `make check` for no new
    information.
14. **After later changes to the branch** (step 7 of the procedure, developer feedback), the main
    session runs the review loop again before reporting back, unless the developer says to skip
    it.
15. **The hook runs on host `python3`.** Claude Code runs hooks on the host; `protect-main.py`
    already does, so this is the existing exception to "everything runs in Docker". The hook's
    tests run in Docker.
16. **TDD.** The hook is code, so it is written test-first (red → green → refactor). The agent
    prompt and the documentation are not executable. They are verified by the smoke test, the dry
    run and the self-review loop (Verification). This is stated here so that it is not a silent
    exception to CLAUDE.md's TDD rule.

## Verified facts

Verified on 2026-11-20.

- **Subagent frontmatter**, from the Claude Code subagents docs
  (https://code.claude.com/docs/en/sub-agents):
  - `model` accepts `sonnet`, `opus`, `haiku`, a full model ID, or `inherit`;
  - `effort` accepts `low`, `medium`, `high`, `xhigh`, `max` ("available levels depend on the
    model");
  - `tools` is an allowlist; omitting it inherits every tool;
  - `hooks` are "lifecycle hooks scoped to this subagent".
- **Restricting Bash**, same page:
  - The `tools` field does not support Bash command specifiers.
  - "A `disallowedTools` entry with a specifier, such as `Bash(git push *)`, still removes the
    whole tool from the subagent". The docs recommend settings `permissions.deny` or a
    `PreToolUse` hook instead. Hence the hook.
- **Other subagent behaviour**, same page:
  - Claude Code watches `.claude/agents/`. A new or edited agent file is used by the next
    delegation, with no restart ("If Claude can't find the new subagent, restart Claude Code").
  - Custom subagents load the project CLAUDE.md unless `omitClaudeMd: true` is set.
  - `SendMessage` resumes a subagent with its full history.
  - A subagent can spawn subagents unless `Agent` is missing from its `tools`.
- **Hooks**, from the Claude Code hooks docs (https://code.claude.com/docs/en/hooks):
  - PreToolUse denies with
    `{"hookSpecificOutput": {"hookEventName": "PreToolUse", "permissionDecision": "deny", "permissionDecisionReason": "…"}}`
    on stdout and exit 0. This is the same shape `protect-main.py` uses.
  - Exit 0 with no output is "no decision".
  - Exit 2 blocks. Any other exit code is a non-blocking error: **the tool call proceeds**. A
    timed-out hook does not block either. So the hook must catch every error and deny.
  - Tool events from a subagent fire the project hooks too, so `protect-main.py` covers the
    reviewer. The input carries `agent_id` / `agent_type`.
  - Frontmatter hooks of a project subagent run only after the workspace trust dialog is
    accepted; a `-p` session does not count.
  - `$CLAUDE_PROJECT_DIR` is exported to hook processes.
- **Python image:** Docker Hub lists `python:3.14.10-slim` (Hub API, tags of `library/python`).
  The host `python3` is 3.14.10.
- **Python `shlex`:** `shlex.shlex(s, posix=True, punctuation_chars=True)` splits unquoted runs of
  `();<>|&` into separate tokens and keeps quoted text in one token. `whitespace_split` is
  compatible since Python 3.8. `commenters` defaults to `#` and must be set to empty, so that `#`
  inside arguments is not dropped.
- **Repository facts** (local git):
  - Task 003 is merged into `main` as `e2b0686`. Its first parent `d4a2ef0` is task 003's base,
    and its second parent `8f5c575` is the tip of `task/003-whole-jicin-district`.
  - `CLAUDE.md` is identical at `8f5c575` and `e2b0686`.
- **Tool coverage**, from the repository configs:
  - `.php-cs-fixer.dist.php` enforces `@PER-CS` and `declare_strict_types` on `src`, `tests`,
    `public` and `bin/console`.
  - PHPStan runs at level max on the same paths.
  - `deptrac.yaml` covers class references between the layers in `./src` only, not `tests/`.
  - ESLint (`strictTypeChecked`), Prettier and `tsc -b` cover the frontend.

## Steps

0. **Task file**: `docs(tasks): add 004 Review agent` (this commit).
1. **The developer can run a read-only review of a task branch.** One commit:
   `chore(review): add the read-only review agent (#004)`.
   - It contains:
     - `.claude/agents/review.md`;
     - `.claude/hooks/review-read-only.py`;
     - `.claude/hooks/tests/test_review_read_only.py`;
     - the Makefile `test-hooks` target, `PYTHON_IMAGE`, `CHECK_TARGETS` and `test`;
     - the CLAUDE.md Agents list entry and Commands lines;
     - the README agents list;
     - this task file, with status `in-progress` and an implementation-log entry.
   - Order: hook tests first (red: the script does not exist), then the hook, then the agent file
     and the docs.
   - After this commit, the main session can spawn `review` by hand and use the existing step 6
     ("review feedback goes back to implement").
2. **The review loop is the standard procedure.** One commit:
   `docs(workflow): add the review loop (#004)`.
   - It contains the CLAUDE.md main-session procedure, `.tasks/TEMPLATE.md`, `implement.md`,
     `plan.md`, the README loop description, and this task file (implementation-log entry).
   - The status stays `in-progress`. Implement then reports "awaiting verification" instead of
     finishing.
3. **Verification** by the main session with the developer (see Verification). No new commit.
   The implement agent records the outcome in the implementation log, sets the status to `done`,
   and amends commit 2.

## Architecture

### Domain / Application / Infrastructure / Handlers (HTTP) / Commands (CLI) / Frontend / Wiring

None. This task touches no code under `src/`, `frontend/`, `public/` or `bin/`, and adds no
deptrac layer.

### Review agent: `.claude/agents/review.md`

**Frontmatter** (exact):

```yaml
---
name: review
description: Reviews the commits of a task branch against the architecture and language rules in CLAUDE.md and reports numbered findings for the developer to confirm or reject. Read-only; it changes nothing. Pass it the base (default main), the head (default HEAD) and the round number.
model: opus
effort: high
tools: Read, Grep, Glob, Bash
hooks:
  PreToolUse:
    - matcher: "Bash"
      hooks:
        - type: command
          command: python3 "$CLAUDE_PROJECT_DIR/.claude/hooks/review-read-only.py"
          timeout: 10
---
```

**Body.** It is written in the tone and structure of `plan.md` / `implement.md`: second person,
short sections, binding wording. The sections below, in this order, with this content. The
implement agent writes the prose; the content is fixed.

1. **Role** (opening paragraph).
   - You are the review agent. You review the commits of a task branch against a fixed subset of
     CLAUDE.md and report findings. You are read-only.
   - You never change files, commits, branches or the index. Bash is limited to read-only git
     commands by a hook.
   - You do not fix anything, suggest improvements outside the rules, praise, or summarise the
     code.

2. **Input.** The main session passes:
   - `base` (default `main`);
   - `head` (default `HEAD`);
   - the round number (default 1). A dry run is marked as such and changes nothing else.

   Earlier verdicts come only from the task file's `## Review` section, never from the prompt.

3. **Start.**
   1. Resolve `head` to a commit (`git rev-parse --short`) and the branch name
      (`git branch --show-current` when `head` is `HEAD`; otherwise the ref as given).
   2. Find the task file. A branch `task/NNN-slug` gives `.tasks/NNN-slug.md` at `head`. If there
      is no task file, note it in the header ("Task file: none") and continue; only its Review
      section is needed.
   3. Stop with a not-reviewable result when:
      - `head` resolves to the same commit as `main`;
      - `git rev-list --count base..head` is 0;
      - `head` is `HEAD` and the working tree has uncommitted or untracked, non-ignored changes
        (`git status --porcelain` is not empty).
   4. Read the rules from `git show <head>:CLAUDE.md`, so that Glossary entries and rules added on
      the branch count. If a section in the in-scope list is absent at `head` (an old branch),
      skip it.
   5. Read the `## Review` section of the task file at `head`, if present: the rejected findings,
      and the confirmed findings with their fixing commits.

4. **Procedure.**
   - List the commits: `git log --reverse --format='%h %s' base..head`.
   - Read the whole change: `git diff base...head`. Read each commit with `git show <sha>`.
   - For every touched file (all files in the diff, tests and docs included), read the whole file
     at `head` for context.
   - Attribute each line to the commit that last changed it: `git blame base..head -- <path>`.
     Lines from before the branch show as boundary commits (`^…`) and are not reviewed
     (Decision 7).
   - Check every in-scope rule against the added or changed lines. Check the Glossary for every new
     domain type or term.
   - Check commit messages for the Language rule only.
   - **Where to read from.**
     - When `head` is the checked-out `HEAD` and the tree is clean, Read, Grep and Glob may be
       used on the working tree.
     - Otherwise read everything through git: `git show <head>:<path>`, `git grep -n <pattern>
       <head> -- <path>`, `git ls-tree -r --name-only <head>`. The working tree then belongs to a
       different commit.

5. **Rules in scope.** Name the CLAUDE.md section of each rule, and quote the rule in findings.
   | CLAUDE.md section | What is checked |
   |---|---|
   | Project, the "Language" bullet | English everywhere: code, comments, tests, docs, task files, commit messages. Czech only where it names source data (Glossary). Applies to all files. |
   | Architecture: hexagonal + DDD (layout) | Each class sits in the layer the layout assigns. Use cases are one class with one public method. One handler per endpoint, one command per CLI action. HTTP plumbing lives only in `Handler/Http/`, CLI plumbing only in `Command/Console/`. Ports are owned by Domain (repositories) or Application (others). |
   | Dependency rule | Handlers and commands only translate input to an input DTO, call the use case, and translate the output DTO or exception. They contain no business logic and do not touch Domain objects or Infrastructure (also through DTOs that leak domain objects). Bootstrap wires only. Tests: unit tests use Domain/Application and fakes, not Infrastructure. |
   | DDD rules | All bullets: explicit domain types, invariants validated in constructors / named constructors, value objects `final readonly` with `equals()`, entities without public setters, no primitive obsession inside Application and Domain, Glossary names, new concepts added to the Glossary in the same change. |
   | Code rules, including Naming | All bullets and all Naming rules, except those in "Not reported". The PHP-specific bullets apply to PHP only. For TypeScript, only intention-revealing names and "comment the why" apply (Decision 4). |
   | Glossary | Code names match the Glossary. A new domain concept appears in the Glossary in the same commit that introduces it. |
   | Testing (conventions only) | Test names describe behaviour. Unit tests mirror `src/`. Fakes over mocks, with fake naming per Naming. The per-level rules from the testing table (unit: no I/O, filesystem, network or Docker services; integration: no network, recorded ČÚZK fixtures). Fixture data small, committed, and documented in the fixtures README. |

6. **Not reported.**
   - Correctness bugs, performance, security, product and UX decisions.
   - Whether the tests are sufficient, TDD order, deleted or weakened tests.
   - Commit structure and the commit message format (only their language is checked).
   - Workflow, the accuracy of the Commands section and README, and the plan content of task files.
   - Anything the tools already enforce:
     - PHP-CS-Fixer: PER-CS formatting and `declare(strict_types=1)`;
     - PHPStan max: missing or wrong type declarations;
     - deptrac: class references between the layers in `src/`;
     - ESLint, Prettier and `tsc` for the frontend.

     An unjustified `mixed` is in scope: PHPStan accepts an explicit `mixed`. So are layering
     issues deptrac cannot see: logic placement, and `tests/`.
   - Code that the branch did not add or change (Decision 7).
   - Findings that an earlier round rejected (same rule, same code), unless the code changed so
     that the rejection reason no longer applies.

7. **Precision bar.**
   - Report a finding only when you can quote the rule and point to the line, and a maintainer
     reading that rule would agree it is broken. When in doubt, leave it out. False positives cost
     the developer more than a missed finding costs the next round.
   - One finding per breach per commit. If the same breach repeats in several places within one
     commit, list all locations in one finding.
   - Point to the commit that introduced the line (from `git blame base..head`). For an omission
     (e.g. a missing Glossary entry), point to the commit that introduced the concept.
   - If the code follows the task file and the task file prescribes the violation, still report it
     and set `Task-file contract: yes (<task-file section or Decision N>)`. Also use `yes` when
     the fix would change a contract the task file defines: a port, DTO, route, CLI signature,
     schema, or a public class name.
   - If a confirmed finding from an earlier round is still present, report it again with a
     `Repeats:` line.

8. **Output format** (exact; findings ordered by commit order, then by path):

   ```
   Review round 1 of task/005-parcel-detail-panel
   Base main (d4a2ef0), head 8f5c575, 7 commits. Rules: CLAUDE.md at 8f5c575. Task file: .tasks/005-parcel-detail-panel.md

   1. src/Handler/ParcelDetailHandler.php:42
      Commit: 3c1d2e4 feat(parcel): show parcel detail on click (#005)
      Rule (Dependency rule): "Handlers and commands never touch Domain objects or Infrastructure directly."
      Problem: The handler builds a ParcelNumber from the query string to validate it.
      Fix: Pass the raw string in GetParcelDetailInput; the use case builds ParcelNumber and throws InvalidParcelNumber, which the handler maps to 400.
      Task-file contract: no

   2. tests/Unit/Application/Parcel/GetParcelDetailUseCaseTest.php:18, :57
      Commit: 3c1d2e4 feat(parcel): show parcel detail on click (#005)
      Rule (Testing): "Fakes over mocks: write in-memory implementations of ports for unit tests."
      Problem: The test creates a PHPUnit mock of ParcelRepository.
      Fix: Use a ParcelRepositoryUsingMemory fake in tests/Fake/.
      Task-file contract: no
      Repeats: round 1 #4 (confirmed, still present)

   2 findings. Confirm or reject each by number.
   ```

   - Several locations in one file: `path:line, :line`. In several files: one location per line,
     each indented under the number.
   - `Repeats:` appears only when it applies.
   - A clean result is the header followed by one line: `No findings.`
   - A not-reviewable result is the header (with whatever could be resolved) followed by one
     line: `Not reviewable: <reason>.`
   - Nothing else is printed: no preamble, no summary, no list of what was fine.

### Read-only guard: `.claude/hooks/review-read-only.py`

- **Header.** Executable (`chmod +x`), with the shebang `#!/usr/bin/env python3` and a module
  docstring, like `protect-main.py`. Standard library only.
- **Input:** the PreToolUse JSON on stdin. Only `tool_input.command` is used.
- **Output:**
  - Allowed: no output, exit 0.
  - Denied: the deny JSON on stdout (shape in Verified facts), exit 0. The reason is
    `"The review agent is read-only. Allowed: one git command without shell operators: git <subcommand> … with <subcommand> one of diff, log, show, blame, grep, ls-files, ls-tree, rev-parse, rev-list, merge-base, range-diff, cat-file, status, branch (--show-current, --list, -a, --all, -r, -v, -vv only)."`
    For malformed input, the reason starts with `"Malformed hook input; denied. "` followed by the
    same text.
- **Fails closed.** The whole decision runs inside one `try`. Any exception (bad JSON, a missing or
  non-string or empty `command`, a `shlex` `ValueError` on unbalanced quotes, anything else) is a
  deny. Exit code 2 and other non-zero codes are never used.
- **Policy**, applied in this order; the first failing check denies:
  1. `command` is a non-empty string after stripping whitespace.
  2. The raw command contains none of `$`, `` ` ``, `\n`, `\r`. This applies even inside quotes:
     bash expands `$(…)` and backticks inside double quotes. A rejected regex anchor `$` in
     `git grep` is an acceptable false deny; the Grep tool covers it.
  3. Tokenise with `shlex.shlex(command, posix=True, punctuation_chars=True)`, with
     `whitespace_split = True` and `commenters = ""`. Any token made only of the characters
     `();<>|&` is denied. This covers `;`, `&&`, `||`, `|`, `>`, `>>`, `2>&1`, `<(…)` and `&`.
     Quoted operators inside a word stay part of the word and are allowed (`'foo|bar'`).
  4. At least 2 tokens, the first exactly `git`, and the second one of the allowed subcommands.
     This also denies global options (`git -c …`, `git -C …`, `git --git-dir=…`,
     `git --exec-path`, `git -p`), environment prefixes (`FOO=1 git …`), and non-git commands.
  5. No argument (token 3 onwards) starts with `--output`. The diff-family `--output=<file>`
     writes a file.
  6. For `grep`: no argument starts with `--open-files-in-pager`, and no single-dash argument
     (starting with `-` but not `--`) contains `O`. `-O` runs a pager command and can be bundled
     (`-nO`).
  7. For `branch`: every argument is one of `--show-current`, `--list`, `-a`, `--all`, `-r`,
     `-v`, `-vv`. Positional arguments create branches and other options delete or rename them.
- Everything that passes is allowed. Arguments of the other subcommands are not restricted further:
  - `git status` refreshes the index stat cache, which is harmless metadata;
  - `diff --no-index` and `blame --contents` only read.

### Hook tests: `.claude/hooks/tests/test_review_read_only.py`

- Python `unittest`, standard library only.
- Black-box: each test runs the hook as a subprocess, `[sys.executable, <path of the hook
  relative to the test file>]`, with a PreToolUse JSON on stdin:
  `{"hook_event_name": "PreToolUse", "tool_name": "Bash", "tool_input": {"command": "…"}, "cwd": "/repo", "agent_type": "review"}`.
- Assertions:
  - allowed: exit 0 and empty stdout;
  - denied: exit 0 and stdout JSON with `hookSpecificOutput.hookEventName == "PreToolUse"`,
    `permissionDecision == "deny"` and a non-empty `permissionDecisionReason`.
- Test methods are named `test_` + a behaviour sentence, e.g.
  `test_denies_a_command_chained_with_a_semicolon` (the CLAUDE.md naming rule, with the prefix
  unittest requires).

### Make target and CI

- Makefile:
  - `PYTHON_IMAGE := python:3.14.10-slim`, next to `ACTIONLINT_IMAGE`, with a one-line comment
    (the Claude Code hooks run on host python3 and are tested in this image).
  - Target:
    ```
    test-hooks:
    	docker run --rm -u "$(UID):$(GID)" -v "$(CURDIR)":/repo -w /repo $(PYTHON_IMAGE) python -I -B -m unittest discover -s .claude/hooks/tests -v
    ```
    `-B` keeps `__pycache__` out of the repository.
  - `test-hooks` is added to `.PHONY`, to `CHECK_TARGETS` right after `lint-ci`
    (`lint-ci test-hooks php-qa php-tests frontend-checks e2e`), and to `test`
    (`test-hooks test-unit test-integration test-api test-frontend test-e2e`).
- CI: no file change. The matrix is built from `make ci-targets`, so a `test-hooks` job appears
  and is covered by the required `check` job.

### Documentation changes

**Commit 1 (`chore(review): …`):**

- CLAUDE.md, Agents list. After the implement bullet, add:
  - **review** (`.claude/agents/review.md`, strong model, read-only): reviews the commits of a task
    branch against the architecture and language rules in this file and reports numbered
    findings. It changes nothing; its Bash use is limited to read-only git commands by a hook.
- CLAUDE.md, Commands:
  - add `make test-hooks     # Python unittest of the Claude Code hooks in .claude/hooks/ (python:3.14.10-slim image)`;
  - the `make test` line becomes
    `make test           # test-hooks test-unit test-integration test-api test-frontend test-e2e (run make assets first)`.
- CLAUDE.md, Git → Branches: after the sentence on the protect-main hook, add one sentence. The
  review agent is additionally limited to read-only git commands by
  `.claude/hooks/review-read-only.py`, a hook in its frontmatter.
- README "Development workflow":
  - "two agents" becomes "three agents";
  - add item 3, **review** (`.claude/agents/review.md`, stronger model, read-only): reviews the
    commits of a task branch against the architecture and language rules of CLAUDE.md;
  - the developer item becomes 4.

**Commit 2 (`docs(workflow): …`):**

- CLAUDE.md, main-session procedure. Insert a new step 6; the old step 6 becomes step 7. The text,
  to be turned into the list style of the existing steps:

  6. **Review loop.** After implement reports a finished task:
     - Spawn a **fresh** review agent with base `main`, the task branch checked out, and the round
       number (1, 2, …).
     - Relay its report to the developer **verbatim**.
     - The developer answers by number: confirm or reject, optionally with a reason or an adjusted
       fix.
     - Confirmed findings marked `Task-file contract: yes` go to **plan** first, which amends the
       task-file commit.
     - Then send to **implement**, verbatim:
       - the confirmed findings with the developer's notes;
       - the rejected findings with their reasons;
       - the round number;
       - the reviewed head SHA.
     - Implement folds the fixes into their commits, records the round in the task file's
       `## Review` section, and verifies every commit.
     - Repeat with a new review agent.
     - The loop stops when a round reports `No findings.`, when the developer confirms none (implement
       then only records that round), or when the developer says stop.
     - If a finding comes back with `Repeats:`, point this out to the developer instead of
       looping silently.

  7. (former 6) Add one sentence at the end: after such a change, run the review loop again
     before reporting back, unless the developer says to skip it.
- `.tasks/TEMPLATE.md`: after `## Implementation log`, add:

  ```
  ## Review

  Filled in during the review loop (implement agent), one entry per round with findings.
  ```
- `.claude/agents/implement.md`: a new section **"Applying review findings"**, after "Changing an
  existing branch". Content:
  - **Input:** round number, reviewed head SHA, the confirmed findings (verbatim, with the
    developer's notes), the rejected findings with reasons.
  - **Before starting:** be on the task branch with a clean tree. If a confirmed finding says
    `Task-file contract: yes` and the task file has not been revised for it, stop and report.
  - **For each confirmed finding:**
    - Make the fix. TDD applies: a fix that changes behaviour starts with a failing test. A pure
      rename or move is a refactor with all tests green. Updating tests that reference the renamed
      thing is not weakening them.
    - Commit it as `git commit --fixup=<sha>` of the commit the finding names. Find that commit by
      its subject if the SHA is stale. If the finding names the wrong commit, use the right one and
      say so in the report.
    - Then run `git rebase --autosquash main`.
  - **Record the round** in the task file's `## Review` section and `git commit --amend` it into
    the branch's last commit. Appending at the end of the last commit cannot conflict during
    autosquash. Format:

    ```
    ### Round 1: head 5d6e7f8, 2026-11-22

    1. Confirmed. `src/Handler/ParcelDetailHandler.php:42`, Dependency rule: the handler builds a ParcelNumber. Fixed in `feat(parcel): show parcel detail on click (#005)`.
    2. Rejected. `src/Domain/Parcel/Area.php:12`, DDD rules: … Reason: "<developer's words>".
    ```

    Use commit subjects, not SHAs: the SHAs change on every rewrite. A round where every finding is
    rejected is recorded the same way, with no code change.
  - **Verify:** `git rebase --exec "make check" main` when any commit other than the last
    changed; otherwise `make check`.
  - **Report:** for each finding, the commit it was folded into; the new head SHA; the list of
    commits (hash + subject). If the branch was already pushed, say so (force-push with
    `--force-with-lease`).
- `.claude/agents/plan.md`, "When asked to revise an existing task file (…)": extend the
  parenthesis to "e.g. implement reported a problem with the plan, or a confirmed review finding
  changes a contract the task file defines".
- README "Development workflow": after the review item, add a short paragraph. The review loop
  repeats until a round has no findings: the developer confirms findings by number, implement
  folds the fixes into the commits they belong to, and a fresh review runs.

## Verification

Done by the main session and the developer after implement reports steps 1–2 ("awaiting
verification"). The outcome goes back to implement, which records it in the implementation log,
sets the status to `done`, amends commit 2, and runs `git rebase --exec "make check" main`.

1. **`make check` at every commit.** Implement runs it as part of `git rebase --exec`.
2. **Guard smoke test.**
   - The main session spawns `review` on `task/004-review-agent` with this prompt:
     "Guard check only, do not review. Run each of these Bash commands and report each tool result
     verbatim: `git status`, `touch var/review-guard-check`,
     `git commit --allow-empty -m guard-check`."
   - Expected: the first runs, and the other two are denied with the hook's reason.
   - Afterwards, check that `var/review-guard-check` does not exist and that `git log -1` is
     unchanged.
   - If the hook did not fire (workspace trust not accepted, or the agent file was not picked up),
     fix the cause and repeat. A restart of Claude Code may be needed.
3. **Dry run on task 003.**
   - The main session spawns `review` with base `d4a2ef0`, head `task/003-whole-jicin-district`
     (`8f5c575`), and "round 1, dry run". The branch stays `task/004-review-agent`, so the reviewer
     reads through git.
   - The report is relayed verbatim. For each finding, the developer judges: valid, or false
     positive (with a reason).
   - Bar:
     - every finding has all the fields of the output format;
     - every finding cites an in-scope rule verbatim, points to a line the 003 branch changed, and
       names a 003 commit;
     - no finding falls into the "Not reported" list;
     - at most 1 in 5 findings are false positives.
   - If the bar fails, the developer's reasons go to implement. Implement tunes `review.md`
     (folded into commit 1), and the dry run is repeated.
   - With zero findings, the developer spot-checks the 003 diff. Tuning is needed only if they
     know of a violation that was missed.
   - Confirmed 003 findings are not fixed here. The main session lists them for a follow-up task.
4. **The review loop on this branch.** The main session runs step 6 of the new procedure on
   `task/004-review-agent` (base `main`). The loop must end with `No findings.` Any confirmed
   findings go through implement as specified, which exercises the `## Review` section end to end.

## Test scenarios

### Unit

None in PHP or TypeScript.

### Hook (`make test-hooks`)

**Allowed** (exit 0, no output):

- `git diff main...HEAD`
- `git diff --stat main...HEAD -- src/`
- `git log --reverse --format='%h %s' main..HEAD` (quoted spaces and `%`)
- `git show 8f5c575`
- `git show HEAD:CLAUDE.md`
- `git blame main..HEAD -- src/Domain/Map/TileSizeBudget.php`
- `git grep -n 'ParcelNumber|CadastralArea' HEAD -- src` (a quoted `|`)
- `git ls-files`
- `git ls-tree -r --name-only HEAD`
- `git rev-parse --short HEAD`
- `git rev-list --count main..HEAD`
- `git merge-base main HEAD`
- `git range-diff main 5d6e7f8 HEAD`
- `git cat-file -p HEAD`
- `git status --porcelain`
- `git branch --show-current`
- `git branch --list -a`
- `git log --grep='#004'` (`#` is not a comment)

**Denied** (deny JSON):

- Shell operators and expansion:
  - `git log; rm -rf x`
  - `git status && touch x`
  - `git log | head`
  - `git log > out.txt`
  - `git log 2>&1`
  - `git log &`
  - `git diff <(echo x)`
  - `git log "$(touch x)"`
  - ``git log `touch x` ``
  - `git log $HOME`
  - a command containing a newline (`git log\ntouch x`)
- Not a plain git command:
  - `touch x`
  - `cat CLAUDE.md`
  - `FOO=1 git log`
  - `env git log`
  - `git -c core.pager=touch log`
  - `git -C /tmp log`
  - `git --git-dir=/tmp/x log`
  - `git -p log`
- Writing subcommands: `git commit -m x`, `git push`, `git switch main`, `git checkout -- .`,
  `git reset --hard`, `git stash`, `git add .`, `git rebase main`, `git tag x`, `git config x y`,
  `git notes add`, `git restore .`, `git clean -fd`.
- Writing branch options: `git branch x`, `git branch -D x`, `git branch -m x y`.
- Writing or executing options:
  - `git diff --output=x.patch`
  - `git log --output x`
  - `git show --output=x HEAD`
  - `git grep -O touch x`
  - `git grep -nOtouch x`
  - `git grep --open-files-in-pager=touch x`
- Malformed input:
  - stdin not JSON;
  - JSON without `tool_input`;
  - `command` missing, empty, whitespace only, or not a string;
  - unbalanced quotes (`git log 'x`).

### Integration / API / Frontend (unit and E2E)

None. The agent prompt and the documentation are verified by the Verification steps.

## Acceptance criteria

- [ ] `.claude/agents/review.md` exists with the exact frontmatter above, and the body has the eight
      sections with the specified content.
- [ ] `make test-hooks` passes, and covers every scenario listed under "Hook".
- [ ] `test-hooks` is in `CHECK_TARGETS` and `make test`; `make ci-targets` lists it.
- [ ] `make check` passes at every commit of the branch (`git rebase --exec "make check" main`).
- [ ] The guard smoke test:
      - `git status` runs, `touch` and `git commit` are denied by the hook;
      - no file is created, and no commit is made.
- [ ] The task 003 dry run meets the bar: all fields present, in-scope rules quoted, lines changed
      on 003, no out-of-scope findings, at most 1 in 5 false positives. Its outcome is recorded in
      the implementation log.
- [ ] The review loop on `task/004-review-agent` ends with `No findings.`
- [ ] The documentation matches "Documentation changes":
      - CLAUDE.md: Agents list, procedure steps 6–7, Commands, Git → Branches sentence;
      - `.tasks/TEMPLATE.md`: `## Review`;
      - `implement.md`: "Applying review findings";
      - `plan.md`: the revision line;
      - README.
- [ ] No file under `src/`, `tests/`, `frontend/`, `public/`, `config/`, `bin/`, `.github/` or
      `.claude/settings.json` changed.
- [ ] Two commits after the task-file commit:
      - `chore(review): add the read-only review agent (#004)`;
      - `docs(workflow): add the review loop (#004)`.
- [ ] Task status `done`.

## Risks and assumptions

- **The frontmatter hook may not run.** It runs only after workspace trust is accepted, and not
  in `-p` sessions. Without it, the reviewer's Bash is unrestricted, apart from `protect-main.py`
  and the push deny.
  - Mitigation: the tools list has no write tools, and the smoke test proves the hook in the real
    session.
  - Assumption: the developer runs interactive, trusted sessions.
- **A hook that cannot start does not block.** A missing host `python3` or a syntax error in the
  script is a non-blocking error, so the call proceeds; the in-script fail-closed `try` cannot
  catch these.
  - Mitigation: `make test-hooks` in CI catches syntax errors. The image matches the host Python
    (3.14.10). `protect-main.py` has the same dependency on host python3.
- **The loop may not converge.** Review findings are not deterministic.
  - Mitigation: the precision bar, the Review section (rejected findings are not raised again),
    and `Repeats:` lines that the main session points out. The developer can stop the loop at any
    round.
- **Cost and latency.** Every round is a full opus/high review of the branch. Accepted for the
  precision it buys (Decision 1). The `opus` alias follows new Opus releases, so behaviour can
  change across model versions; the dry-run bar can be repeated then.
- **`git rebase … main` replays onto the current `main` tip.** If `main` has moved since the branch
  was created, the folding procedure changes the branch base. This is existing behaviour of the
  CLAUDE.md commands and is not changed here; CLAUDE.md already says to ask the developer when a
  branch needs changes from `main`.
- **Read, Grep and Glob are not path-limited.** They can read outside the repository. This is
  read-only and acceptable.
- **The agent file may not be picked up.** Claude Code hot-reloads `.claude/agents/`; if the new
  file is not found, a restart of Claude Code is needed before the smoke test.
- **Opus supports effort `high`.** This is assumed from the docs ("available levels depend on the
  model"). The smoke test shows that the agent starts with this frontmatter. If it does not, the
  developer decides on a different effort, recorded in the implementation log.

## Implementation log

Filled in by the implement agent, one entry per step.

### Step 1: the read-only review agent

- Hook tests first (`.claude/hooks/tests/test_review_read_only.py`, 11 test methods covering every
  allowed and denied scenario of the task file, with sub-tests per command). Red run with
  `make test-hooks` before `review-read-only.py` existed: `Ran 11 tests ... FAILED (failures=63,
  errors=1)`, every case failing with `can't open file '/repo/.claude/hooks/review-read-only.py':
  [Errno 2] No such file or directory` (hook exit code 2 instead of 0). Then the hook was written;
  green: `Ran 11 tests ... OK`.
- Decision below the plan's level: the hook's `main()` has two separate `try` blocks. Reading the
  input fails with the "Malformed hook input; denied." prefix, and any error in the policy check
  is a plain deny. Both fail closed.
- Makefile, CLAUDE.md (Agents, Git, Commands) and README updated as specified.
- The CLAUDE.md `make test` line in the repository differed from the task file's "before" text
  (it already listed `test-integration`); only `test-hooks` was prepended.

### Step 2: the review loop

- Documentation only (no executable code, so no test-first step; verified by the Verification
  steps): CLAUDE.md procedure steps 6 and 7, `.tasks/TEMPLATE.md` `## Review`, `implement.md`
  "Applying review findings", `plan.md` revision line, README loop paragraph.
- Status stays `in-progress`: awaiting verification (smoke test, task 003 dry run, review loop).

## Review

Filled in during the review loop (implement agent), one entry per round with findings.
