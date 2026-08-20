# Phase 22 — Mobile Field Workforce

Phase 22 adds a mobile/offline field-work layer that is separate from normal browser session authentication.

## Mobile authentication

Mobile login returns a `wim_...` bearer token. Only SHA-256(token) is stored. The raw token is returned once and is never persisted by the server.

A mobile token is bound to a workspace member/device identity, has expiry/revocation state, and records last-use metadata. Enterprise MFA policy is honored when installed.

## Work orders

Work orders support assignment, job/client/project linkage and controlled transitions:

- assigned
- accepted
- in_progress
- blocked
- completed

Terminal states cannot be silently reopened by an ordinary field worker. Managers can perform authorized administrative transitions.

## Offline sync

Every mobile event has a UUID. The sync envelope is durable and idempotent:

- same processed UUID -> duplicate response, no domain mutation
- failed UUID -> controlled retry
- same UUID from a different member -> rejected
- processing/processed state is recorded independently of the device network retry

This protects work-order/checkpoint/incident operations from duplicate offline replay.

## Checkpoints

QR/NFC-ready checkpoint secrets use a `wifc_...` token and are hash-only at rest. Optional geofence validation checks a scan against the configured checkpoint radius.

## Forms and incidents

Field forms support template fields, submissions and answers. Safety incidents are numbered under a workspace lock so simultaneous reports cannot receive the same incident number.

## Files

Field evidence/photos are stored privately and are served through authorized endpoints. The module does not introduce a public storage URL for evidence.

## Location privacy

Location is accepted as evidence attached to explicit field actions. Phase 22 does not add continuous background GPS surveillance.

## Doctor

```bash
php artisan workintel:mobile-field-doctor
```

Checks Phase 22 tables plus private-storage read/write readiness.
