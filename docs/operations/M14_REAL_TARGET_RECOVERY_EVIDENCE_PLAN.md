# M14 Real-Target and Recovery Evidence Plan

**Repository:** `Vertex-Systems-Network/workforce-intelligence`  
**Prepared against protected main:** `0e6262ddbf32ce23f3d7ee3f8b9886ea7e95da1c`  
**Prepared:** 2026-09-26 20:10 Asia/Karachi  
**Active scope:** Windows/Linux M14 release trust. Apple/macOS remains deferred to Issue #123.  
**Execution status:** PREPARED ONLY — no production deployment, restore, traffic mutation, destructive database action, trusted publication, or Runner task is authorized by this document.

## Purpose

This plan converts the final M14 real-target and recovery gate into a deterministic evidence packet. It defines exactly what must be observed before the active Windows/Linux release scope can truthfully advance from source/release readiness to `PRODUCTION_VERIFIED`.

It supplements:
- `docs/PRODUCTION_CHECKLIST.md`
- `docs/operations/PRODUCTION_OPERATIONS_RUNBOOK.md`
- `docs/SCHEMA_RECOVERY_STRATEGY.md`
- M14 release-trust contracts and Issue #62.

## Truth states

Do not collapse these states:

- `BUILT` — deterministic build exists.
- `HASH_VERIFIED` — final artifact digest/provenance matches.
- `SIGNED` — Windows signature is actually applied and verified.
- `RELEASED` — intended immutable distribution assets are actually public.
- `DEPLOYED` — exact release/revision is placed on a named real target.
- `RESTORE_VERIFIED` — a named backup is successfully restored on an isolated/disposable target and validated.
- `PRODUCTION_VERIFIED` — exact released revision/artifact is verified on the stated real target with the required runtime and recovery evidence.

Green CI or a merged PR proves none of the final three states by itself.

## Entry conditions

Real-target execution must not start until the authorized active-scope release candidate is known and all applicable upstream gates are satisfied.

Required preconditions:

1. exact protected-main source SHA recorded;
2. exact immutable release/tag/version recorded;
3. final Windows/Linux artifact SHA-256 values recorded;
4. Windows public-trust signer/timestamp evidence available when Windows enterprise distribution is in scope;
5. Linux final provenance/digest evidence available;
6. intended deployment target explicitly named;
7. production configuration owner identified;
8. backup source and restore destination explicitly identified;
9. destructive/privileged production and restore actions separately authorized;
10. rollback/recovery classification recorded;
11. Apple/macOS remains excluded while Issue #123 is deferred.

If any required precondition is missing, mark `NOT VERIFIED` and stop rather than substituting source/CI evidence.

## Evidence packet identity

Every real-target evidence packet must record:

- evidence packet ID;
- repository;
- active platform scope;
- exact protected-main SHA;
- release tag/version;
- immutable release URL/ID when published;
- Windows artifact filename + SHA-256;
- Linux artifact filename + SHA-256;
- target environment name;
- target host/service identifier;
- region/location where applicable;
- application URL;
- operator/auditor identity;
- execution start/end timestamp with timezone;
- change/incident/ticket reference;
- evidence status: `PARTIALLY_COMPLETE`, `BLOCKED`, or `PRODUCTION_VERIFIED`.

Never store passwords, tokens, raw Authorization headers, private signing keys, cookies, P12/P8 data, or unnecessary personal data in the evidence packet.

## Phase A — pre-deploy target identity

Before mutation, capture:

- current deployed revision/build;
- target database identity/schema;
- current migration state;
- queue/scheduler state;
- storage backend identity;
- configuration/feature-flag delta relevant to the release;
- current health endpoints;
- current release/download path;
- pre-change backup reference;
- backup timestamp, retention and encryption/access posture;
- rollback classification:
  - `SIMPLE_ROLLBACK`
  - `ROLLBACK_WITH_COMPATIBILITY`
  - `FORWARD_FIX_PREFERRED`
  - `IRREVERSIBLE`.

An uncertain target or database identity is a hard stop.

## Phase B — deploy evidence

When separately authorized, record:

- exact artifact/revision deployed;
- deployment command/process identity without secrets;
- migration command/result;
- application optimize/cache result where applicable;
- deployment start/end time;
- exit/result status;
- any controlled compatibility steps;
- no unrelated feature/data changes.

A successful deploy command yields `DEPLOYED`, not `PRODUCTION_VERIFIED`.

## Phase C — runtime health evidence

Verify on the actual target:

- `/health/live` returns success;
- `/health/ready` returns success;
- database connectivity;
- expected migration state;
- queue worker supervision;
- queue backlog/failed-job state;
- scheduler health/expected execution;
- storage read/write;
- cache/session backend when applicable;
- mail/provider connectivity when business-critical;
- release/download availability;
- recent application/security/audit error state.

