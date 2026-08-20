# Chat V2.2 — Professional Messaging

Chat V2.2 extends the stabilized collaboration layer without weakening workspace, module, role or conversation membership boundaries.

## Delivered capabilities

- Message edit with immutable pre-edit history.
- Soft delete with auditable prior-content snapshot restricted to the sender or `chat.moderate` users.
- Proper root-message threads with reply counts, follow/unfollow state and a dedicated thread panel.
- Cross-device text drafts saved per member and conversation.
- Private Saved Messages/bookmarks.
- Forward/share into another conversation after the target membership is re-authorized by the backend.
- Advanced scoped search operators: `from:`, `in:`, `before:`, `after:`, `has:file`, and `has:link`.
- Single- and multiple-choice polls with optional close time and aggregate-only vote results.
- Authenticated image/video/audio previews; raw storage paths and provider URLs remain private.
- Professional message action menu for thread, reactions, save, pin, forward, edit, history and soft delete.

## Persistence

The additive migration `2026_08_13_000400_create_chat_professional_messaging.php` adds:

- `chat_message_edit_history`
- `chat_saved_messages`
- `chat_drafts`
- `chat_polls`
- `chat_poll_options`
- `chat_poll_votes`
- `chat_thread_follows`
- `chat_messages.forwarded_from_message_id`

Existing conversations, messages, attachments, reactions, pins and presence records are not deleted or rewritten.

## Security rules

1. Every thread, bookmark, history, forward, poll and draft operation re-validates conversation membership.
2. Forwarding validates both the source and target conversation.
3. Edit history is not a general conversation feed; it is limited to the message sender or a chat moderator.
4. Saved messages and drafts are private to the owning workspace member.
5. Advanced search is built from typed operators only; raw SQL fragments are never accepted.
6. Poll responses expose aggregate counts plus only the current member's own selections.
7. Attachment preview still downloads through the authenticated WorkIntel API.

## Verification

Run on an existing database:

```bat
verify-release.cmd
```

Run on a disposable zero-install database:

```bat
verify-clean-install.cmd
```

The release gates run Chat V2.1 regressions first, then Chat V2.2 contracts/feature flows, followed by the full PHPUnit and npm/build suites.
