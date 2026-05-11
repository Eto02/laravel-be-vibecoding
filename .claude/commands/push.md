---
description: Push current branch to remote. Verifies branch is not main and reports the URL for creating a PR.
---

Push the current branch to remote.

## Steps

1. Verify current branch is **not** `main` (`git branch --show-current`). If on main, refuse and explain.
2. Run `git push -u origin {current-branch}`.
3. Report the push result and the GitHub URL for creating a PR (shown in the push output).

## Notes

- This command only pushes — it does NOT create a PR. Run `/pr` next to create the PR.
- If the branch already has an upstream, plain `git push` is sufficient.
- If the push fails due to non-fast-forward (remote has new commits), inform the user and ask whether to rebase or force-push (never force-push without explicit permission).
