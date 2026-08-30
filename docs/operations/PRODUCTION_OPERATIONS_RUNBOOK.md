# WorkIntel Production Operations Runbook

This runbook extends `docs/PRODUCTION_CHECKLIST.md`, `docs/SCHEMA_RECOVERY_STRATEGY.md`, migration recovery documentation, and the execution/release contracts in `AGENTS.md`. It does not replace them.

Use it for production-readiness review, incident response, rollback/recovery planning, and support diagnosis.

## 1. Operating principles

Priorities during an incident:

`Stabilize → Contain → Preserve Evidence → Diagnose → Recover → Verify → Root Cause → Prevent Recurrence`

Do not perform unrelated feature refactors during active containment.

Stop the affected lane immediately for:

- unexpected data loss/corruption;
- cross-workspace or cross-tenant data exposure;
- credential/token/private-key exposure;
- destructive command with uncertain target;
- migration corruption;
- unexplained massive diff or deployment artifact mismatch;
- critical authorization/security bypass;
- backup/restore evidence that cannot be trusted.

Preserve evidence before destructive cleanup when safe.

## 2. Production identity before action

Before a privileged production action record:

- environment/host/region;
- application URL;
- exact deployed revision/build identifier;
- database target/schema identity;
- release/package checksum where available;
- configuration/feature-flag changes relevant to the event;
- migration state;
- queue/scheduler state;
- incident/change owner;
- start time and timezone.

Never assume the server is running the revision shown in a local checkout.

## 3. Health and service checks

At minimum verify as applicable:

- `/health/live` responds successfully;
- `/health/ready` responds successfully;
- database connectivity;
- cache/session backend if externally hosted;
- queue worker supervision and backlog;
- failed jobs/dead-letter equivalent;
- scheduler heartbeat/expected periodic execution;
- filesystem/object storage read/write path;
- mail/provider connectivity when business-critical;
- webhook/provider callback reachability where applicable;
- browser/desktop release download availability when in scope;
- recent application/security/audit error rate.

A healthy HTTP endpoint does not by itself prove queues, schedulers, database writes, integrations or tenant isolation are healthy.

## 4. Observability and evidence

Capture enough evidence to reproduce/diagnose without leaking sensitive data:

- incident/change ID;
- exact revision;
- correlation/request IDs;
- stable error identity;
- timestamps/timezone;
- affected workspace/user/object IDs only when necessary and appropriately protected;
- sanitized logs;
- failed job identifiers;
- provider request/event IDs;
- migration version/state;
- relevant metrics/alerts;
- commands executed and their outcomes.

Never copy into ordinary tickets/logs:

- passwords;
- API tokens;
- cookies/session secrets;
- raw Authorization headers;
- enrollment/device tokens;
- private signing keys;
- unnecessary screenshot/browser monitoring payloads;
- sensitive personal data not required for diagnosis.

## 5. Backup contract

Before risky schema/data/release operations, know:

- what is backed up;
- backup timestamp;
- target database/storage identity;
- encryption/access policy;
- retention;
- restore procedure;
- restore destination;
- expected RPO/RTO where defined;
- whether the backup has been restore-tested.

`backup created` is not equivalent to `restore verified`.

For high-risk data changes, capture explicit pre-change backup evidence and ensure the recovery path is compatible with the application/schema versions involved.

## 6. Restore procedure contract

A restore plan must identify:

1. reason/incident/change being recovered;
2. exact backup artifact/snapshot;
3. restore target (prefer isolated/disposable verification target first when practical);
4. application/schema version compatibility;
5. secrets/configuration required without embedding them in documentation;
6. expected data-loss window;
7. validation queries/checks;
8. tenant/workspace isolation verification;
9. queue/scheduler behavior during restore;
10. traffic/write freeze requirement;
11. post-restore smoke tests;
12. decision owner for returning traffic.

After restore verify, as applicable:

- schema/migration state;
- critical row counts/invariants;
- authentication;
- authorization/workspace isolation;
- key CRUD workflows;
- payroll/billing/timekeeping invariants if affected;
- queue processing;
- scheduler;
- file/storage references;
- integration credentials/reachability;
- application health endpoints.

Do not call a restore successful merely because the database command completed.

## 7. Rollback/recovery classification

Before high-risk release or migration classify recovery:

