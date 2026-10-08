# User Response Contract

This contract controls user-facing repository development/status messages produced by the AI Engineering Supervisor.

## Mandatory header

Every development/status response starts with these fields in order:

```text
Repo: <owner/repository>
Current Work: <active bounded milestone or exact waiting/verification action>
Current Module: <active engineering/product/governance module>
Module Progress: [████████░░] <0-100>%
Overall Progress: [██████████] <0-100>% — <scope>
```

Use a 10-cell bar. The displayed numeric percentage remains exact; the visual fill is rounded to the nearest cell.

## Progress sources

The machine-readable baseline is `docs/ai-state/CURRENT-STATE.yaml.response_status`.

- **Module Progress** is evidence-based progress against the explicit acceptance boundary of the active module/milestone.
- **Overall Progress** must come from an authoritative repository progress document, not chat inference.
- The current authoritative overall source is `docs/architecture/MODULAR_MATURITY_STATUS.md`, which records **100% overall weighted modular maturity for the active release scope**.
- That 100% does not erase open maintenance, security, governance, provider, release-trust, or future-scope work. Those remain visible as blockers/current work.
- If a future authoritative scope has no defined denominator, render `Overall Progress: [░░░░░░░░░░] N/A` and explain the missing basis instead of inventing a number.

## Fast-Batch response cadence

Continuous Fast-Batch is the default repository-development interaction mode.

- Do not emit a user-facing status message for every internal file edit, tool call, test command, PR metadata update, review check, or milestone boundary.
- Do not ask the user to approve routine substeps, technical repairs, retries with a changed hypothesis, or next-safe-task selection already inside repository authority.
- Completing one milestone should normally trigger README/compact-state synchronization and immediate selection of the next safe authorized milestone, not a conversational handoff.
- A blocked or externally waiting lane should be recorded and bypassed in favor of another independent safe authorized lane whenever one exists.
- When external CI remains pending after the allowed consolidated observation, report/record the exact pending runs without tight-polling, then continue other safe work. Do not stop the whole execution window solely because one lane is waiting.
- During a long-running execution window, surface only material security findings, genuinely required user action, or meaningful state transitions; otherwise keep selecting and executing safe work. Do not estimate or use a token/session budget as a voluntary stopping condition.
- Group manual configuration into one checklist unless the user explicitly asks for one-by-one instructions.
- Before any terminal execution-window handoff, run the mandatory fallback work scan from `AGENTS.md`; if a safe PR/Issue/repair/maintenance/reconciliation/evidence-preparation lane exists, select it automatically and continue.
- Show numbered next-action choices only when that fallback scan proves no safe authorized work can continue automatically, not after every completed/blocked/waiting internal milestone.
- Do not voluntarily end a development response merely to conserve tokens/context; checkpoint compact state and continue until a genuine terminal repository condition or a hard host boundary is reached.

Continuous Fast-Batch changes cadence and autonomy only. It does not weaken authorization, security, review, exact-head certification, merge, deployment, migration, secrets, provider, or release-publication requirements.

## Exact-head protection

When a PR/source head is under exact-head certification, do not commit compact-state changes merely to update the response display. Use current GitHub PR/Issue/check evidence plus the last durable compact-state baseline, so status reporting does not invalidate the head being certified.

## Required trailing facts

After the header, report only relevant facts: CI/check state, blockers, Issue/PR/commit evidence, and exact next safe action.
