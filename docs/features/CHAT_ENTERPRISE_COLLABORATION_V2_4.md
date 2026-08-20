# Chat V2.4 — Enterprise Collaboration

Chat V2.4 extends the existing WorkIntel collaboration engine with governed external access, retention, legal hold, eDiscovery, data-loss-prevention controls and auditable moderation. It is additive to Chat V2.1–V2.3 and does not create a parallel messaging subsystem.

## External collaborators

Workspace administrators with `chat.guests_manage` can enable external access on an individual conversation and create a guest, client or vendor invitation. The invitation is restricted to that conversation, has a hard external-access expiry, and uses the restrictive `external-collaborator` workspace role. The raw invitation token is returned once and only its SHA-256 hash is persisted.

Accepted external members receive only `chat.view` and `chat.create` through the external system role. They cannot create ordinary workspace conversations, discover/join public channels, see project/task creation options or invite other external users. Expired collaborators are rejected by workspace resolution and the hourly enterprise chat maintenance command suspends expired memberships and revokes active workspace access sessions.

## Conversation governance

Enterprise policy is stored directly on the existing `chat_conversations` record:

- `external_access`
- `retention_days`
- `legal_hold`
- `export_policy`
- `dlp_mode`

Policy updates have field-specific backend authorization. A user holding only `chat.export`, for example, cannot use the combined policy endpoint to enable external access or disable DLP.

## Retention and legal hold

A workspace `data_governance_policies` row for the `chat_messages` dataset defaults to 3,650 days. A conversation can override retention with a shorter or longer value within the supported range. The maintenance service permanently removes expired messages and their private attachment files only when no active workspace-wide or conversation-specific legal hold applies.

Legal holds are lifecycle records. Releasing a hold marks it released instead of deleting the record. Active holds override retention cleanup.

## eDiscovery

Authorized administrators can generate private JSON or CSV exports for a conversation. Exports include conversation metadata, members, messages, message edit history, attachment metadata/checksums/security status, reactions and pin state. Internal storage disk/path/checksum fields are hidden from JSON responses.

Export files are stored privately, belong to the requesting workspace member, expire after seven days and are deleted by scheduled maintenance while the export job remains as an audit record.

## DLP

Workspace DLP policies support deterministic rules for:

- message keywords;
- attachment file extensions;
- maximum attachment size.

Policy modes are `monitor`, `quarantine` and `block`. Blocking rules reject content before message persistence. Quarantine rules mark matching attachments and restrict download to chat moderators or DLP administrators. DLP events store matched rule identifiers, not message bodies or file contents.

A conversation can inherit workspace DLP or explicitly disable it only when the actor has the DLP-management permission.

## Moderation audit

Enterprise moderation adds audited `flag` and `redact` actions. Redaction snapshots the previous message body into immutable edit history before removing visible content. Moderation and governance actions are recorded in `chat_moderation_events`.

## Scheduled maintenance

Run manually:

```bash
php artisan workintel:chat-enterprise-maintenance
```

Or for one workspace:

```bash
php artisan workintel:chat-enterprise-maintenance --workspace=1
```

The scheduler runs the maintenance command hourly without overlapping.

## Diagnostics

```bash
php artisan workintel:chat-v2.4-doctor
```

The doctor checks V2.4 schema, permissions, enterprise routes, UI contracts and the maintenance schedule.
