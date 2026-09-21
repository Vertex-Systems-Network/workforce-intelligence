# Runner Benchmark Register

## Purpose

WorkIntel intentionally keeps expensive runner/browser certification at the end of the implementation cycle so release evidence is produced for a settled head instead of a sequence of transient commits.

The canonical machine-readable backlog is `benchmarks/runner/registry.json`. Every material AI/code-agent session must add or update an entry when it discovers verification that cannot be responsibly completed with the normal local/source quality lane.

The register is additive. A newly discovered runner requirement is captured immediately, but execution is deferred to the final runner batch unless the active scope explicitly requires earlier real-target evidence.

## What belongs in the register

Add a benchmark when the required evidence genuinely depends on one or more of these conditions:

- a GitHub-hosted release/certification workflow;
- a Windows-only runner or platform-specific release environment;
- installed real Chrome, Microsoft Edge, Firefox, or another system-browser matrix;
- a publicly reachable external target or third-party certification service;
- a real release/runtime target whose evidence is materially more expensive or environment-sensitive than ordinary development checks;
- another owner-approved remote matrix whose repeated execution on intermediate commits would create stale evidence or unnecessary runner cost.

Do **not** use the register to defer normal development feedback such as unit tests, PHP/JS contract tests, typecheck, source audits, documentation audits, Pint for changed PHP files, performance/source budgets, or other inexpensive checks that can run before the final certification phase. Targeted browser testing may also run earlier when it is useful to diagnose a feature; the backlog is for the expensive final certification obligation, not for hiding browser defects until release.

## Required entry contract

Each entry must include:

- stable `RB-###` identifier and descriptive title;
- source/ref explaining where the requirement came from;
- scope and explicit defer reason;
- required environment;
- exact command or workflow identifier;
- measurable acceptance criteria;
- `required_for_release` boolean;
- `execution_phase: final-runner-batch`;
- `stale_when_head_moves: true`;
- status and dependencies;
- current verification object;
- historical attempts/evidence when applicable.

Use `npm run audit:runner-benchmarks` to validate structure before handing work to another agent or entering final certification.

## Status model

| Status | Meaning |
| --- | --- |
| `queued` | Captured but not yet runnable; one or more prerequisites are not available. |
| `ready` | Fully specified and intended to run in the final benchmark batch. |
| `running` | Currently executing for the exact candidate head. |
| `blocked` | Cannot execute because a named environment, credential, authority, or infrastructure prerequisite is missing. |
| `passed` | Passed for the exact SHA recorded in `verification.head_sha`, with timestamp and evidence. |
| `failed` | Failed for the exact SHA recorded in `verification.head_sha`, with timestamp and evidence. |
| `superseded` | Replaced by another benchmark entry; retain the record and point to the replacement in scope/history rather than deleting evidence. |

During implementation, an unexecuted required benchmark is reported as:

`Not Verified — deferred to final runner batch`

Never convert deferred work into PASS merely because source checks are green.

## Final runner batch

After implementation, source cleanup, and normal quality work are complete:

1. Rehydrate protected `main`, branch/PR state, authority, required checks, and unresolved review threads.
2. Run the normal source lane, including `npm run quality` and `npm run quality:full`, fixing source defects before spending runner capacity.
3. Run `npm run audit:runner-benchmarks` and resolve malformed, duplicate, or underspecified entries.
4. Settle/freeze the candidate PR head SHA for certification.
5. Select every `required_for_release: true` benchmark whose current verification is not PASS for that exact SHA, plus any optional benchmark explicitly required by the approved scope.
6. Execute that set as one final certification batch. “One batch” means one final phase; jobs may run in dependency order rather than unsafe forced parallelism.
7. For each execution, record exact head SHA, timestamp, and immutable run/evidence references. A failed benchmark remains failed/blocked until diagnosed and rerun; never weaken the gate.
8. If source changes after a benchmark result, move the old result to `history`, reset the current verification, and return the affected benchmark to `ready` or `queued`. Evidence for an older SHA cannot certify the newer head.
9. Release/merge completion may be claimed only when every required benchmark and every separately required repository status is green for the exact final head.

## Adding new work during implementation

When a task exposes a new runner-only requirement, the agent must update the register in the same checkpoint rather than relying on chat memory or a private checklist. Use the next stable `RB-###` identifier, describe why the check is deferred, and link it to the feature/issue/contract that created the obligation.

If the task is cheap enough to run locally, run it now instead of registering it merely to postpone feedback.

## Evidence discipline

`passed` and `failed` are evidence-bearing states. They require:

- a 40-character Git commit SHA;
- a verification timestamp;
- at least one evidence reference such as a GitHub Actions run URL/ID, retained runtime report, or external scan reference.

When the head changes, current PASS evidence becomes stale by policy even if the code change appears unrelated. The agent may preserve the old result in `history`, but current completion claims must still be based on evidence accepted for the final head and the actual repository ruleset.
