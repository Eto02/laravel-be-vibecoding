---
description: User has reviewed and approved the PR — merge to main with squash and delete the branch. Also updates project_state.md memory.
---

User has approved the PR. Merge it to main: $ARGUMENTS

## Identify PR Number

1. If `$ARGUMENTS` provides a PR number, use that.
2. Otherwise, run `gh pr list --head $(git branch --show-current) --json number,title,state --jq '.[0]'` to get the PR for the current branch.
3. Fail clearly if no PR is found or if the PR is not in `OPEN` state.

## Pre-merge Checks

4. Verify PR is mergeable: `gh pr view {N} --json mergeable,state`
   - If `CONFLICTING`, report conflict and stop.
   - If `state` is not `OPEN`, stop (already merged or closed).
5. Optionally check CI status: `gh pr checks {N}` — warn user if checks are failing but proceed if user explicitly invoked `/merge-ok` anyway.

## Merge

6. Run `gh pr merge {N} --squash --delete-branch`.
7. Switch back to main: `git checkout main && git pull origin main`.
8. Confirm merge in logs: `git log --oneline -3`.

## Update Memory

9. Update `memory/project_state.md` (if Sprint work):
   - Mark the sprint as `COMPLETE ✅ MERGED`
   - Update "Next" pointer to the next sprint
   - Note any pending dependencies discovered during review

## Report

10. Return summary:
    - PR # and title that was merged
    - Commit SHA on main after merge
    - Branch deleted (yes/no)
    - Next suggested step

## Rules

- **Always squash** — keep main history clean.
- **Always delete branch** — no stale feature branches.
- **Never force-merge** — if CI/checks fail and user wants to proceed anyway, require explicit confirmation.
- Never bypass approval — `/merge-ok` IS the approval; never auto-merge from `/pr` or anywhere else.
