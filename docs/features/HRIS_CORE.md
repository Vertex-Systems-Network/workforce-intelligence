# Phase 18 — HRIS Core

Phase 18 turns the People area into an employee-record system without giving every manager access to sensitive HR data.

## Employee profile & lifecycle

Employment stages:

`preboarding → onboarding → probation → active → leave / notice → terminated → alumni`

Every HR-managed stage change writes an immutable `employment_history` record with effective date, previous value, new value, note and actor.

## Documents

- employee folders
- private file storage
- metadata + SHA-256 file hash
- expiry date tracking
- employee self upload
- HR/Admin sensitive-document access
- secure authenticated download

Manager/team HRIS visibility does **not** automatically grant access to private documents.

## Contracts

Contracts use immutable version history. A new version points to the previous version. Activating a contract supersedes the previously active contract instead of rewriting it.

## Personal HR data

- emergency contacts
- dependents
- workspace-defined custom employee fields
- self/team/HR visibility levels for custom fields

## Lifecycle checklists

Reusable templates support:

- onboarding
- offboarding
- probation
- promotion
- role change

Checklist tasks can be owned by employee, manager, HR, IT or payroll. Due dates are generated relative to the lifecycle effective date.

## Assets

- asset inventory
- asset tags / serial numbers
- purchase and warranty information
- issue to employee
- expected return date
- condition out / condition in
- return history

An asset cannot have two active assignments at the same time.

## Policies

Company policies are versioned. Publishing a new version supersedes the previous published version. Employee acknowledgement records store:

- typed signed name
- timestamp
- IP address
- user agent
- SHA-256 hash of the exact policy content acknowledged

This is an acknowledgement/evidence workflow, not a qualified cryptographic digital-signature service.

## Permissions

- `hris.view_own`
- `hris.view_team`
- `hris.view_all`
- `hris.manage`
- `hris.documents.manage`
- `hris.assets.manage`
- `hris.policies.manage`
- `hris.lifecycle.manage`

Defaults:

- Owner/Admin: full HRIS
- HR: full HRIS
- Manager/Team Lead: self + direct-report HR summary
- Payroll Manager: own HR profile
- Employee: own HR profile

Private documents, contracts, dependents and emergency contacts are limited to employee-self or HRIS full-management/sensitive access.

## Install

Extract the Phase 18 patch over the Phase 17 project, then run:

```bat
verify-phase18-upgrade.cmd
```

The integration patcher is idempotent and backs up modified integration files as `*.phase18.bak`.

For a disposable database only:

```bat
verify-phase18-zero.cmd
```

Do not use the zero-base script on a database whose data must be preserved.
