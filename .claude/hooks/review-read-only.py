#!/usr/bin/env python3
"""PreToolUse hook for the review agent: allow only read-only git commands, deny everything else.

Fails closed: any error while deciding is a deny. An allowed command produces no output
("no decision"), so the normal permission flow still applies; this hook only ever denies.
"""

import json
import re
import shlex
import sys

SUBCOMMANDS = {
    "diff", "log", "show", "blame", "grep", "ls-files", "ls-tree", "rev-parse",
    "rev-list", "merge-base", "range-diff", "cat-file", "status", "branch",
}
BRANCH_OPTIONS = {"--show-current", "--list", "-a", "--all", "-r", "-v", "-vv"}
FORBIDDEN_CHARACTERS = re.compile(r"[$`\n\r]")
OPERATOR_TOKEN = re.compile(r"^[();<>|&]+$")

REASON = (
    "The review agent is read-only. Allowed: one git command without shell operators: "
    "git <subcommand> … with <subcommand> one of diff, log, show, blame, grep, ls-files, "
    "ls-tree, rev-parse, rev-list, merge-base, range-diff, cat-file, status, branch "
    "(--show-current, --list, -a, --all, -r, -v, -vv only)."
)
MALFORMED_PREFIX = "Malformed hook input; denied. "


class Malformed(Exception):
    pass


def read_command() -> str:
    payload = json.load(sys.stdin)
    command = payload["tool_input"]["command"]
    if not isinstance(command, str) or not command.strip():
        raise Malformed
    return command


def tokenize(command: str) -> list[str]:
    lexer = shlex.shlex(command, posix=True, punctuation_chars=True)
    lexer.whitespace_split = True
    lexer.commenters = ""
    return list(lexer)


def is_allowed(command: str) -> bool:
    if FORBIDDEN_CHARACTERS.search(command):
        return False
    tokens = tokenize(command)
    if any(OPERATOR_TOKEN.match(token) for token in tokens):
        return False
    if len(tokens) < 2 or tokens[0] != "git" or tokens[1] not in SUBCOMMANDS:
        return False
    subcommand, arguments = tokens[1], tokens[2:]
    if any(argument.startswith("--output") for argument in arguments):
        return False
    if subcommand == "grep" and any(runs_pager(argument) for argument in arguments):
        return False
    if subcommand == "branch" and any(a not in BRANCH_OPTIONS for a in arguments):
        return False
    return True


def runs_pager(argument: str) -> bool:
    if argument.startswith("--open-files-in-pager"):
        return True
    return argument.startswith("-") and not argument.startswith("--") and "O" in argument


def deny(reason: str) -> None:
    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": reason,
        }
    }))


def main() -> None:
    try:
        command = read_command()
    except Exception:
        deny(MALFORMED_PREFIX + REASON)
        return
    try:
        allowed = is_allowed(command)
    except Exception:
        allowed = False
    if not allowed:
        deny(REASON)


main()
