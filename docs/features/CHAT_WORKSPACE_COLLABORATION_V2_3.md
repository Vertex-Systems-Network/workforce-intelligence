# Chat V2.3 — Workspace Collaboration

Chat V2.3 extends the existing WorkIntel conversation engine with governed workspace channels and actions. It does not introduce a parallel chat subsystem.

## Channel types and discovery

Channels can be private or publicly discoverable inside the workspace. Standard channels allow member posting, while announcement channels default to moderator-only posting. Project and task conversations keep their existing scoped targets and can also use governance metadata.

## Channel roles

Each channel member has a conversation-specific role: `owner`, `admin`, `moderator`, `member`, or `read_only`. Workspace permissions remain authoritative for creating managed channels and domain actions, while channel roles govern channel-specific administration and moderation. The final channel owner cannot be removed or demoted until another owner exists.

## Notifications

Members can select `all`, `mentions`, or `nothing` per conversation. `all` delivers normal top-level message notifications, `mentions` delivers mention/thread notifications without generic message notifications, and `nothing` suppresses chat delivery. Legacy mute state is migrated to `nothing` and remains synchronized with the older mute endpoint.

## Workspace actions

Authorized members can turn a source chat message into an existing WorkIntel Task, Approval Request, or Safety Incident. These actions call the existing task, approval, field-workforce, permission, and scope services. The resulting bot action card links the created record back to the source conversation/message metadata.

## Slash commands

Supported commands are `/help`, `/status`, `/task <title>`, `/assign @[member:ID]`, and `/poll Question | Option one | Option two`. `/assign` is restricted to task-linked conversations and active conversation members. Slash commands do not accept file attachments.

## Bots and resources

Workspaces receive built-in WorkIntel System and WorkIntel Automation bot identities. Bot messages are workspace-scoped structured chat records. Channel moderators can pin validated HTTP/HTTPS resources to the channel details panel.

## Security invariants

All reads/writes still require workspace resolution, Chat module enablement, and conversation membership. Public discovery does not expose private channels. Channel resources cannot bypass conversation membership. Domain actions retain their own permissions and WorkScope checks. The current-user exclusion, canonical DM reuse, inactive-member filtering, attachment privacy, and presence privacy from Chat V2.1 remain regression gates.
