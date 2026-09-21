# Runner Benchmark Register

## Source definitions vs exact-head runtime results

`benchmarks/runner/registry.json` is the committed schema-v3 **task-definition registry**. It records material remote/container/browser/runtime/full-regression/performance obligations, authority requirements, execution class, safety/merge classifications, environment identity, expected runner-time budget, and deterministic dedup template.

The committed registry is intentionally **not** the exact-head PASS/FAIL ledger. Committing a terminal result into the same candidate source branch would change the candidate SHA and invalidate the head it claims to certify.

Exact-head runtime results use the machine-readable envelope defined by `benchmarks/runner/result-envelope.schema.json`. Validate a local envelope with:

`npm run validate:runner-result -- <path-to-result.json>`

Store terminal envelopes on a non-source evidence surface: an immutable GitHub Actions artifact, a PR/Issue evidence attachment/comment carrying the exact JSON envelope, or another explicitly approved immutable evidence store.

## Task definitions

Each committed task definition records stable `RB-###` ID, source work item, registered source identity, command/workflow, environment/matrix/input/fixture identity, authorization state, security-critical flag, merge-blocking flag, expected runner-time budget, `dedup_key_template`, execution policy, definition status, dependencies, and result-recording mode.

Registration is **not** execution authority. Safe non-blocking work defaults to `final-runner-batch`. Security-critical, exact-head merge-required, migration/auth/secrets/data-safety, current-change integration-safety, and incident/recovery checks are `immediate` when the active milestone needs them. Immediate work without current authority remains `blocked`.

## Result envelope

A terminal envelope records stable task ID, exact candidate repository/ref/SHA, computed deterministic dedup key, current authorization reference, exact execution environment/matrix/inputs/fixtures, PASS/FAIL status, timestamps, and immutable evidence references.

The computed dedup key must incorporate the exact candidate SHA. If the candidate head changes, the old envelope remains historical only and a new envelope/dedup key is required.

By default use one consolidated remote CI/status refresh per milestone and never tight-poll. A message-delivery timeout never authorizes a rerun.

Run `npm run audit:runner-benchmarks` after task-definition changes.
