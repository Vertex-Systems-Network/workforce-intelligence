# WorkIntel Module Specification Standard

This document defines the minimum repository-native specification contract for every substantial new WorkIntel module, major feature, or material workflow change.

It complements `AGENTS.md`. It does not grant product scope by itself. Product implementation still requires an explicit owner-approved repository authority such as an accepted issue/specification.

## 1. Specification state

Every substantial scope must declare exactly one state:

- `DRAFT`
- `SPECIFICATION`
- `AWAITING_DEVELOPMENT_APPROVAL`
- `APPROVED`
- `IMPLEMENTING`
- `VERIFYING`
- `BLOCKED`
- `PARTIALLY_COMPLETE`
- `DONE`

A planning document cannot self-promote from planning to product implementation authority.

## 2. Identity and boundaries

Define:

- stable module/work-package ID;
- module name and business objective;
- actors/personas;
- in-scope behavior;
- explicit non-goals;
- dependencies/prerequisites;
- affected architecture surfaces;
- explicitly unaffected adjacent contracts;
- expected path/change budget;
- owner/authority issue and starting protected-main SHA.

## 3. Interfaces and states

For every affected user-facing surface document, where applicable:

- routes/pages/screens;
- forms and fields;
- tables/lists/cards;
- tabs/filters/search/sort;
- primary/secondary actions;
- bulk actions;
- modals/drawers/dialogs;
- loading/skeleton states;
- empty states;
- validation states;
- success/partial-success states;
- error states;
- disabled/read-only states;
- permission-denied/not-found behavior;
- responsive desktop/tablet/mobile behavior;
- keyboard/focus behavior;
- accessibility names/roles;
- RTL/localization implications.

UI visibility is never authorization. Server-side policy remains authoritative.

## 4. Every meaningful option

Every meaningful input, setting, toggle, checkbox, dropdown, status, permission, preference, automation option, configuration value, or user-selectable behavior must define where relevant:

| Contract | Required detail |
| --- | --- |
| Identity | stable name/key and user-facing label |
| Purpose | business/runtime purpose |
| Type | string/number/boolean/enum/date/reference/etc. |
| Values | allowed values and semantic meaning |
| Default | explicit default or `none` |
| Required | required/optional and conditional rules |
| Validation | format, min/max, normalization, cross-field rules |
| Visibility | where and when exposed |
| Permission | role/capability required to read/change |
| Storage | model/table/config/cache/secret reference |
| API | request/response representation if applicable |
| Runtime | exact behavior when enabled/disabled/changed |
| Dependencies | prerequisites and conflicts |
| Side effects | jobs/events/notifications/data changes |
| Fallback | behavior when dependency/value is unavailable |
| Failure | safe user/operator behavior on error |
| Security/privacy | exposure, tenancy, secrets, abuse implications |
| Tests | happy path, invalid path, denied path, edge cases |

Do not intentionally ship undocumented product options.

## 5. Permissions matrix

For every protected operation define the backend authorization contract.

Minimum operations to consider:

- view/list;
- view detail;
- create;
- update;
- delete/archive/restore;
- approve/reject;
- export/download;
- configure;
- administer;
- impersonate/act-as where applicable;
- bulk operations.

For each operation specify:

- allowed roles/capabilities;
- workspace/tenant ownership constraint;
- object ownership constraint;
- entitlement/module gate;
- exceptional/system actor behavior;
- denied response behavior;
- audit/security-event expectation.

## 6. Data contract

Define where applicable:

- entities and fields;
- data types/defaults/nullability;
- relationships and ownership;
- workspace/tenant scope;
- uniqueness and foreign keys;
- indexes/query access patterns;
- retention and deletion;
- audit/history requirements;
- transaction boundaries;
- duplicate-request/idempotency behavior;
- concurrency/race handling;
- migration and existing-data impact;
- rollback/forward-fix strategy;
- data export/privacy implications.

For money, payroll and timekeeping include rounding, currency, timezone and date-boundary semantics.

## 7. Workflow contract

For every material workflow define:

1. trigger;
2. actor;
3. preconditions;
4. validation;
5. authorization;
6. processing sequence;
7. state transitions;
8. data writes;
9. domain/security/audit events;
10. jobs/queues;
11. notifications/webhooks;
12. success result;
13. partial-success result;
14. failure behavior;
15. timeout/retry policy;
16. idempotency/deduplication;
17. cancellation behavior;
18. crash/restart recovery;
19. concurrent execution behavior;
20. operator/support diagnostics.

## 8. Integrations

For external services/APIs/webhooks define:

- provider/protocol;
- authentication/secret-reference model;
- scopes/permissions;
- endpoint/event contract;
- timeout;
- bounded retries/backoff;
- rate limits;
- idempotency/replay handling;
- signature/verification rules;
- partial failure behavior;
- dependency outage behavior;
- observability;
- data classification and privacy;
- sandbox/real-target verification where required.

## 9. Negative requirements

Every security-, privacy-, billing-, tracking-, authorization- or tenant-sensitive specification must include explicit `MUST NOT` rules.

Examples:

- a workspace admin MUST NOT enumerate another workspace's protected records;
- a retry MUST NOT duplicate an irreversible financial effect;
- a disabled module MUST NOT remain callable through a hidden API route;
- client-side visibility MUST NOT substitute for backend authorization;
- sensitive monitoring payloads MUST NOT appear in ordinary application logs.

Critical negative requirements become automated tests where practical.

## 10. Threat model and failure-mode review

When `AGENTS.md` threat/FMEA triggers apply, document at minimum:

- unauthorized access;
- cross-tenant access;
- privilege escalation;
- IDOR/BOLA/object substitution;
- replay/tampering;
- malformed/oversized input;
- duplicate delivery;
- stale state;
- partial commit;
- dependency outage;
- retry exhaustion;
- operator misuse;
- sensitive-data leakage;
- rollback/recovery failure.

High-severity unresolved risks block release.

## 11. Observability and supportability

Define where relevant:

- stable error identity;
- request/correlation context;
- structured logs;
- audit/security events;
- metrics/health status;
- queue/job visibility;
- alert condition/owner;
- troubleshooting evidence;
- sensitive-data redaction.

Do not claim a control executed merely because a log entry exists.

## 12. Verification plan

Classify required evidence before implementation:

- unit;
- integration;
- database;
- API/contract;
- authorization/tenant isolation;
- migration/upgrade;
- frontend contract;
- browser/E2E;
- accessibility;
- security/static analysis;
- concurrency/idempotency;
- performance/budget;
- recovery/rollback;
- real-target/provider;
- independent review.

Use fast targeted gates during implementation and the repository's exact-head full certification at closure.

## 13. Acceptance and approval boundary

Before implementation, the authority package must summarize:

- scope and non-goals;
- options/interfaces;
- permissions;
- workflows;
- data/migration;
- integrations;
- security/negative requirements;
- verification classes;
- risks;
- change budget;
- implementation sequence;
- Definition of Done.

Substantial new product scope enters `AWAITING_DEVELOPMENT_APPROVAL` until explicitly accepted. Once a scope is approved, normal reversible engineering decisions inside it do not require repeated approval.

Approval must be revisited only for material scope expansion, destructive/irreversible data action, major breaking change, serious security/legal implication, or privileged production action.

## 14. Existing modules

Do not rewrite completed modules merely to satisfy this template.

Backfill specifications when one of these is true:

- the module is about to receive substantial new work;
- the module is security/privacy/financial/tracking critical;
- repository behavior and documentation disagree;
- recurring defects show unclear contracts;
- a release or migration depends on precise behavior.

Repository/code/test evidence outranks retrospective prose.