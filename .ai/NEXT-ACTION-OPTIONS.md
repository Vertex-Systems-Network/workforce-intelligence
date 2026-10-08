# VSN Organization Next-Action Options Contract

This repository adopts the Vertex Systems Network interactive AI-development handoff standard.

## User-facing handoff

Do not hand control back merely because an internal milestone completed, one lane became blocked, or one external check is waiting. While the host/session execution budget remains and at least one safe authorized task exists, synchronize durable progress and continue automatically.

Expose 1 to 3 currently valid next actions only at a **terminal execution-window handoff**, and only after the mandatory fallback work scan in `AGENTS.md` proves no safe automatic lane remains. A completed milestone, waiting CI/provider lane, repairable failure, available maintenance task, state drift, or host-token conservation preference is not a valid reason to present options. Do not emit next-action options after internal continuous Fast-Batch progress updates or routine milestone boundaries.

- Always include the canonical/recommended next action, but do not bind it permanently to option 1.
- When two or more valid options exist, reshuffle the visible 1/2/3 numbering on every handoff.
- If the previously selected action identity and number are known, that same action must move to a different visible number on the next handoff. With only one valid action, number reuse is allowed.
- Mark the canonical action as **Recommended**. Numbering is ephemeral presentation state and never changes priority, safety, scope, or authorization.
- A reply containing only an option number starts the corresponding continuous execution window. Re-read current repository state before any mutation. If the option became stale or unsafe, fail closed on that option and continue/recompute any other safe authorized path; show new options only if no safe automatic path remains.
- Interactive buttons may be used when the host supports them; otherwise numbered one-line options are the mandatory fallback.

## URL-only repository entry

When the user's message contains only this repository's canonical GitHub URL (optionally with surrounding whitespace), treat it as a read-only development entry request.

1. Resolve the repository and default/protected branch.
2. Read this repository's durable/current state and governing instructions.
3. Reconcile open Issues first, then open PRs, then any repository-specific coordination/runner state required by local rules.
4. Do **not** create a branch, commit, PR, merge, deployment, provider call, destructive action, or other mutation from the URL alone.
5. Respond with 1 to 3 shuffled valid next-action options and mark the canonical one **Recommended**.
6. The user's subsequent number selection initiates the normal fully revalidated development turn.

## Safety and local authority

Repository-specific governance, security, exact-head CI, approval, migration, production/provider, release, and continuous-execution rules remain authoritative and may be stricter than this interaction contract. Fast-Batch may complete and chain routine safe milestones without another numeric confirmation. This file never grants execution authority and never permits bypassing an accepted actionable Issue/PR or deferred work boundary.