- `SIMPLE_ROLLBACK` — application revision can safely revert with schema/config compatibility preserved.
- `ROLLBACK_WITH_COMPATIBILITY` — revert requires compatibility steps, feature flags, or staged schema handling.
- `FORWARD_FIX_PREFERRED` — data/schema side effects make application rollback riskier than a controlled forward repair.
- `IRREVERSIBLE` — operation cannot be safely undone; explicit privileged authorization and stronger backup/recovery controls are required before execution.

For schema evolution prefer `Expand → Migrate → Contract` where practical so old/new application versions can temporarily coexist.

## 8. Incident response flow

### 8.1 Stabilize

- stop the failing rollout/job/action if it is actively worsening impact;
- reduce load or disable a feature only through an authorized reversible mechanism;
- keep authentication/authorization protections intact;
- do not bypass tenant/security gates to restore apparent availability.

### 8.2 Contain

- identify affected environments/workspaces/features/time window;
- isolate compromised credential/integration/device where authorized;
- prevent repeated destructive/replayed effects;
- pause queues/jobs when continued processing is unsafe.

### 8.3 Preserve evidence

- exact revision/build;
- sanitized logs/request IDs;
- database/migration state;
- queue/job IDs;
- provider event IDs;
- relevant alert/metric snapshots;
- commands/actions already taken.

### 8.4 Diagnose

Classify the failure before blind retries:

- source defect;
- configuration;
- migration/data;
- environment/infrastructure;
- dependency/provider;
- authorization/tenant policy;
- queue/scheduler;
- browser/desktop release artifact;
- test/evidence defect;
- authority/state mismatch.

Repeated identical failure without a changed hypothesis triggers the `AGENTS.md` circuit breaker.

### 8.5 Recover

Choose rollback/forward-fix/restore according to the classification above. Preserve data integrity and idempotency; retries must not duplicate irreversible effects.

### 8.6 Verify

Verification must match the failure mode. Consider:

- health endpoints;
- targeted backend tests;
- authorization/tenant isolation;
- database invariants;
- queue/scheduler recovery;
- browser/E2E journey;
- external/provider callback;
- real-target evidence;
- exact deployed revision confirmation.

### 8.7 Root cause and prevention

Record:

- root cause;
- contributing factors;
- detection gap;
- why existing tests/monitoring did or did not catch it;
- corrective change;
- prevention test/control;
- owner/follow-up issue;
- residual known risk.

## 9. Queue/job operations

For material queued/background work define operationally:

- queue name/class;
- expected throughput/latency where relevant;
- timeout;
- retry count/backoff;
- idempotency/deduplication;
- failed-job handling;
- safe replay conditions;
- cancellation/poison-message behavior;
- partial completion recovery;
- observability/alerting.

Never repeatedly replay a failed job without understanding whether its side effects are idempotent.

## 10. Scheduler operations

For business-critical scheduled work know:

- schedule/cadence/timezone;
- overlap/locking behavior;
- missed-run behavior;
- duplicate-run behavior;
- runtime limit;
- failure visibility;
- catch-up/recovery procedure.

Use Laravel route/scheduler certification and repository tests as engineering evidence; validate the real deployment scheduler separately when required.

## 11. Deployment/release readiness

Before traffic or release boundary follow `docs/PRODUCTION_CHECKLIST.md` and record:

- exact revision;
- CI/release evidence for that revision;
- migration plan/status;
- dependency/config changes;
- build/archive integrity;
- required signing/checksums;
- backup/recovery evidence;
- rollback classification;
- post-deploy verification plan.

Distinguish:

- `BUILT` — artifact/build produced;
- `DEPLOYED` — deployed to a target;
- `RELEASED` — intentionally made available to intended users;
- `PRODUCTION_VERIFIED` — critical behavior verified on the production target after release.

Do not collapse these states into one success label.

## 12. Support/troubleshooting packet

A production support escalation should include, where safe:

- environment and exact revision;
- user-visible symptom;
- first/last observed timestamps;
- scope/affected actors;
- reproducibility;
- correlation/error IDs;
- sanitized relevant logs;
- recent deploy/config/migration change;
- queue/scheduler/provider state;
- known workaround;
- data/security impact classification;
- actions already attempted;
- explicit `Not Verified` items.

## 13. Closeout contract

Every material incident/recovery/release checkpoint ends with:

- **Verified:** exact evidence observed for the current target/revision.
- **Not Verified:** evidence not run, unavailable, stale, or belonging to a different target/revision.
- **Known Risk:** unresolved technical/security/data/operational risk.
- **Next Action:** one clear authorized follow-up or blocker.

If required evidence remains missing, status is `PARTIALLY_COMPLETE`/`BLOCKED`, not `DONE`.