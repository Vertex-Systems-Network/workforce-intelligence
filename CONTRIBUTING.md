# Contributing to WorkIntel

WorkIntel uses pull-request-first delivery for routine repository changes.

## Branch policy

- Do not make routine direct commits to `main`.
- Create a focused feature/fix branch from the current `main` HEAD.
- Keep each pull request scoped to one coherent change.
- Do not force-push or rewrite `main` history.
- Do not delete `main`.

GitHub technical branch-protection rules are not an active repository requirement. The policy above remains the expected contribution discipline.

## Required validation

Before merge, run and review the applicable repository certification workflows when execution capacity is available, including:

- WorkIntel CI job: `test`
- WorkIntel Windows Certification job: `windows-certification`

Changes that affect runtime, database, browser behavior, release verification, security, accessibility, or architecture must preserve the relevant existing doctors, audits, tests, migration/seed guarantees, and browser certification gates.

A GitHub Actions quota/capacity failure must not be described as a passing run.

## Database safety

- Production/live verification must be non-destructive.
- Never use `migrate:fresh` or `verify-clean-install.cmd` against a live database.
- Physical Laragon + MySQL acceptance is not part of the active release scope; any Laragon verifier that remains in the repository is optional diagnostic tooling only.

## Pull requests

A pull request should explain:

1. what changed;
2. why the change is required;
3. relevant safety or migration impact;
4. the tests/certification evidence used to validate it;
5. any remaining external or environment-specific limitation.

Do not claim evidence that was not actually executed.
