# WorkIntel Repository Governance

Updated: 2026-08-21

## Purpose

This document defines the repository-side governance contract for WorkIntel. It is intentionally separate from application maturity: repository settings cannot be represented as complete merely because source files describe the desired policy.

## Main branch protection contract

The `main` branch must be protected with at least the following controls:

- require a pull request before merging;
- require at least one approving review;
- dismiss stale approvals when new commits are pushed;
- require conversation resolution before merging;
- require status checks to pass before merging;
- require the branch to be up to date before merging;
- require status check `test` from WorkIntel CI;
- require status check `windows-certification` from WorkIntel Windows Certification;
- enforce the rule for administrators for routine changes;
- disallow force pushes;
- disallow deletion of `main`;
- do not require linear history while certified merge commits remain the repository's accepted merge strategy.

`CODEOWNERS` requests repository-owner review. Code-owner review may be made mandatory only when the repository has enough eligible reviewers to avoid creating an unrecoverable single-maintainer merge deadlock.

## Current enforcement state

At the time this document was introduced, GitHub's branch API reported `main` as `protected: false`. Issue #8 is the canonical external repository-setting gate. This document, CODEOWNERS, CONTRIBUTING, SECURITY policy and pull-request template improve governance but do not substitute for the actual GitHub branch-protection setting.

Issue #8 may close only after the branch API reports `protected: true` and the enforced required checks include `test` and `windows-certification`.

## Pull-request-first development

Routine repository changes must be made on focused branches and merged through pull requests after applicable certification passes. Direct writes to `main`, history rewrites, and force pushes are governance violations even while GitHub's technical protection setting remains pending.

If an accidental direct write occurs before protection is enabled, preserve auditability: repair it with a normal forward commit instead of rewriting branch history, and document the event when material.

## Certification boundaries

Hosted Linux/MySQL and Windows browser certification prove hosted release dimensions. They do not prove the combined physical Laragon Windows + configured MySQL environment. Physical target-runtime acceptance remains governed by Issue #6 and `verify-laragon-release.cmd`.

Repository governance completion and physical runtime certification are independent gates; neither should be used to bypass the other.
