#!/usr/bin/env python3
"""PreToolUse hook: deny git commands that create or rewrite commits while main is checked out."""

import json
import os
import re
import subprocess
import sys

PROTECTED = {"main", "master"}
WRITING = re.compile(
    r"\bgit(?:\s+-[Cc]\s+\S+)*\s+(commit|merge|cherry-pick|revert|am|rebase)\b"
)

payload = json.load(sys.stdin)
command = payload.get("tool_input", {}).get("command", "")
if not WRITING.search(command):
    sys.exit(0)

cwd = payload.get("cwd") or os.environ.get("CLAUDE_PROJECT_DIR", ".")
branch = subprocess.run(
    ["git", "-C", cwd, "branch", "--show-current"],
    capture_output=True,
    text=True,
).stdout.strip()

if branch in PROTECTED:
    print(json.dumps({
        "hookSpecificOutput": {
            "hookEventName": "PreToolUse",
            "permissionDecision": "deny",
            "permissionDecisionReason": (
                f"Committing on '{branch}' is not allowed (see CLAUDE.md, Git). "
                "Switch to the task branch in a separate command first."
            ),
        }
    }))
