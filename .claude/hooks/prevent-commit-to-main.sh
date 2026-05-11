#!/usr/bin/env bash
# PreToolUse hook for Bash — block `git commit` while on `main` branch.
# Per Sprint Execution Workflow: all changes go through a feature/chore branch.
#
# Input (stdin): JSON with .tool_input.command and .cwd

set -euo pipefail

input="$(cat)"
cmd="$(echo "$input" | jq -r '.tool_input.command // ""')"
cwd="$(echo "$input" | jq -r '.cwd // empty')"

# Only check if command starts with `git commit` (allow `git commit-tree` etc. through)
if ! echo "$cmd" | grep -qE '(^|[^[:alnum:]])git[[:space:]]+commit([[:space:]]|$)'; then
    exit 0
fi

# Allow `git commit --amend` only on non-main branches too — same rule applies
branch="$(git ${cwd:+-C "$cwd"} branch --show-current 2>/dev/null || echo "")"

if [ "$branch" = "main" ]; then
    cat <<EOF >&2
BLOCKED: cannot commit directly to main.

Per Sprint Execution Workflow (CLAUDE.md), all changes must go through a feature/chore branch:
  git checkout -b feat/sprint-N-{module}    # for sprint work
  git checkout -b chore/{description}       # for meta-changes
  git checkout -b fix/{description}         # for bug fixes

Then re-run the commit.
EOF
    exit 2
fi

exit 0
