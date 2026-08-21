# Contributing to WorkIntel

WorkIntel uses pull-request-first delivery for all repository changes.

## Branch policy

- Do not make routine direct commits to `main`.
- Create a focused feature/fix branch from the current `main` HEAD.
- Keep each pull request scoped to one coherent change.
- Do not force-push or rewrite `main` history.
- Do not delete `main`.

GitHub branch protection is tracked in Issue #8. Until that repository setting is enabled, this file is policy documentation rather than a substitute for enforcement.

## Required validation

Before merge, the pull-request head must pass the repository certification workflows, including:

- WorkIntel CI job: `test`
- WorkIntel Windows Certification job: `windows-certification`

Changes that affect runtime, database, browser behavior, release verification, security, accessibility, or architecture must preserve the relevant existing doctors, audits, tests, migration/seed guarantees, and browser certification gates.

## Database safety

- Production/live verification must be non-destructive.
- Never use `migrate:fresh` or `verify-clean-install.cmd` against a live workstation database.
- The final Laragon release verifier is `verify-laragon-release.cmd` and its acceptance process is tracked in Issue #6.

## Pull requests

A pull request should explain:

1. what changed;
2. why the change is required;
3. relevant safety or migration impact;
4. the tests/certification evidence used to validate it;
5. any remaining external or physical acceptance gate.

Do not label hosted CI as proof of a physical target-runtime condition that hosted runners cannot actually prove.
