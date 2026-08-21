# WorkIntel Repository Governance

Updated: 2026-08-22

## Purpose

This document defines the repository-side governance contract for WorkIntel. Repository governance is intentionally separate from application maturity and release certification.

## Branch policy

WorkIntel uses pull-request-first development as a repository policy:

- routine changes should be made on focused feature/fix branches;
- pull requests should remain scoped to one coherent change;
- applicable tests and certification should be reviewed before merge;
- `main` history must not be rewritten;
- force pushes to `main` and deletion of `main` are prohibited by policy;
- accidental direct writes should be repaired with normal forward commits so audit history remains intact.

GitHub technical branch-protection rules are **not part of the active repository governance scope** as of 2026-08-22 by explicit repository-owner decision. No branch-protection operator, required-rule contract, or branch-protection completion gate is maintained by this repository.

`CODEOWNERS` remains informational ownership metadata so GitHub can request the repository owner on changes. It is not coupled to mandatory branch-protection enforcement.

## Pull-request-first development

Routine repository changes should be made on focused branches and merged through pull requests after applicable validation. The absence of GitHub branch-protection rules does not authorize history rewrites, destructive branch operations, unrelated changes, or bypassing review discipline.

If an accidental direct write occurs, preserve auditability: repair it with a normal forward commit instead of rewriting branch history, and document the event when material.

## Validation

The repository retains its automated validation surfaces, including:

- WorkIntel CI job `test` where GitHub Actions capacity is available;
- WorkIntel Windows Certification job `windows-certification` on matching self-hosted Windows runners;
- PHPUnit, migrations/seeds, production/final doctors, responsive E2E, accessibility and cross-browser certification where applicable.

A quota/capacity failure must never be relabeled as a passing run. Historical successful certification evidence remains valid evidence for the commits on which it executed.

## Release-scope boundaries

Active modular maturity is 100% under the release scope recorded in `docs/architecture/MODULAR_MATURITY_STATUS.md`.

Physical Laragon + MySQL acceptance was explicitly withdrawn from the active release scope on 2026-08-22. The Laragon verifier may remain as an optional diagnostic, but it is not a release-completion or repository-governance gate.

Repository governance is complete under this documented policy with no branch-protection requirement.
