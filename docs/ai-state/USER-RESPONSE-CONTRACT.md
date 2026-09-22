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

## Exact-head protection

When a PR/source head is under exact-head certification, do not commit compact-state changes merely to update the response display. Use current GitHub PR/Issue/check evidence plus the last durable compact-state baseline, so status reporting does not invalidate the head being certified.

## Required trailing facts

After the header, report only relevant facts: CI/check state, blockers, Issue/PR/commit evidence, and exact next safe action.
