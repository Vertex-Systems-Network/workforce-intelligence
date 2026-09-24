# Fast-Batch Execution Mode

Fast-Batch is the default AI Engineering Supervisor execution mode for this repository.

## Goal

Reduce conversational fragmentation and repeated user prompts while preserving every repository security, authority, exact-head, review, release, and Runner gate.

## Batch boundary

One user turn authorizes one bounded logical milestone. Within that milestone, the supervisor should automatically carry out all routine, tightly coupled, already-authorized substeps that are necessary and safe.

Typical bounded sequence:

`reconcile -> implement -> source checks -> PR -> exact-head observation -> merge if already terminal green -> resulting-main verification -> durable-state/README closeout`

The sequence may stop earlier whenever an external wait or authorization boundary is reached.

## Do not stop for routine substeps

Do not require a new user reply merely to:

- edit another file in the same coherent change;
- repair a test that became stale because the intended contract changed;
- update PR metadata or resolve a routine review-thread state;
- run cheap/source checks;
- perform the single allowed consolidated CI/status observation;
- merge an already-authorized, exact-head-certified, review-clean PR;
- verify the resulting main SHA;
- synchronize compact state and the README progress block at milestone closeout.

## Mandatory stop conditions

Stop and hand control back when:

- a separately authorized destructive/provider/production/release-publication/migration action is next;
- a user-owned secret, certificate, credential, approval, product decision, or external fact is required;
- required CI/external checks remain non-terminal after the consolidated status refresh;
- a material security/scope/authority conflict appears;
- the milestone is complete.

## CI and external waits

Never tight-poll. One consolidated refresh is the default budget. If checks remain pending, persist/record the waiting state without changing the candidate source solely for status, then end the turn. Prefer condition-based notification over repeated manual status prompts when the host supports it.

## User updates

Internal tool activity is not itself a user-facing update. Surface interim messages only for material blockers, security findings, required user action, or meaningful state transitions. Final handoff should be compact and consolidated.

## Manual configuration

Default to a single consolidated checklist. Switch to one-by-one instructions only when the user explicitly asks for them.

## Runner policy

Fast-Batch does not change Runner authorization. Safe non-blocking Runner tasks remain deferred to the final batch; exact-head merge-required and other repository-policy immediate checks remain immediate only when the active milestone requires them.

## Security invariant

Fast-Batch optimizes interaction count, not safety. It must never bypass exact-head certification, review cleanliness, least privilege, secret handling, destructive-action authority, environment protection, migration safeguards, or release trust.
