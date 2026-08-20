import type { Attachment, Member, Message, OutboxItem } from './chatTypes';

/** Creates a stable client-generated idempotency key for one outgoing chat message. */
export const createClientMessageId = () => `web:${crypto.randomUUID()}`;
/** Returns the workspace-scoped local outbox storage key. */
export const outboxKey = (workspaceId: number) => `workintel-chat-outbox:${workspaceId}`;
/** Reads a bounded text-message outbox without allowing malformed local data to break chat boot. */
export const readOutbox = (workspaceId: number): OutboxItem[] => {
    try {
        const parsed = JSON.parse(window.localStorage.getItem(outboxKey(workspaceId)) ?? '[]');
        return Array.isArray(parsed) ? parsed.filter(item => item && typeof item.clientMessageId === 'string' && typeof item.body === 'string').slice(-100) : [];
    }
    catch {
        return [];
    }
};
/** Persists the bounded text outbox used for reconnect delivery recovery. */
export const writeOutbox = (workspaceId: number, items: OutboxItem[]) => window.localStorage.setItem(outboxKey(workspaceId), JSON.stringify(items.slice(-100)));
/** Merges message pages by server id and keeps the client rendering window bounded for long histories. */
export const mergeMessageWindow = (current: Message[], incoming: Message[], maxItems = 600, keep: 'latest' | 'older' = 'latest'): Message[] => {
    const byId = new Map<number, Message>();
    for (const message of current)
        byId.set(message.id, message);
    for (const message of incoming)
        byId.set(message.id, message);
    const ordered = Array.from(byId.values()).sort((a, b) => a.id - b.id);
    const bounded = Math.max(100, maxItems);
    return keep === 'older' ? ordered.slice(0, bounded) : ordered.slice(-bounded);
};
/** Formats an attachment size for compact chat display. */
export const size = (bytes: number) => bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
/** Replaces secure member mention tokens with human-readable names. */
export const displayBody = (value: string | null, members: Member[]) => {
    let out = value ?? '';
    for (const member of members)
        out = out.replaceAll(`@[member:${member.id}]`, `@${member.name}`);
    return out;
};
/** Determines whether enterprise moderation has flagged a message for review. */
export const messageFlagged = (message: Message) => Boolean(((message.metadata?.moderation as Record<string, unknown> | undefined) ?? {}).flagged_at);
/** Determines whether the message viewport is close enough to keep following new messages. */
export const nearBottom = (element: HTMLDivElement | null) => !element || element.scrollHeight - element.scrollTop - element.clientHeight < 96;
/** Classifies a private attachment for professional preview controls. */
export const attachmentKind = (attachment: Attachment): 'image' | 'video' | 'audio' | 'file' => {
    const mime = (attachment.mime_type ?? '').toLowerCase();
    if (['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'].includes(mime))
        return 'image';
    if (['video/mp4', 'video/webm', 'video/ogg'].includes(mime))
        return 'video';
    if (['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/webm'].includes(mime))
        return 'audio';
    return 'file';
};
