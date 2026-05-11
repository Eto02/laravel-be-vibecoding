#!/usr/bin/env bash
# PostToolUse hook for Edit/Write — run Laravel Pint in --test mode on
# modified .php files. Reports style issues without blocking the edit.
#
# Input (stdin): JSON with .tool_name and .tool_input.file_path

set -euo pipefail

input="$(cat)"
tool="$(echo "$input" | jq -r '.tool_name // ""')"
file="$(echo "$input" | jq -r '.tool_input.file_path // ""')"
cwd="$(echo "$input" | jq -r '.cwd // empty')"

# Only act on Edit/Write tools
case "$tool" in
    Edit|Write) ;;
    *) exit 0 ;;
esac

# Trigger only for .php files inside the project's app/, tests/, database/, or config/ directories
if ! echo "$file" | grep -qE '\.php$'; then
    exit 0
fi
if ! echo "$file" | grep -qE '/(app|tests|database|config|routes)/'; then
    exit 0
fi

# Resolve project root
project_root="${cwd:-$(pwd)}"
pint_bin="$project_root/vendor/bin/pint"

if [ ! -x "$pint_bin" ]; then
    # Pint not installed locally; skip silently
    exit 0
fi

# Convert to relative path for nicer output
rel_file="${file#"$project_root/"}"

# Run pint --test on the single file. Don't block on failure, just report.
if output=$(cd "$project_root" && "$pint_bin" --test "$rel_file" 2>&1); then
    # File is clean — no output to keep things quiet
    exit 0
else
    echo "⚠ Pint style issues in $rel_file:" >&2
    echo "$output" | tail -20 >&2
    echo "  Run: vendor/bin/pint $rel_file" >&2
    exit 0  # don't block — just inform
fi
