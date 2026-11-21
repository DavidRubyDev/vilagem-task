"""Black-box tests of the review agent's read-only Bash guard."""

import json
import subprocess
import sys
import unittest
from pathlib import Path

HOOK = Path(__file__).resolve().parent.parent / "review-read-only.py"


def run_hook(stdin: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        [sys.executable, str(HOOK)],
        input=stdin,
        capture_output=True,
        text=True,
    )


def payload(command: object) -> str:
    return json.dumps({
        "hook_event_name": "PreToolUse",
        "tool_name": "Bash",
        "tool_input": {"command": command},
        "cwd": "/repo",
        "agent_type": "review",
    })


class GuardTestCase(unittest.TestCase):
    def assert_allowed(self, command: str) -> None:
        result = run_hook(payload(command))
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertEqual("", result.stdout, command)

    def assert_denied_raw(self, stdin: str, label: str) -> None:
        result = run_hook(stdin)
        self.assertEqual(0, result.returncode, f"{label}: {result.stderr}")
        output = json.loads(result.stdout)["hookSpecificOutput"]
        self.assertEqual("PreToolUse", output["hookEventName"], label)
        self.assertEqual("deny", output["permissionDecision"], label)
        self.assertTrue(output["permissionDecisionReason"], label)

    def assert_denied(self, command: str) -> None:
        self.assert_denied_raw(payload(command), command)


class AllowedCommandsTest(GuardTestCase):
    def test_allows_read_only_git_commands(self) -> None:
        commands = [
            "git diff main...HEAD",
            "git diff --stat main...HEAD -- src/",
            "git log --reverse --format='%h %s' main..HEAD",
            "git show 8f5c575",
            "git show HEAD:CLAUDE.md",
            "git blame main..HEAD -- src/Domain/Map/TileSizeBudget.php",
            "git grep -n 'ParcelNumber|CadastralArea' HEAD -- src",
            "git ls-files",
            "git ls-tree -r --name-only HEAD",
            "git rev-parse --short HEAD",
            "git rev-list --count main..HEAD",
            "git merge-base main HEAD",
            "git range-diff main 5d6e7f8 HEAD",
            "git cat-file -p HEAD",
            "git status --porcelain",
            "git branch --show-current",
            "git branch --list -a",
            "git log --grep='#004'",
        ]
        for command in commands:
            with self.subTest(command=command):
                self.assert_allowed(command)


class DeniedCommandsTest(GuardTestCase):
    def assert_all_denied(self, commands: list[str]) -> None:
        for command in commands:
            with self.subTest(command=command):
                self.assert_denied(command)

    def test_denies_shell_operators_and_expansion(self) -> None:
        self.assert_all_denied([
            "git log; rm -rf x",
            "git status && touch x",
            "git log | head",
            "git log > out.txt",
            "git log 2>&1",
            "git log &",
            "git diff <(echo x)",
            'git log "$(touch x)"',
            "git log `touch x`",
            "git log $HOME",
            "git log\ntouch x",
        ])

    def test_denies_commands_that_are_not_a_plain_git_command(self) -> None:
        self.assert_all_denied([
            "touch x",
            "cat CLAUDE.md",
            "FOO=1 git log",
            "env git log",
            "git -c core.pager=touch log",
            "git -C /tmp log",
            "git --git-dir=/tmp/x log",
            "git -p log",
        ])

    def test_denies_writing_subcommands(self) -> None:
        self.assert_all_denied([
            "git commit -m x",
            "git push",
            "git switch main",
            "git checkout -- .",
            "git reset --hard",
            "git stash",
            "git add .",
            "git rebase main",
            "git tag x",
            "git config x y",
            "git notes add",
            "git restore .",
            "git clean -fd",
        ])

    def test_denies_branch_options_that_write(self) -> None:
        self.assert_all_denied([
            "git branch x",
            "git branch -D x",
            "git branch -m x y",
        ])

    def test_denies_options_that_write_or_execute(self) -> None:
        self.assert_all_denied([
            "git diff --output=x.patch",
            "git log --output x",
            "git show --output=x HEAD",
            "git grep -O touch x",
            "git grep -nOtouch x",
            "git grep --open-files-in-pager=touch x",
        ])

    def test_denies_unbalanced_quotes(self) -> None:
        self.assert_denied("git log 'x")


class MalformedInputTest(GuardTestCase):
    def test_denies_stdin_that_is_not_json(self) -> None:
        self.assert_denied_raw("not json", "not json")

    def test_denies_json_without_tool_input(self) -> None:
        self.assert_denied_raw(json.dumps({"tool_name": "Bash"}), "no tool_input")

    def test_denies_a_missing_empty_blank_or_non_string_command(self) -> None:
        missing = json.dumps({"tool_input": {}})
        self.assert_denied_raw(missing, "missing command")
        for command in ["", "   ", 42, None, ["git", "log"]]:
            with self.subTest(command=command):
                self.assert_denied_raw(payload(command), repr(command))

    def test_malformed_input_reason_starts_with_the_malformed_prefix(self) -> None:
        reason = json.loads(run_hook("not json").stdout)["hookSpecificOutput"][
            "permissionDecisionReason"
        ]
        self.assertTrue(reason.startswith("Malformed hook input; denied. "))


if __name__ == "__main__":
    unittest.main()
