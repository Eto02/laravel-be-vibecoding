---
description: Create a Pull Request from the current branch to main using gh CLI. Generates title and body from commits and diff.
---

Create a Pull Request for the current branch.

## Pre-flight Checks

1. Verify current branch is **not** `main`.
2. Verify the branch is already pushed to remote (`git ls-remote --heads origin {current-branch}`). If not, ask user to run `/push` first.
3. Check if a PR already exists for this branch (`gh pr list --head {current-branch}`). If yes, return its URL and stop.

## Gather Context

4. Run in parallel:
   - `git log main..HEAD --oneline` — commits ahead of main
   - `git diff main..HEAD --stat` — file change stats
   - `git diff main..HEAD --name-only` — full file list
5. Analyze ALL commits (not just the latest) to understand the full scope of the PR.

## Draft PR Body

6. Title: `feat({domain}): Sprint {N} — {Module Name}` for sprint work, or `chore({scope}): {short description}` for meta-changes.
7. Body sections (skip empty ones):
   - **Summary** — 3-5 bullets on what was added/changed
   - **Bug Fixes** — list any bugs fixed during execution
   - **Endpoints** — list new endpoints if applicable
   - **Test Plan** — markdown checkbox list (tests pass, Postman updated, etc.)
   - **Notes** — pending dependencies for future sprints, stubs that need activation

## Create PR

8. Run `gh pr create --base main --head {current-branch}` with title and body via HEREDOC for correct formatting.
9. Return the PR URL to the user.

## Rules

- Use HEREDOC for the body to preserve newlines and markdown formatting.
- Title under 70 characters — use the body for details.
- Don't push from this command — `/push` should already have been run.
- After creating PR, suggest user run `/ultrareview {PR}` manually if they want automated multi-agent review.
- Stop after creating PR — wait for `/merge-ok` before merging.
