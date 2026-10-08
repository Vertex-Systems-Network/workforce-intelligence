# VSN Organization Next-Action Options Contract

This repository adopts the Vertex Systems Network interactive AI-development handoff standard.

## User-facing handoff

This repository overrides interactive-choice handoffs in favor of autonomous continuation.

- Do **not** present numbered 1/2/3 engineering choices during ordinary development, after milestone completion, after CI failure, after a merge, during external waits, or because compact state moved.
- Run the mandatory fallback work scan and select the highest-priority safe authorized action automatically.
- At a genuine terminal condition, report the exact blocker/status without asking the owner to choose between engineering tasks. A terminal no-work condition is genuine only after the normal fallback scan and the bounded maintenance-discovery sweep both find no concrete safe task; empty PR/Issue queues or green CI alone are insufficient.
- Ask a single minimal concrete question only when a user-owned fact, secret, legal/commercial decision, explicit new product scope, or separately protected destructive/provider/production/release/migration authority is actually required and no independent safe lane remains.
- If the user explicitly asks for options, up to 3 current safe choices may be shown; otherwise autonomous execution is the default.
- A numeric reply, when the user explicitly requested options earlier, starts the selected continuous execution window after fresh revalidation.

## URL-only repository entry

When the user's message contains only this repository's canonical GitHub URL (optionally with surrounding whitespace), treat it as a **resume/rehydration entry**, not as a forced choice prompt.

1. Resolve the repository and default/protected branch.
2. Read durable/current state and governing instructions.
3. Reconcile OPEN Issues first, then OPEN PRs, reviews/checks, coordination and Runner state.
4. If safe authorized work exists under current repository/standing maintenance authority, continue it automatically using Continuous Fast-Batch.
5. Do not invent new product scope, bypass protected authority, or perform destructive/provider/production/release/migration actions merely from a URL.
6. If no safe automatic lane exists, return terminal status. Do not ask the user to choose engineering options unless the user explicitly requested options; ask only for a genuinely required user-owned fact or authority.

## Safety and local authority

Repository-specific governance, security, exact-head CI, approval, migration, production/provider, release, and continuous-execution rules remain authoritative and may be stricter than this interaction contract. Fast-Batch may complete and chain routine safe milestones without another numeric confirmation. This file never grants execution authority and never permits bypassing an accepted actionable Issue/PR or deferred work boundary.
