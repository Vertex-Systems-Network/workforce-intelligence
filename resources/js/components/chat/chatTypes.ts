/** Chat domain types shared by the page shell and focused chat components. */
export type Member = {
    id: number;
    name: string;
    email?: string | null;
    job_title?: string | null;
    is_self?: boolean;
    role?: 'owner' | 'admin' | 'moderator' | 'member' | 'read_only';
    collaboration_type?: 'internal' | 'guest' | 'client' | 'vendor';
    external_company?: string | null;
    external_expires_at?: string | null;
};
export type Conversation = {
    id: number;
    uuid: string;
    type: string;
    name: string;
    description?: string | null;
    visibility?: 'public' | 'private';
    channel_mode?: 'standard' | 'announcement';
    posting_policy?: 'members' | 'admins';
    is_locked?: boolean;
    external_access?: boolean;
    retention_days?: number | null;
    legal_hold?: boolean;
    export_policy?: 'admins' | 'moderators' | 'members' | 'disabled';
    dlp_mode?: 'inherit' | 'off';
    viewer_role?: string;
    notification_mode?: 'all' | 'mentions' | 'nothing';
    notifications_snoozed_until?: string | null;
    unread_count: number;
    is_muted: boolean;
    draft?: {
        body: string;
        updated_at: string | null;
    } | null;
    members: Member[];
    project?: {
        id: number;
        name: string;
    } | null;
    task?: {
        id: number;
        title: string;
    } | null;
    last_message?: {
        id: number;
        body: string;
        created_at: string;
    } | null;
};
export type Attachment = {
    id: number;
    filename: string;
    mime_type?: string | null;
    size_bytes: number;
    url: string;
    security_status?: 'clear' | 'review' | 'quarantined' | 'blocked';
    security_reason?: string | null;
};
export type Reaction = {
    emoji: string;
    count: number;
    mine: boolean;
};
export type PollOption = {
    id: number;
    label: string;
    votes: number;
    mine: boolean;
};
export type Poll = {
    id: number;
    allows_multiple: boolean;
    closes_at?: string | null;
    closed: boolean;
    total_voters: number;
    options: PollOption[];
};
export type ForwardedMessage = {
    id: number;
    body: string | null;
    deleted: boolean;
    sender: string;
    attachment_count: number;
};
export type Message = {
    id: number;
    uuid: string;
    client_message_id?: string | null;
    client_sent_at?: string | null;
    delivery_state?: 'pending' | 'sent' | 'failed';
    delivered_to?: number;
    conversation_id: number;
    body: string | null;
    message_type?: string;
    metadata?: Record<string, unknown>;
    deleted_at?: string | null;
    edited_at?: string | null;
    created_at: string;
    sender: {
        id: number | null;
        bot_id?: number;
        name: string;
        kind?: 'member' | 'bot';
        bot_kind?: string;
        collaboration_type?: 'internal' | 'guest' | 'client' | 'vendor';
        external_company?: string | null;
    } | null;
    parent?: {
        id: number;
        body: string;
        sender: string;
    } | null;
    mentions: number[];
    attachments: Attachment[];
    reactions: Reaction[];
    read_by: number;
    mine: boolean;
    pinned: boolean;
    saved: boolean;
    thread_reply_count: number;
    thread_following: boolean;
    thread_unread_count: number;
    forwarded?: ForwardedMessage | null;
    poll?: Poll | null;
};
export type Presence = {
    member_id: number;
    conversation_id: number | null;
    is_typing: boolean;
    last_seen_at: string | null;
};
export type ProjectOption = {
    id: number;
    name: string;
};
export type TaskOption = {
    id: number;
    title: string;
    project_id: number | null;
};
export type DocumentOption = {
    id: number;
    filename: string;
    document_type?: string | null;
    source_type?: string | null;
    source_id?: number | null;
    generated_at?: string | null;
};
export type CreationOptions = {
    current_member_id?: number;
    is_external?: boolean;
    people: Member[];
    projects: ProjectOption[];
    tasks: TaskOption[];
    documents: DocumentOption[];
};
export type ThreadData = {
    root: Message;
    replies: Message[];
    following: boolean;
};
export type EditVersion = {
    id: number;
    body: string | null;
    edited_at: string | null;
    editor: string | null;
};
export type PreviewState = {
    attachment: Attachment;
    url: string;
};
export type SearchMode = 'none' | 'search' | 'saved';
export type MobilePanel = 'list' | 'chat' | 'details';
export type PublicChannel = {
    id: number;
    name: string;
    description?: string | null;
    channel_mode: string;
    member_count: number;
};
export type ChannelResource = {
    id: number;
    kind: string;
    label: string;
    url?: string | null;
    resource_type?: string | null;
    resource_id?: number | null;
    sort_order: number;
    available?: boolean;
    entity?: {
        type: 'project' | 'task' | 'document';
        id: number;
        title: string;
        status?: string | null;
        priority?: string | null;
        due_at?: string | null;
        generated_at?: string | null;
    } | null;
};
export type MessageActionType = 'task' | 'approval' | 'incident';
export type MessagePageMeta = {
    before: number | null;
    after: number | null;
    around?: number | null;
    has_more: boolean;
    next_before: number | null;
    next_after: number | null;
    oldest_id: number | null;
    newest_id: number | null;
    limit: number;
};
export type OutboxItem = {
    clientMessageId: string;
    conversationId: number;
    body: string;
    createdAt: string;
    attempts: number;
    status: 'queued' | 'failed';
    error?: string;
};
export type SyncState = 'live' | 'offline' | 'reconnecting';
export type ConversationFilter = 'all' | 'unread' | 'direct' | 'channels';
export type InboxTriage = {
    status: string;
    snoozed_until?: string | null;
    follow_up_at?: string | null;
};
export type InboxThreadItem = {
    root_message_id: number;
    conversation_id: number;
    conversation_name: string;
    root_body: string;
    unread_count: number;
    latest_reply: Message;
    activity_key?: string;
    triage?: InboxTriage;
};
export type CollaborationInbox = {
    counts: {
        mentions: number;
        threads: number;
        direct: number;
        total: number;
    };
    mentions: Array<Message & {
        activity_key?: string;
        triage?: InboxTriage;
    }>;
    threads: InboxThreadItem[];
    direct: Array<Conversation & {
        activity_key?: string;
        triage?: InboxTriage;
    }>;
};
export type ChatNotificationPreference = {
    category: 'chat_mentions' | 'chat_threads' | 'chat_direct' | 'chat_channels';
    in_app: boolean;
    email: boolean;
    digest: 'immediate' | 'daily' | 'weekly';
};
export type ContextFile = {
    id: number;
    message_id: number;
    filename: string;
    mime_type?: string | null;
    size_bytes: number;
    security_status?: string | null;
    security_reason?: string | null;
    url: string;
    sender?: string | null;
    created_at?: string | null;
};
export type ContextBookmark = {
    id: number;
    note?: string | null;
    updated_at?: string | null;
    message: Message;
};
export type ConversationContext = {
    pinned: Message[];
    bookmarks: ContextBookmark[];
    files: ContextFile[];
    meta?: {
        pin_next?: number | null;
        bookmark_next?: number | null;
        file_next?: number | null;
    };
};
