#!/usr/bin/env bash
# PostToolUse hook for Edit/Write — auto-run merge.py when a Postman domain
# collection file is modified. Keeps marketplace_api.postman_collection.json
# in sync with the per-domain files automatically.
#
# Input (stdin): JSON with .tool_name and .tool_input.file_path

set -euo pipefail

input="$(cat)"
tool="$(echo "$input" | jq -r '.tool_name // ""')"
file="$(echo "$input" | jq -r '.tool_input.file_path // ""')"
cwd="$(echo "$input" | jq -r '.cwd // empty')"

# Only act on Edit/Write tools
case "$tool" in
    Edit|Write|NotebookEdit) ;;
    *) exit 0 ;;
esac

# Trigger only when a per-domain Postman file is modified
# (skip the master file itself to avoid infinite loops)
if ! echo "$file" | grep -qE '/postman/[0-9]{2}-[a-z-]+\.postman_collection\.json$'; then
    exit 0
fi

# Make sure project has the merge script
script="${cwd:-$(pwd)}/postman/merge.py"
if [ ! -f "$script" ]; then
    exit 0
fi

# Run merge.py — emit a friendly note. Don't fail the hook on script error.
if (cd "${cwd:-$(pwd)}" && python3 postman/merge.py >/dev/null 2>&1); then
    echo "✓ Auto-regenerated postman/marketplace_api.postman_collection.json" >&2
else
    echo "⚠ postman/merge.py failed — run it manually to regenerate the master collection" >&2
fi

exit 0
