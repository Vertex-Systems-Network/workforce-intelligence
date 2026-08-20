# M10 — Chat & Collaboration V4

M10 promotes the existing professional Chat V2.x stack into the modular WorkIntel collaboration experience without replacing proven enterprise governance, realtime delivery, threads, drafts, polls, DLP, exports or guest controls.

## MAX Batch 1

- Keeps direct messages, groups, public/private channels, project threads and task threads on the existing workspace membership boundary.
- Adds a **Collaboration Activity inbox** that aggregates unread mentions, unread followed-thread replies and unread direct conversations.
- Adds a richer **conversation context panel** with pinned messages, private saved-message bookmarks/notes and recent private files.
- Saved-message notes are private to the member and never modify the shared message.
- Chat attachments continue through the shared **Media Library / Upload** chooser (`MediaFileField` / `MediaPicker`) while server attachment/DLP validation remains authoritative.
- Channel resources can now link authorized WorkIntel projects, tasks and generated documents in addition to HTTP/HTTPS links. Internal resource IDs are re-authorized server-side before a pin is created.
- Existing realtime typing/presence, read/delivered cursors, reactions, pins, threads, polls, durable drafts, offline idempotent outbox, advanced search operators and notification modes remain intact.
- Enterprise moderation no longer uses browser-native `window.prompt`; flag/redact reasons use WorkIntel dialog controls.
- M10 audit, frontend contracts, PHPUnit source contract and DB-backed Feature flow are wired into release and clean-install gates.

## Remaining M10 closure scope

- Dedicated notification preference matrix across mentions, threads, DMs and channel events.
- Richer inbox triage (mark-one/read-all, snooze/follow-up state) and saved-message organization.
- Deeper entity cards for projects/tasks/documents instead of compact resource rows.
- Context panel pagination for very large channels and bulk pin/bookmark administration.
- Target Laragon DB-backed feature suite, installed-node build/typecheck and Playwright mobile/RTL/accessibility certification.

## MAX Closure

- Adds persistent `chat_activity_states` for private inbox triage. Derived mention/thread/DM activity can be marked done, snoozed for later, or assigned a follow-up without mutating shared message/read history.
- Adds a dedicated chat notification matrix using the existing `notification_preferences` store: mentions, followed threads, direct messages and channel activity each support in-app/email plus immediate/daily/weekly delivery policy.
- Chat notification emission now uses the matching granular category so the matrix is effective rather than decorative.
- Conversation context uses independent cursor pagination for pins, private bookmarks and recent files. Large channels no longer require loading the complete context collection at once.
- Adds bounded bulk context cleanup: moderators can unpin visible messages; members can remove their own visible bookmarks. Server authorization remains authoritative.
- Project, task and generated-document resources now render permission-safe entity-card metadata. If visibility is later removed, chat returns an unavailable card instead of leaking current restricted entity details.
- M10 remains at 95% until target Laragon DB flow, installed-node typecheck/build and Playwright mobile/RTL/accessibility certification are executed.
