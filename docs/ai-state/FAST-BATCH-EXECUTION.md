# Fast-Batch Continuous Execution Mode

Fast-Batch is the default AI Engineering Supervisor execution mode for this repository.

## Goal

Keep development moving across successive safe, already-authorized milestones without repeated user prompts, while preserving every repository security, authority, exact-head, review, release, migration and Runner gate.

## Execution window

One user development instruction opens a continuous safe execution window. A milestone is a durable checkpoint inside that window, not an automatic conversational boundary.

Typical continuous sequence:

`reconcile -> implement -> source checks -> PR -> exact-head observation -> merge if already authorized and terminal green -> resulting-main verification -> durable-state/README progress sync -> select next safe milestone -> repeat`

Continue chaining safe authorized milestones while execution capacity is available. Do not pre-compute, target, or voluntarily stop for a token/session budget; only a real host interruption may cut the run short, and durable state must make the next resume automatic.

## Do not stop for routine substeps or technical failures

Do not require a new user reply merely to:

- edit another file in the same coherent change;
- choose the next safe task after a milestone completes;
- diagnose an error or approve a technical repair;
- repair a test that became stale because the intended contract changed;
- update PR metadata or resolve a routine review-thread state;
- run cheap/source checks;
- perform the allowed consolidated CI/status observation;
- merge an already-authorized, exact-head-certified, review-clean PR;
- verify the resulting main SHA;
- synchronize compact state and the README progress block;
- move to another safe lane when the current lane is blocked or waiting externally.

For a technical blocker, capture evidence, change the hypothesis, repair inside current authority, verify, and continue. Repeated identical failure without new evidence still triggers the repository circuit breaker; the response to that circuit breaker is diagnosis or another safe lane, not asking the user to debug the repository.

## External waits and blocked lanes

A blocked lane is not automatically a blocked execution window.

When a provider, credential, approval, CI run, external fact or other dependency is unavailable:

1. record the exact blocker and evidence;
2. mark only that lane `WAITING_EXTERNAL` or `BLOCKED`;
3. preserve exact-head/source identity;
4. continue the next independent safe authorized task.

Before any voluntary handoff, run the mandatory fallback scan:

1. accepted OPEN PR repair/review/merge;
2. accepted actionable OPEN Issue;
3. CI/test/security/review/audit/state/branch repair;
4. authorized dependency and supply-chain maintenance;
5. semantic docs/compact-state/README/coordination/PR-Issue lifecycle reconciliation; pointer-only post-merge drift is not standalone work;
6. non-destructive evidence or diagnostic preparation for an existing authorized blocker.

The owner standing maintenance authority recorded in `.ai/schedule/SCHEDULE-PLAN.md` covers routine repository maintenance in this ladder. It never creates product scope or protected production/provider/release/migration authority.

If a safe fallback exists, choose it automatically. Do not ask the user to choose a fallback and do not end the execution window.

### Maintenance discovery when surfaced queues are empty

Do not equate `no open PR + green CI + externally blocked active milestone` with `no work`.

Before a voluntary no-work handoff, run one bounded fresh discovery pass across:
- CI/test reliability and flaky/failure history;
- dependency, supply-chain and security evidence;
- source-quality, invariant and regression-test gaps;
- semantic documentation/governance drift;
- accessibility, performance, browser and runtime certification gaps;
- non-destructive diagnostics for existing blocked Issues.

A concrete evidence-backed finding becomes an autonomous maintenance lane: open/update an Issue when durable tracking is useful, create a branch/PR when a source fix is justified, verify it, and continue. This standing maintenance authority never permits speculative product features, fabricated evidence, protected production/provider/release actions, or no-op commits.

If every discovery lane is clean or requires unavailable protected/external authority, record that sweep result and only then permit terminal status.

Do not voluntarily stop to conserve tokens/context. Persist a compact checkpoint and continue. A host/session hard termination can interrupt execution, but it is not a repository decision; resume from durable state on the next turn.

Hand control back voluntarily only when the fallback scan **plus the maintenance-discovery pass** prove every reachable/discovered lane needs unavailable user/external/protected authority, a material security/authority conflict makes further work unsafe, or all authorized product and maintenance work is complete. An empty surfaced queue alone is not terminal proof. At that point, do not offer engineering choices by default: report the blocker/status and that the discovery pass was clean. Ask one minimal concrete question only if a user-owned fact or authority is actually required to proceed.

## CI and remote-call budget

Never tight-poll. One consolidated refresh per milestone/lane is the default budget. If checks remain pending, persist the waiting state without changing candidate source solely for status, then continue other safe work. A later refresh is allowed only after a material transition makes it necessary for a safe decision.

## User confirmation policy

Routine technical work never requires user confirmation. The supervisor must not ask the user to confirm an error repair, retry, test fix, refactor, PR metadata update, safe merge that is already authorized, or next safe milestone selection.

User input remains required only for information or authority the repository cannot legitimately infer or self-create, such as user-owned secrets, explicit new product/scope decisions, legal/commercial decisions, or separately protected destructive/provider/production/release/migration actions. Before asking, perform a fresh live-authority reconciliation and the mandatory fallback scan. A stale/closed Issue, moved branch/head, failed CI, merge completion, state drift, or ordinary technical choice is never sufficient reason to ask. If another safe lane exists, continue it instead.

## README progress synchronization

At every completed milestone and meaningful durable progress checkpoint, synchronize `docs/ai-state/CURRENT-STATE.yaml` and the root `README.md` **AI Development Progress** block before chaining onward, when source mutation is safe.

Do not invent percentages. Preserve the exact-head rule: while a candidate head is under certification, do not mutate it solely for status; record live remote status on the PR/Issue evidence surface. Committed compact state and README are semantic checkpoints, not live transaction ledgers: PR number, working branch, check IDs, merge SHA and pointer-only post-merge drift are resolved from GitHub at runtime and must not spawn a recursive state-only PR. README must omit those volatile transaction fields. Semantic product/module/progress/blocker drift still requires correction.

## User updates

Internal tool activity is not itself a user-facing update. Surface interim messages only for material security findings, genuinely required user action, or meaningful state transitions. Do not present next-action choices merely because an internal milestone completed while safe work remains.

## Manual configuration

Default to a single consolidated checklist. Switch to one-by-one instructions only when the user explicitly asks for them.

## Runner policy

Continuous Fast-Batch does not change Runner authorization. Safe non-blocking Runner tasks remain governed by the registry; exact-head merge-required and other repository-policy immediate checks remain immediate only when the active milestone requires them.

## Security invariant

Continuous Fast-Batch optimizes uninterrupted safe execution, not safety. It must never bypass exact-head certification, review cleanliness, least privilege, secret handling, destructive-action authority, environment protection, migration safeguards, tenant isolation, release trust, or explicit scope authority.
