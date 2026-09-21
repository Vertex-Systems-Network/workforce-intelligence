# Runner Benchmark Register

The canonical machine-readable register is `benchmarks/runner/registry.json` (schema v2).

Every material remote/container/browser/runtime/full-regression/performance workload records source Issue/PR/work package, exact source identity, command/workflow, environment/matrix/input/fixture identity, authorization, security-critical and merge-blocking classification, expected runner-time budget, deterministic `dedup_key`, status, and immutable terminal evidence.

Registration is **not** execution authority. Consumed, expired, historical, destructive, provider, production, deployment, release, or formal-runtime authorization is never inferred or silently reused.

Safe non-blocking work defaults to `final-runner-batch`. Security-critical validation, exact-head merge-required checks, migration/auth/secrets/data-safety checks, current-change integration-safety checks, and incident/recovery checks are `immediate` when the active milestone requires them. Immediate work without current authority is `blocked`.

Before execution: reconcile compact state/main/Issues/PRs; verify the deterministic `dedup_key`; set exact candidate SHA; confirm current authority; persist `VERIFYING`/`WAITING_EXTERNAL` where needed. By default use one consolidated remote CI/status refresh and never tight-poll.

Terminal PASS/FAIL requires exact candidate SHA, timestamp, and immutable evidence. A head move makes previous evidence historical. An older green SHA is historical evidence only.

Run `npm run audit:runner-benchmarks` after registry changes.
