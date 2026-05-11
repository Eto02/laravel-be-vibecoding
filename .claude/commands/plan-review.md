---
description: Review and discuss the planning document for a module/sprint. Read-only — no code changes, no git operations.
---

Review the planning document for: $ARGUMENTS

## Steps

1. Locate the planning file at `planning/{NN}-{module}.md` based on the argument.
   - Argument format: `06-order`, `7-payment`, or `sprint-7`
   - Fail with a clear message if the file doesn't exist.
2. Read the **full** planning document.
3. Summarize for the user:
   - **Scope**: what's being built (1-2 sentences)
   - **Entities**: tables + key columns
   - **Endpoints**: route list
   - **Dependencies**: services that must already exist (mis: `OrderService`, `EmailService`)
   - **State machine / Business rules**: if any
4. Flag any **ambiguities, contradictions, or gaps**:
   - Missing field types or migration column specs
   - Unclear error scenarios
   - Implicit assumptions about other sprints
   - Conflict between sections of the same planning doc
5. Ask clarifying questions if needed — use `AskUserQuestion` for binary/multi-choice questions.

## Rules

- **DO NOT modify any code files.**
- **DO NOT create branches, issues, or commits.**
- **DO NOT run tests or seeders.**
- This is a planning discussion only.

Execution starts only when user invokes `/execute {module}`.
