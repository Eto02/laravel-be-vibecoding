---
description: Execute a sprint/module — create GitHub issue, branch from main, implement the FULL module per planning doc, then STOP for user review. Do not push without explicit /push command.
---

Execute the module: $ARGUMENTS

Follow the **Sprint Execution Workflow** from `CLAUDE.md`. Treat the planning doc as the source of truth.

## Pre-flight Checks

1. Verify `planning/{NN}-{module}.md` exists. Fail clearly if not.
2. Verify working tree is clean (`git status` — no uncommitted changes). If dirty, ask user how to proceed.
3. Verify current branch is `main` or ask user before continuing.

## Setup

4. Create GitHub issue via `gh issue create`:
   - Title: `Sprint {N}: {Module Name}`
   - Body: Link to planning doc + 3-5 bullet scope summary
   - Label: none (project doesn't use labels yet)
5. `git checkout main && git pull origin main`
6. Create branch: `git checkout -b feat/sprint-{N}-{module-slug}`
   - Use the number from the planning filename (e.g. `06-order` → `feat/sprint-6-order`)

## Implementation

7. Implement the **FULL module** according to the planning doc — all phases at once.
   - Follow ALL Architecture Rules in CLAUDE.md (thin controllers, fat services, DTOs, ApiResponse, etc.)
   - Follow Naming Conventions strictly
   - Follow Testing Standards (RefreshDatabase, assertJsonStructure, explicit HTTP status)
8. Make **atomic local commits** per logical unit:
   - `feat({domain}): enums + migrations`
   - `feat({domain}): models + factories + DTOs`
   - `feat({domain}): service + events + listeners + jobs`
   - `feat({domain}): controllers + requests + resources + routes`
   - `feat({domain}): feature tests`
   - `feat({domain}): postman + seeder + planning checkboxes`
9. **DO NOT push to remote.**

## Definition of Done (all required before STOP)

10. **Tests**: `docker compose exec app php artisan test` — all tests pass.
11. **Postman**: edit `postman/{NN}-{module}.postman_collection.json` (add all new endpoints with auth, examples, and test scripts) → run `python3 postman/merge.py`.
12. **Planning doc**: open `planning/{NN}-{module}.md`, tick `⬜` → `✅` for completed items, set status to `✅ Selesai`.
13. **DevSeeder**: if there are new entities, add sample data to `database/seeders/DevSeeder.php` for `test@example.com`.

## Stop and Self-Review

14. Emit **Self-Review Report** in the exact format defined in CLAUDE.md section "Self-Review Report Format":
    - Files Changed (categorized)
    - Tests (pass/fail/skipped + duration)
    - New Endpoints in Postman
    - Potential Issues / Known Gaps (be honest — flag race conditions, stubs, untested paths)
    - Pending Dependencies for Future Sprints
    - Done Criteria Status checklist
15. **STOP.** Wait for user to run `/push`.

## Rules

- **No push, no PR, no merge** without explicit user command.
- If a bug is found during execution, fix it inline in the same branch (no separate branch).
- If `main` advances during work, rebase the feature branch and report any conflicts that need user decision.
- If tests fail, fix the underlying issue — do not skip or disable tests.