Record command/check name, timestamp, sanitized result, and target identity for every item.

## Phase D — application trust smoke

Verify the exact deployed revision through the smallest safe representative smoke set:

- authentication succeeds for an authorized test account;
- authorization remains fail-closed;
- workspace/tenant isolation remains intact;
- one critical read flow;
- one safe reversible write flow where authorized;
- one critical browser journey where applicable;
- release artifact download/checksum path;
- no cross-workspace data exposure;
- no security gate bypass.

Use disposable/test identities and data where practical. Never create irreversible customer-impacting test data merely for certification.

## Phase E — backup-to-restore verification

`backup created` is not `RESTORE_VERIFIED`.

The preferred proof is an isolated/disposable restore target.

Before restore record:

- exact backup artifact/snapshot identifier;
- backup creation time;
- source database/storage identity;
- restore target identity;
- application/schema compatibility;
- expected data-loss window;
- write/traffic isolation;
- validation queries/invariants.

During restore:

- restore only to the explicitly approved isolated/disposable target unless stronger authorization states otherwise;
- record command/process outcome without secrets;
- preserve failure evidence;
- do not retry destructive steps blindly.

After restore verify:

- schema/migration state;
- critical row counts/invariants;
- authentication;
- authorization/workspace isolation;
- representative CRUD;
- queue behavior;
- scheduler behavior;
- storage references;
- health endpoints;
- target is still isolated from real production traffic.

Only then may the backup be labeled `RESTORE_VERIFIED`.

## Phase F — rollback/recovery evidence

For the selected rollback classification record:

- trigger condition;
- exact rollback/forward-fix target;
- data/schema compatibility;
- whether traffic/write freeze is required;
- expected operator steps;
- post-recovery checks;
- residual known risk.

Do not perform a rollback just to produce evidence if a safe isolated verification method is available.

## Phase G — final evidence decision

`PRODUCTION_VERIFIED` requires all applicable active-scope evidence to refer to the same exact release/revision and target.

Minimum final assertions:

- immutable release identity is known;
- deployed revision/artifact matches that release;
- required Windows/Linux trust evidence matches the distributed bytes;
- health endpoints pass;
- database/migrations are healthy;
- queues and scheduler are healthy;
- storage read/write passes;
- critical auth/workspace-isolation smoke passes;
- release/download path works;
- backup-to-restore verification passes on an isolated/disposable target;
- no material security/data blocker remains unresolved.

If any assertion is missing, stale, from another revision/target, or based only on expectation, final status remains `PARTIALLY_COMPLETE` or `BLOCKED`.

## Evidence record template

Use this structure for the future executed packet:

```text
Evidence Packet ID:
Execution Date/Timezone:
Repository:
Protected Main SHA:
Release Tag/Version:
Immutable Release ID/URL:
Windows Artifact + SHA256:
Linux Artifact + SHA256:
Target Environment:
Target Host/Service:
Application URL:
Database Target:
Operator/Auditor:

Pre-deploy identity: VERIFIED / NOT VERIFIED
Deployment: VERIFIED / NOT VERIFIED
Health live: VERIFIED / NOT VERIFIED
Health ready: VERIFIED / NOT VERIFIED
Database/migrations: VERIFIED / NOT VERIFIED
Queue supervision/backlog: VERIFIED / NOT VERIFIED
Scheduler: VERIFIED / NOT VERIFIED
Storage read/write: VERIFIED / NOT VERIFIED
Auth smoke: VERIFIED / NOT VERIFIED
Workspace isolation: VERIFIED / NOT VERIFIED
Critical browser journey: VERIFIED / NOT VERIFIED
Release download/digest: VERIFIED / NOT VERIFIED
Backup identified: VERIFIED / NOT VERIFIED
Isolated restore: VERIFIED / NOT VERIFIED
Post-restore invariants: VERIFIED / NOT VERIFIED
Rollback classification:
Residual Known Risk:

Final State: PARTIALLY_COMPLETE / BLOCKED / PRODUCTION_VERIFIED
Evidence References:
Next Action:
```

## Safety / authority boundary

This preparation milestone authorizes no:

- production deployment;
- production migration;
- traffic switch;
- production write test;
- database restore;
- destructive database/storage action;
- provider purchase;
- Windows signing;
- trusted release publication;
- Apple/macOS work;
- Runner Benchmark execution.

Those actions require the authority already defined by repository governance and the applicable external gate.

## Current conclusion

The real-target/recovery lane is now source-prepared for the active Windows/Linux scope. No runtime evidence has been executed, so no progress percentage is advanced and M14 remains at 70%.

The next active external dependency remains the written SSL.com/DigiCert Windows qualification response. While waiting, this plan can be used to collect target-specific non-secret facts without executing production or restore actions.
