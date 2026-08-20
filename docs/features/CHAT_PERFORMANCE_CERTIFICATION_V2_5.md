# Chat V2.5 — Performance & Production Certification

Chat V2.5 closes the professional collaboration roadmap with transport reliability, bounded history rendering, server-side idempotency and production safety gates. It extends the existing V2.1–V2.4 data model; it does not introduce a second chat subsystem.

## Message history and sync

The message endpoint supports `before`, `after` and `around` integer cursors. Search-result jumps use `around` so older matches open in context instead of silently falling back to the latest page. Initial history loads the latest bounded page, scrolling near the top requests an older page, and routine polling asks only for messages newer than the newest loaded server message. Periodic latest-page reconciliation catches edits, reactions and soft deletes when WebSocket delivery is unavailable.

The web client keeps a bounded in-memory window and uses CSS `content-visibility` containment so very long conversations do not require the browser to fully lay out every loaded message. Moving into older history may evict the newest part of the local window; **Jump to latest** reconciles with the server before scrolling to the newest message.

## Exactly-once retry identity

Every normal browser send receives a `client_message_id`. The database uniquely scopes that id to `(conversation_id, sender_member_id, client_message_id)`. Retrying a request with the same id returns the original message and does not repeat attachments, notifications or thread effects. A transaction-level duplicate-race recovery path handles concurrent retries.

## Offline outbox

Text messages can be queued in a workspace-scoped local browser outbox while offline. Reconnect flushes the queue sequentially using the original idempotency key. Failed non-transient items remain visible with a Retry action. Attachments and slash commands are intentionally not persisted into browser storage and require an active network connection.

## Delivery and read cursors

`last_delivered_message_id` records that a member fetched a message page. It is independent from `last_read_message_id`, which advances only when the UI reaches the latest visible content. Read requests are batched client-side to reduce write amplification.

## Multi-tab and realtime behavior

Laravel Reverb/Echo remains the preferred push transport. Polling is the fallback. Browser tabs also synchronize through `BroadcastChannel`, allowing a send or reconnect flush in one tab to refresh sibling tabs without waiting for the next polling interval.

## Server safety

Chat routes have layered request throttles. Attachments have per-file, count and combined-size limits. High-risk executable extensions are rejected by default; an enterprise DLP policy may explicitly quarantine a matching file instead of allowing ordinary download access.

## Verification

Run `php artisan workintel:chat-v2.5-doctor` after migration. `verify-release.cmd` and `verify-clean-install.cmd` run all V2.1–V2.5 smoke, contract and feature gates before the full PHPUnit and frontend build suites.
