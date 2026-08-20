# Chat V2.1 — Professional Collaboration Stabilization

Chat V2.1 hardens the existing WorkIntel chat foundation before advanced messaging expansion.

## Identity and conversation safety

- New Conversation excludes the signed-in workspace member on the API and UI layers.
- Only active workspace memberships backed by active user accounts are eligible conversation participants.
- Explicit self-DM requests return `422 SELF_CONVERSATION_NOT_ALLOWED`.
- A direct-message pair is canonical: recreating the same pair opens the existing conversation rather than inserting a duplicate.
- Presence responses remain limited to members that share at least one conversation with the viewer.

## UI stabilization

Desktop uses a fixed three-pane layout for conversation list, active conversation and details. Tablet uses an overlay details drawer. Mobile uses one panel at a time so the conversation list and message pane do not stack into a broken long page.

The message viewport preserves scroll position while a user is reading older history. Incoming messages display a **Jump to latest** control instead of forcing the reader to the bottom. The first unread message can retain an unread divider for orientation.

Typing presence is debounced. A typing request is no longer sent for every keystroke.

## RTL

Pinned-message emphasis and reply borders use logical CSS properties. Mobile navigation icons mirror under RTL so Urdu and Arabic layouts retain correct directional affordances.

## Next block

After V2.1 is certified, Chat V2.2 can add edit/delete history UI, message threads, drafts, saved messages, forwarding, advanced search, polls and richer attachment workflows without building on unstable navigation or identity behavior.
