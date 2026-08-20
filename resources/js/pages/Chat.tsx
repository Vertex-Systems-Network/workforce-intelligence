import { useEffect, useMemo, useRef, useState } from 'react';
import { ArrowLeft, AtSign, BarChart3, Bell, BellOff, CheckCircle2, Clock3, Bookmark, ChevronDown, Download, FileText, Film, Forward, Hash, History, Image as ImageIcon, Inbox, Info, ListTree, MessageCircle, MoreHorizontal, Music, Paperclip, Pencil, Pin, Plus, Reply, Search, Send, Shield, Smile, Trash2, Users, WifiOff, RefreshCw, X, } from 'lucide-react';
import { ApiError, apiDownload, apiRequest } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { hasPermission } from '../access';
import { useConfirmAction, Alert, Avatar, Badge, Button, Field, Input, Modal, Page, PageHeader, Segmented, Select, Textarea, Pressable, ChoiceInput, Checkbox, Image, Label, Link, Option, Box} from '../design-system';
import { PageLoadingState } from '../components/LoadingStates';
import { getRealtime } from '../realtime';
import { useLocalization } from '../i18n/LocalizationContext';
import { MediaFileField } from '../media/MediaFileField';
import { EnterpriseControls } from '../components/chat/EnterpriseControls';
import type { Attachment, ChatNotificationPreference, CollaborationInbox, Conversation, ConversationContext, ConversationFilter, CreationOptions, EditVersion, Message, MessageActionType, MessagePageMeta, MobilePanel, OutboxItem, Presence, PreviewState, PublicChannel, SearchMode, SyncState, ThreadData, ChannelResource, ContextBookmark, ContextFile, Member } from '../components/chat/chatTypes';
import { attachmentKind, createClientMessageId, displayBody, mergeMessageWindow, messageFlagged, nearBottom, readOutbox, size, writeOutbox } from '../components/chat/chatUtils';
import { AttachmentPreview, ConversationDetails, MessageCard, NewConversation, PollModal, ThreadPanel } from '../components/chat/ChatPanels';
/** Renders the professional workplace chat experience with durable drafts, threads and message actions. */
export default function Chat() {
    const confirmAction = useConfirmAction();
    const { session } = useAuth();
    const { formatTime } = useLocalization();
    const workspace = session?.user.workspaces.find(item => item.id === session.user.activeWorkspaceId);
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [presence, setPresence] = useState<Presence[]>([]);
    const [viewerMemberId, setViewerMemberId] = useState<number | null>(workspace?.memberId ?? null);
    const [selected, setSelected] = useState<number | null>(null);
    const [messages, setMessages] = useState<Message[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [body, setBody] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const [sending, setSending] = useState(false);
    const [newOpen, setNewOpen] = useState(false);
    const [enterpriseOpen, setEnterpriseOpen] = useState(false);
    const [optionsLoaded, setOptionsLoaded] = useState(false);
    const [options, setOptions] = useState<CreationOptions>({ people: [], projects: [], tasks: [], documents: [] });
    const [search, setSearch] = useState('');
    const [conversationFilter, setConversationFilter] = useState<ConversationFilter>('all');
    const [results, setResults] = useState<Message[]>([]);
    const [searchMode, setSearchMode] = useState<SearchMode>('none');
    const [showJump, setShowJump] = useState(false);
    const [unreadStartId, setUnreadStartId] = useState<number | null>(null);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [mobilePanel, setMobilePanel] = useState<MobilePanel>('list');
    const [menuMessageId, setMenuMessageId] = useState<number | null>(null);
    const [editMessage, setEditMessage] = useState<Message | null>(null);
    const [editBody, setEditBody] = useState('');
    const [editHistory, setEditHistory] = useState<EditVersion[]>([]);
    const [historyMessage, setHistoryMessage] = useState<Message | null>(null);
    const [forwardMessage, setForwardMessage] = useState<Message | null>(null);
    const [forwardConversationId, setForwardConversationId] = useState('');
    const [forwardNote, setForwardNote] = useState('');
    const [pollOpen, setPollOpen] = useState(false);
    const [pollQuestion, setPollQuestion] = useState('');
    const [pollOptions, setPollOptions] = useState(['', '']);
    const [pollMultiple, setPollMultiple] = useState(false);
    const [pollClosesAt, setPollClosesAt] = useState('');
    const [threadRoot, setThreadRoot] = useState<Message | null>(null);
    const [threadData, setThreadData] = useState<ThreadData | null>(null);
    const [threadBody, setThreadBody] = useState('');
    const [threadSending, setThreadSending] = useState(false);
    const [preview, setPreview] = useState<PreviewState | null>(null);
    const [draftStatus, setDraftStatus] = useState<'idle' | 'saving' | 'saved'>('idle');
    const [targetMessageId, setTargetMessageId] = useState<number | null>(null);
    const [publicChannels, setPublicChannels] = useState<PublicChannel[]>([]);
    const [resources, setResources] = useState<ChannelResource[]>([]);
    const [resourceLabel, setResourceLabel] = useState('');
    const [resourceUrl, setResourceUrl] = useState('');
    const [resourceKind, setResourceKind] = useState<'link' | 'project' | 'task' | 'document'>('link');
    const [resourceId, setResourceId] = useState('');
    const [inbox, setInbox] = useState<CollaborationInbox>({ counts: { mentions: 0, threads: 0, direct: 0, total: 0 }, mentions: [], threads: [], direct: [] });
    const [activityOpen, setActivityOpen] = useState(false);
    const [notificationOpen, setNotificationOpen] = useState(false);
    const [chatPreferences, setChatPreferences] = useState<ChatNotificationPreference[]>([]);
    const [contextData, setContextData] = useState<ConversationContext>({ pinned: [], bookmarks: [], files: [], meta: {} });
    const [contextLoadingMore, setContextLoadingMore] = useState(false);
    const [bookmarkNoteMessage, setBookmarkNoteMessage] = useState<Message | null>(null);
    const [bookmarkNote, setBookmarkNote] = useState('');
    const [moderationMessage, setModerationMessage] = useState<Message | null>(null);
    const [moderationAction, setModerationAction] = useState<'flag' | 'redact'>('flag');
    const [moderationReason, setModerationReason] = useState('');
    const [actionMessage, setActionMessage] = useState<Message | null>(null);
    const [actionType, setActionType] = useState<MessageActionType>('task');
    const [actionTitle, setActionTitle] = useState('');
    const [actionProjectId, setActionProjectId] = useState('');
    const [actionBusy, setActionBusy] = useState(false);
    const [hasOlderMessages, setHasOlderMessages] = useState(false);
    const [loadingOlderMessages, setLoadingOlderMessages] = useState(false);
    const [syncState, setSyncState] = useState<SyncState>(navigator.onLine ? 'live' : 'offline');
    const [outbox, setOutbox] = useState<OutboxItem[]>([]);
    const messageList = useRef<HTMLDivElement | null>(null);
    const messageEnd = useRef<HTMLDivElement | null>(null);
    const messageState = useRef<Message[]>([]);
    const activeConversation = useRef<number | null>(null);
    const typingTimer = useRef<number | null>(null);
    const typingActive = useRef(false);
    const draftLoadedConversation = useRef<number | null>(null);
    const messagePollCount = useRef(0);
    const readTimer = useRef<number | null>(null);
    const pendingRead = useRef<{
        conversationId: number;
        messageId: number;
    } | null>(null);
    const tabChannel = useRef<BroadcastChannel | null>(null);
    const current = conversations.find(conversation => conversation.id === selected) ?? null;
    const canManage = workspace ? hasPermission(workspace, 'chat.manage') : false;
    const canModerate = workspace ? hasPermission(workspace, 'chat.moderate') : false;
    const canGuestsManage = workspace ? hasPermission(workspace, 'chat.guests_manage') : false;
    const canRetentionManage = workspace ? hasPermission(workspace, 'chat.retention_manage') : false;
    const canExportChat = workspace ? hasPermission(workspace, 'chat.export') : false;
    const canLegalHoldManage = workspace ? hasPermission(workspace, 'chat.legal_hold_manage') : false;
    const canDlpManage = workspace ? hasPermission(workspace, 'chat.dlp_manage') : false;
    const canEnterprise = canGuestsManage || canRetentionManage || canExportChat || canLegalHoldManage || canDlpManage;
    const isExternalViewer = options.is_external === true;
    const canGovernCurrent = Boolean(current && (canManage || ['owner', 'admin'].includes(current.viewer_role ?? '')));
    const canModerateCurrent = Boolean(current && (canManage || canModerate || ['owner', 'admin', 'moderator'].includes(current.viewer_role ?? '')));
    const canPostCurrent = Boolean(current && current.viewer_role !== 'read_only' && (!current.is_locked || canModerateCurrent) && (!(current.channel_mode === 'announcement' || current.posting_policy === 'admins') || canModerateCurrent));
    const otherMembers = useMemo(() => current?.members.filter(member => !member.is_self && member.id !== viewerMemberId) ?? [], [current, viewerMemberId]);
    const filteredConversations = useMemo(() => conversations.filter(conversation => {
        if (conversationFilter === 'unread')
            return conversation.unread_count > 0;
        if (conversationFilter === 'direct')
            return conversation.type === 'direct';
        if (conversationFilter === 'channels')
            return conversation.type !== 'direct';
        return true;
    }), [conversations, conversationFilter]);
    const unreadConversationCount = useMemo(() => conversations.filter(conversation => conversation.unread_count > 0).length, [conversations]);
    /** Marks a visible conversation read through its latest known message. */
    const markRead = async (conversationId: number, messageId?: number) => {
        if (!workspace)
            return;
        await apiRequest(`/api/v1/chat/conversations/${conversationId}/read`, { method: 'PUT', body: JSON.stringify(messageId ? { message_id: messageId } : {}), workspaceId: workspace.id, silent: true }).catch(() => { });
        setConversations(items => items.map(item => item.id === conversationId ? { ...item, unread_count: 0 } : item));
        void loadInbox(true);
    };
    /** Batches rapid read-cursor updates so scrolling does not generate one API write per event. */
    const scheduleMarkRead = (conversationId: number, messageId: number) => {
        pendingRead.current = { conversationId, messageId };
        if (readTimer.current !== null)
            return;
        readTimer.current = window.setTimeout(() => {
            const pending = pendingRead.current;
            pendingRead.current = null;
            readTimer.current = null;
            if (pending)
                void markRead(pending.conversationId, pending.messageId);
        }, 350);
    };
    /** Scrolls the active message viewport to the newest message and clears unread state. */
    const jumpToLatest = (behavior: ScrollBehavior = 'smooth') => {
        const localLast = messageState.current[messageState.current.length - 1]?.id ?? 0;
        const serverLast = current?.last_message?.id ?? localLast;
        if (selected && serverLast > localLast) {
            void loadMessages(selected, true, true).then(() => requestAnimationFrame(() => jumpToLatest(behavior)));
            return;
        }
        messageEnd.current?.scrollIntoView({ behavior, block: 'end' });
        setShowJump(false);
        const last = messageState.current[messageState.current.length - 1];
        if (selected && last)
            scheduleMarkRead(selected, last.id);
    };
    /** Loads conversation summaries and privacy-scoped presence data. */
    const loadConversations = async (silent = false) => {
        if (!workspace)
            return;
        try {
            const response = await apiRequest<{
                data: Conversation[];
                presence: Presence[];
                viewer_member_id: number;
            }>('/api/v1/chat/conversations', { workspaceId: workspace.id, silent });
            setConversations(response.data);
            setPresence(response.presence);
            setViewerMemberId(response.viewer_member_id ?? workspace.memberId ?? null);
            setSelected(currentId => currentId && response.data.some(item => item.id === currentId) ? currentId : (response.data[0]?.id ?? null));
        }
        catch (exception) {
            if (!silent)
                setError(exception instanceof Error ? exception.message : 'Could not load chat.');
        }
        finally {
            if (!silent)
                setLoading(false);
        }
    };
    /** Loads latest or incremental messages without forcing the reader away from an older scroll position. */
    const loadMessages = async (conversationId: number, silent = false, forceLatest = false) => {
        if (!workspace)
            return;
        const firstLoad = activeConversation.current !== conversationId;
        const shouldStick = firstLoad || nearBottom(messageList.current);
        const previous = messageState.current;
        const previousLastId = previous[previous.length - 1]?.id ?? 0;
        const incremental = !firstLoad && !forceLatest && previousLastId > 0;
        const aroundTarget = firstLoad ? targetMessageId : null;
        const url = aroundTarget
            ? `/api/v1/chat/conversations/${conversationId}/messages?around=${aroundTarget}&limit=60`
            : incremental
                ? `/api/v1/chat/conversations/${conversationId}/messages?after=${previousLastId}&limit=100`
                : `/api/v1/chat/conversations/${conversationId}/messages?limit=60`;
        try {
            const response = await apiRequest<{
                data: Message[];
                meta: MessagePageMeta;
            }>(url, { workspaceId: workspace.id, silent });
            const incoming = response.data.filter(message => message.id > previousLastId);
            const maxItems = 600;
            const next = firstLoad ? response.data : mergeMessageWindow(previous, response.data, maxItems);
            messageState.current = next;
            setMessages(next);
            if (firstLoad || forceLatest)
                setHasOlderMessages(Boolean(response.meta?.has_more || response.meta?.next_before));
            if (firstLoad) {
                activeConversation.current = conversationId;
                const unreadCount = conversations.find(item => item.id === conversationId)?.unread_count ?? 0;
                const unread = next.filter(message => !message.mine).slice(-unreadCount);
                setUnreadStartId(unread[0]?.id ?? null);
            }
            else if (!shouldStick && incoming.some(message => !message.mine)) {
                setUnreadStartId(currentId => currentId ?? incoming.find(message => !message.mine)?.id ?? null);
                setShowJump(true);
            }
            requestAnimationFrame(() => {
                if (targetMessageId) {
                    const target = document.querySelector(`[data-message-id="${targetMessageId}"]`);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if ((current?.last_message?.id ?? 0) > (next[next.length - 1]?.id ?? 0))
                            setShowJump(true);
                        setTargetMessageId(null);
                        return;
                    }
                }
                const last = next[next.length - 1];
                if ((shouldStick || incoming.some(message => message.mine)) && last) {
                    messageEnd.current?.scrollIntoView({ behavior: firstLoad ? 'auto' : 'smooth', block: 'end' });
                    setShowJump(false);
                    scheduleMarkRead(conversationId, last.id);
                }
            });
            setSyncState(navigator.onLine ? 'live' : 'offline');
        }
        catch (exception) {
            if (!navigator.onLine || !(exception instanceof ApiError))
                setSyncState('offline');
            if (!silent)
                setError(exception instanceof Error ? exception.message : 'Could not load messages.');
        }
    };
    /** Loads one older cursor page while preserving the reader's visual scroll anchor. */
    const loadOlderMessages = async () => {
        if (!workspace || !selected || loadingOlderMessages || !hasOlderMessages || !messageState.current.length)
            return;
        const element = messageList.current;
        const previousHeight = element?.scrollHeight ?? 0;
        const before = messageState.current[0]?.id;
        if (!before)
            return;
        setLoadingOlderMessages(true);
        try {
            const response = await apiRequest<{
                data: Message[];
                meta: MessagePageMeta;
            }>(`/api/v1/chat/conversations/${selected}/messages?before=${before}&limit=60`, { workspaceId: workspace.id, silent: true });
            const next = mergeMessageWindow(response.data, messageState.current, 600, 'older');
            messageState.current = next;
            setMessages(next);
            setHasOlderMessages(Boolean(response.meta?.has_more || response.meta?.next_before));
            if ((current?.last_message?.id ?? 0) > (next[next.length - 1]?.id ?? 0))
                setShowJump(true);
            requestAnimationFrame(() => {
                if (element)
                    element.scrollTop += Math.max(0, element.scrollHeight - previousHeight);
            });
        }
        catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Could not load older messages.');
        }
        finally {
            setLoadingOlderMessages(false);
        }
    };
    /** Loads the current member's durable text draft for one conversation. */
    const loadDraft = async (conversationId: number) => {
        if (!workspace)
            return;
        try {
            const response = await apiRequest<{
                data: {
                    body: string;
                    parent_id: number | null;
                } | null;
            }>(`/api/v1/chat/conversations/${conversationId}/draft`, { workspaceId: workspace.id, silent: true });
            setBody(response.data?.body ?? '');
            draftLoadedConversation.current = conversationId;
            setDraftStatus(response.data?.body ? 'saved' : 'idle');
        }
        catch {
            draftLoadedConversation.current = conversationId;
        }
    };
    /** Stops a previously announced typing state immediately. */
    const stopTyping = () => {
        if (typingTimer.current !== null) {
            window.clearTimeout(typingTimer.current);
            typingTimer.current = null;
        }
        if (!typingActive.current || !workspace || !selected)
            return;
        typingActive.current = false;
        void apiRequest('/api/v1/chat/presence', { method: 'POST', body: JSON.stringify({ conversation_id: selected, typing: false }), workspaceId: workspace.id, silent: true }).catch(() => { });
    };
    /** Announces typing with debounce so a network request is not sent on every keystroke. */
    const announceTyping = () => {
        if (!workspace || !selected)
            return;
        if (!typingActive.current) {
            typingActive.current = true;
            void apiRequest('/api/v1/chat/presence', { method: 'POST', body: JSON.stringify({ conversation_id: selected, typing: true }), workspaceId: workspace.id, silent: true }).catch(() => { });
        }
        if (typingTimer.current !== null)
            window.clearTimeout(typingTimer.current);
        typingTimer.current = window.setTimeout(() => stopTyping(), 1600);
    };
    /** Stores a text message in the reconnect outbox and immediately reflects the queued state. */
    const queueOutboxMessage = (conversationId: number, text: string, clientMessageId: string, errorMessage?: string) => {
        if (!workspace)
            return;
        setOutbox(currentItems => {
            const existing = currentItems.find(item => item.clientMessageId === clientMessageId);
            const next = existing
                ? currentItems.map(item => item.clientMessageId === clientMessageId ? { ...item, status: 'failed' as const, error: errorMessage, attempts: item.attempts + 1 } : item)
                : [...currentItems, { clientMessageId, conversationId, body: text, createdAt: new Date().toISOString(), attempts: 0, status: errorMessage ? 'failed' as const : 'queued' as const, error: errorMessage }];
            writeOutbox(workspace.id, next);
            return next;
        });
    };
    /** Notifies sibling browser tabs that shared chat state changed. */
    const notifySiblingTabs = (conversationId: number) => {
        tabChannel.current?.postMessage({ type: 'chat.changed', conversationId, at: Date.now() });
    };
    /** Flushes queued text messages sequentially using their stable idempotency keys. */
    const flushOutbox = async () => {
        if (!workspace || !navigator.onLine)
            return;
        const pending = readOutbox(workspace.id);
        if (!pending.length) {
            setOutbox([]);
            setSyncState('live');
            return;
        }
        setSyncState('reconnecting');
        let remaining = [...pending];
        for (const item of pending) {
            try {
                await apiRequest(`/api/v1/chat/conversations/${item.conversationId}/messages`, {
                    method: 'POST',
                    body: JSON.stringify({ body: item.body, client_message_id: item.clientMessageId, client_sent_at: item.createdAt }),
                    workspaceId: workspace.id,
                    silent: true,
                });
                remaining = remaining.filter(candidate => candidate.clientMessageId !== item.clientMessageId);
                writeOutbox(workspace.id, remaining);
                setOutbox([...remaining]);
                notifySiblingTabs(item.conversationId);
                if (selected === item.conversationId)
                    await loadMessages(item.conversationId, true);
            }
            catch (exception) {
                if (exception instanceof ApiError && exception.status < 500) {
                    remaining = remaining.map(candidate => candidate.clientMessageId === item.clientMessageId ? { ...candidate, status: 'failed' as const, attempts: candidate.attempts + 1, error: exception.message } : candidate);
                    writeOutbox(workspace.id, remaining);
                    setOutbox([...remaining]);
                    continue;
                }
                setSyncState('offline');
                break;
            }
        }
        if (navigator.onLine && !remaining.some(item => item.status === 'queued'))
            setSyncState('live');
    };
    /** Retries one failed outbox message without changing its idempotency identity. */
    const retryOutboxMessage = async (clientMessageId: string) => {
        if (!workspace)
            return;
        const next = outbox.map(item => item.clientMessageId === clientMessageId ? { ...item, status: 'queued' as const, error: undefined } : item);
        writeOutbox(workspace.id, next);
        setOutbox(next);
        await flushOutbox();
    };
    useEffect(() => {
        void loadConversations(false);
        void loadInbox(true);
        if (!workspace)
            return;
        setOutbox(readOutbox(workspace.id));
        const id = window.setInterval(() => { void loadConversations(true); void loadInbox(true); }, document.hidden ? 15000 : 6000);
        return () => window.clearInterval(id);
    }, [workspace?.id]);
    useEffect(() => {
        if (!selected) {
            setMobilePanel('list');
            return;
        }
        setDetailsOpen(false);
        setThreadRoot(null);
        setThreadData(null);
        activeConversation.current = null;
        messageState.current = [];
        draftLoadedConversation.current = null;
        setMessages([]);
        setBody('');
        setUnreadStartId(null);
        setShowJump(false);
        setFiles([]);
        setMobilePanel('chat');
        setHasOlderMessages(false);
        void loadDraft(selected);
        void loadMessages(selected, false);
        messagePollCount.current = 0;
        const id = window.setInterval(() => {
            if (document.hidden || !navigator.onLine)
                return;
            messagePollCount.current += 1;
            void loadMessages(selected, true, messagePollCount.current % 5 === 0);
        }, 3000);
        return () => {
            window.clearInterval(id);
            stopTyping();
        };
    }, [selected, workspace?.id]);
    useEffect(() => {
        if (!selected || !workspace || draftLoadedConversation.current !== selected)
            return;
        setDraftStatus('saving');
        const timer = window.setTimeout(() => {
            void apiRequest(`/api/v1/chat/conversations/${selected}/draft`, {
                method: 'PUT',
                body: JSON.stringify({ body }),
                workspaceId: workspace.id,
                silent: true,
            }).then(() => setDraftStatus(body.trim() ? 'saved' : 'idle')).catch(() => setDraftStatus('idle'));
        }, 700);
        return () => window.clearTimeout(timer);
    }, [body, selected, workspace?.id]);
    useEffect(() => {
        if (!selected || !workspace)
            return;
        const echo = getRealtime();
        if (!echo)
            return;
        const channel = `workspace.${workspace.id}.chat.${selected}`;
        const subscription = echo.private(channel);
        /** Refreshes active chat state after a realtime message event. */
        const refresh = () => {
            void loadMessages(selected, true, true);
            void loadConversations(true);
            void loadInbox(true);
            void loadConversationContext(selected);
            if (threadRoot)
                void openThread(threadRoot, true);
        };
        subscription.listen('.chat.message.created', refresh).listen('.chat.message.updated', refresh).listen('.chat.message.deleted', refresh).listen('.chat.message.reaction', refresh).listen('.chat.typing', () => void loadConversations(true));
        return () => { echo.leave(channel); };
    }, [selected, workspace?.id, threadRoot?.id]);
    useEffect(() => {
        if (!workspace)
            return;
        /** Refreshes current-user online presence without changing typing state. */
        const ping = () => void apiRequest('/api/v1/chat/presence', { method: 'POST', body: JSON.stringify({ conversation_id: selected, typing: false }), workspaceId: workspace.id, silent: true }).catch(() => { });
        ping();
        const id = window.setInterval(ping, 30000);
        return () => window.clearInterval(id);
    }, [selected, workspace?.id]);
    useEffect(() => {
        if (!workspace)
            return;
        /** Marks chat offline immediately and flushes the idempotent outbox after reconnect. */
        const online = () => { setSyncState('reconnecting'); void flushOutbox(); };
        /** Prevents background polling from presenting stale chat as connected. */
        const offline = () => setSyncState('offline');
        window.addEventListener('online', online);
        window.addEventListener('offline', offline);
        if (navigator.onLine)
            void flushOutbox();
        else
            setSyncState('offline');
        return () => { window.removeEventListener('online', online); window.removeEventListener('offline', offline); };
    }, [workspace?.id]);
    useEffect(() => {
        if (!workspace || typeof BroadcastChannel === 'undefined')
            return;
        const channel = new BroadcastChannel(`workintel-chat:${workspace.id}`);
        tabChannel.current = channel;
        /** Synchronizes active conversation state after another browser tab mutates chat. */
        channel.onmessage = event => {
            const conversationId = Number(event.data?.conversationId ?? 0);
            void loadConversations(true);
            void loadInbox(true);
            if (selected && selected === conversationId) {
                void loadMessages(selected, true, true);
                void loadConversationContext(selected);
            }
        };
        return () => { channel.close(); if (tabChannel.current === channel)
            tabChannel.current = null; };
    }, [workspace?.id, selected]);
    useEffect(() => {
        if (!workspace)
            return;
        apiRequest<{
            data: CreationOptions;
        }>('/api/v1/chat/options', { workspaceId: workspace.id, silent: true })
            .then(response => { setOptions(response.data); setOptionsLoaded(true); })
            .catch(() => setOptionsLoaded(true));
    }, [newOpen, detailsOpen, actionMessage?.id, workspace?.id]);
    useEffect(() => {
        if (!workspace)
            return;
        void loadPublicChannels();
    }, [workspace?.id]);
    useEffect(() => {
        if (!workspace || !current) {
            setResources([]);
            setContextData({ pinned: [], bookmarks: [], files: [] });
            return;
        }
        void loadResources(current.id);
        void loadConversationContext(current.id);
    }, [workspace?.id, current?.id]);
    useEffect(() => () => {
        if (preview)
            URL.revokeObjectURL(preview.url);
    }, [preview]);
    /** Sends the composer with an idempotency key or queues text safely while the browser is offline. */
    const send = async () => {
        if (!workspace || !selected || (!body.trim() && !files.length))
            return;
        const text = body.trim();
        const clientMessageId = createClientMessageId();
        const clientSentAt = new Date().toISOString();
        if (!navigator.onLine) {
            if (files.length) {
                setError('Attachments require a network connection. Your text draft is still preserved.');
                return;
            }
            if (text.startsWith('/')) {
                setError('Slash commands must be sent while online.');
                return;
            }
            queueOutboxMessage(selected, text, clientMessageId);
            setBody('');
            setDraftStatus('idle');
            setSyncState('offline');
            return;
        }
        setSending(true);
        setError('');
        try {
            const form = new FormData();
            if (text)
                form.append('body', text);
            form.append('client_message_id', clientMessageId);
            form.append('client_sent_at', clientSentAt);
            files.forEach(file => form.append('attachments[]', file));
            await apiRequest(`/api/v1/chat/conversations/${selected}/messages`, { method: 'POST', body: form, workspaceId: workspace.id });
            stopTyping();
            setBody('');
            setFiles([]);
            setDraftStatus('idle');
            await apiRequest(`/api/v1/chat/conversations/${selected}/draft`, { method: 'DELETE', workspaceId: workspace.id, silent: true }).catch(() => { });
            await loadMessages(selected, true);
            await loadConversations(true);
            notifySiblingTabs(selected);
            requestAnimationFrame(() => jumpToLatest('smooth'));
        }
        catch (exception) {
            if (!files.length && text && !text.startsWith('/') && (!(exception instanceof ApiError) || exception.status >= 500)) {
                queueOutboxMessage(selected, text, clientMessageId, exception instanceof Error ? exception.message : 'Waiting for network retry.');
                setBody('');
                setSyncState('offline');
            }
            else {
                setError(exception instanceof Error ? exception.message : 'Could not send message.');
            }
        }
        finally {
            setSending(false);
        }
    };
    /** Sends a reply into the open message thread. */
    const sendThreadReply = async () => {
        if (!workspace || !selected || !threadRoot || !threadBody.trim())
            return;
        setThreadSending(true);
        try {
            const form = new FormData();
            form.append('body', threadBody.trim());
            form.append('parent_id', String(threadRoot.id));
            form.append('client_message_id', createClientMessageId());
            form.append('client_sent_at', new Date().toISOString());
            await apiRequest(`/api/v1/chat/conversations/${selected}/messages`, { method: 'POST', body: form, workspaceId: workspace.id });
            setThreadBody('');
            await openThread(threadRoot, true);
            await loadMessages(selected, true);
            await loadConversations(true);
        }
        catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Could not send thread reply.');
        }
        finally {
            setThreadSending(false);
        }
    };
    /** Opens one message thread in the professional side panel. */
    const openThread = async (message: Message, silent = false) => {
        if (!workspace)
            return;
        const rootId = message.parent?.id ?? message.id;
        try {
            const response = await apiRequest<{
                data: ThreadData;
            }>(`/api/v1/chat/messages/${rootId}/thread`, { workspaceId: workspace.id, silent });
            setThreadRoot(response.data.root);
            setThreadData(response.data);
            setDetailsOpen(true);
            setMobilePanel('details');
            setMenuMessageId(null);
        }
        catch (exception) {
            if (!silent)
                setError(exception instanceof Error ? exception.message : 'Could not open thread.');
        }
    };
    /** Toggles whether the viewer follows the open thread. */
    const toggleThreadFollow = async () => {
        if (!workspace || !threadRoot || !threadData)
            return;
        const following = !threadData.following;
        await apiRequest(`/api/v1/chat/messages/${threadRoot.id}/thread/follow`, { method: 'PUT', body: JSON.stringify({ following }), workspaceId: workspace.id });
        setThreadData({ ...threadData, following });
    };
    /** Toggles an emoji reaction and refreshes the affected conversation. */
    const react = async (message: Message, emoji: string) => {
        if (!workspace)
            return;
        await apiRequest(`/api/v1/chat/messages/${message.id}/reaction`, { method: 'POST', body: JSON.stringify({ emoji }), workspaceId: workspace.id });
        await loadMessages(message.conversation_id, true);
        if (threadRoot)
            await openThread(threadRoot, true);
    };
    /** Toggles the pinned state for a message. */
    const pin = async (message: Message) => {
        if (!workspace)
            return;
        await apiRequest(`/api/v1/chat/messages/${message.id}/pin`, { method: 'POST', body: '{}', workspaceId: workspace.id });
        await loadMessages(message.conversation_id, true);
        if (selected === message.conversation_id)
            await loadConversationContext(message.conversation_id);
        setMenuMessageId(null);
    };
    /** Toggles a private saved-message bookmark. */
    const saveMessage = async (message: Message) => {
        if (!workspace)
            return;
        await apiRequest(`/api/v1/chat/messages/${message.id}/save`, { method: 'POST', body: '{}', workspaceId: workspace.id });
        await loadMessages(message.conversation_id, true);
        if (selected === message.conversation_id)
            await loadConversationContext(message.conversation_id);
        if (searchMode === 'saved')
            await loadSaved();
        setMenuMessageId(null);
    };
    /** Opens the edit dialog for a message owned by the viewer. */
    const startEdit = (message: Message) => {
        setEditMessage(message);
        setEditBody(message.body ?? '');
        setMenuMessageId(null);
    };
    /** Persists one message edit and refreshes the active conversation. */
    const saveEdit = async () => {
        if (!workspace || !editMessage || !editBody.trim())
            return;
        await apiRequest(`/api/v1/chat/messages/${editMessage.id}`, { method: 'PUT', body: JSON.stringify({ body: editBody.trim() }), workspaceId: workspace.id });
        setEditMessage(null);
        setEditBody('');
        await loadMessages(editMessage.conversation_id, true);
        if (threadRoot)
            await openThread(threadRoot, true);
    };
    /** Soft-deletes a message after explicit user confirmation. */
    const deleteMessage = async (message: Message) => {
        if (!workspace || !await confirmAction({ title: 'Delete message?', description: 'The audit history remains available according to workspace policy.', confirmLabel: 'Delete', danger: true }))
            return;
        await apiRequest(`/api/v1/chat/messages/${message.id}`, { method: 'DELETE', workspaceId: workspace.id });
        setMenuMessageId(null);
        await loadMessages(message.conversation_id, true);
        if (threadRoot)
            await openThread(threadRoot, true);
    };
    /** Loads edit history for an owned message or moderator review. */
    const showHistory = async (message: Message) => {
        if (!workspace)
            return;
        const response = await apiRequest<{
            data: EditVersion[];
        }>(`/api/v1/chat/messages/${message.id}/history`, { workspaceId: workspace.id });
        setEditHistory(response.data);
        setHistoryMessage(message);
        setMenuMessageId(null);
    };
    /** Opens the forward dialog for a message and resets target state. */
    const startForward = (message: Message) => {
        setForwardMessage(message);
        setForwardConversationId('');
        setForwardNote('');
        setMenuMessageId(null);
    };
    /** Forwards a message into another conversation visible to the current member. */
    const submitForward = async () => {
        if (!workspace || !forwardMessage || !forwardConversationId)
            return;
        const response = await apiRequest<{
            data: Message;
        }>(`/api/v1/chat/messages/${forwardMessage.id}/forward`, {
            method: 'POST',
            body: JSON.stringify({ conversation_id: Number(forwardConversationId), note: forwardNote.trim() || null }),
            workspaceId: workspace.id,
        });
        setForwardMessage(null);
        setForwardConversationId('');
        setForwardNote('');
        setSelected(response.data.conversation_id);
        await loadConversations(true);
    };
    /** Creates a professional poll in the active conversation. */
    const createPoll = async () => {
        if (!workspace || !selected)
            return;
        const optionsToSend = pollOptions.map(option => option.trim()).filter(Boolean);
        if (!pollQuestion.trim() || optionsToSend.length < 2)
            return;
        await apiRequest(`/api/v1/chat/conversations/${selected}/polls`, {
            method: 'POST',
            body: JSON.stringify({ question: pollQuestion.trim(), options: optionsToSend, allows_multiple: pollMultiple, closes_at: pollClosesAt || null }),
            workspaceId: workspace.id,
        });
        setPollOpen(false);
        setPollQuestion('');
        setPollOptions(['', '']);
        setPollMultiple(false);
        setPollClosesAt('');
        await loadMessages(selected, true);
        await loadConversations(true);
    };
    /** Updates the viewer's poll selection while respecting single/multiple-choice semantics. */
    const votePoll = async (message: Message, optionId: number) => {
        if (!workspace || !message.poll || message.poll.closed)
            return;
        const selectedIds = message.poll.allows_multiple
            ? message.poll.options.filter(option => option.mine && option.id !== optionId).map(option => option.id).concat(message.poll.options.find(option => option.id === optionId)?.mine ? [] : [optionId])
            : [optionId];
        await apiRequest(`/api/v1/chat/polls/${message.poll.id}/vote`, { method: 'POST', body: JSON.stringify({ option_ids: selectedIds }), workspaceId: workspace.id });
        await loadMessages(message.conversation_id, true);
        if (threadRoot)
            await openThread(threadRoot, true);
    };
    /** Downloads a private chat attachment through the authorized API. */
    const download = async (attachment: Attachment) => {
        if (!workspace)
            return;
        const response = await apiDownload(attachment.url, workspace.id);
        const url = URL.createObjectURL(response.blob);
        const element = document.createElement('a');
        element.href = url;
        element.download = response.filename;
        element.click();
        URL.revokeObjectURL(url);
    };
    /** Downloads one recent-file entry from the authorized conversation context panel. */
    const downloadContextFile = async (file: ContextFile) => {
        if (!workspace)
            return;
        const response = await apiDownload(file.url, workspace.id);
        const url = URL.createObjectURL(response.blob);
        const element = document.createElement('a');
        element.href = url;
        element.download = response.filename || file.filename;
        element.click();
        URL.revokeObjectURL(url);
    };
    /** Opens an authenticated media attachment in an in-app preview modal. */
    const previewAttachment = async (attachment: Attachment) => {
        if (!workspace)
            return;
        const response = await apiDownload(attachment.url, workspace.id);
        if (preview)
            URL.revokeObjectURL(preview.url);
        setPreview({ attachment, url: URL.createObjectURL(response.blob) });
    };
    /** Executes an advanced workspace-scoped message search. */
    const doSearch = async () => {
        if (!workspace || search.trim().length < 1) {
            setResults([]);
            setSearchMode('none');
            return;
        }
        const response = await apiRequest<{
            data: Message[];
        }>(`/api/v1/chat/search?q=${encodeURIComponent(search.trim())}`, { workspaceId: workspace.id });
        setResults(response.data);
        setSearchMode('search');
    };
    /** Loads the current member's private Saved Messages collection. */
    const loadSaved = async () => {
        if (!workspace)
            return;
        const response = await apiRequest<{
            data: Message[];
        }>('/api/v1/chat/saved', { workspaceId: workspace.id });
        setResults(response.data);
        setSearchMode('saved');
        setSearch('');
    };
    /** Loads the collaboration inbox across unread mentions, followed threads and unread direct messages. */
    const loadInbox = async (silent = true) => {
        if (!workspace)
            return;
        const response = await apiRequest<{
            data: CollaborationInbox;
        }>('/api/v1/chat/inbox', { workspaceId: workspace.id, silent }).catch(() => null);
        if (response)
            setInbox(response.data);
    };
    /** Applies a private inbox triage action and replaces the derived inbox with the server-authoritative result. */
    const triageActivity = async (action: 'done' | 'reopen' | 'snooze' | 'follow_up' | 'read_all', activityKey?: string) => { if (!workspace)
        return; const until = action === 'snooze' ? new Date(Date.now() + 60 * 60 * 1000).toISOString() : action === 'follow_up' ? new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString() : undefined; const response = await apiRequest<{
        data: CollaborationInbox;
    }>('/api/v1/chat/inbox/triage', { method: 'POST', workspaceId: workspace.id, body: JSON.stringify({ action, activity_key: activityKey, until }) }); setInbox(response.data); };
    /** Loads the dedicated chat notification matrix backed by shared workspace notification preferences. */
    const loadChatPreferences = async () => { if (!workspace)
        return; const response = await apiRequest<{
        data: ChatNotificationPreference[];
    }>('/api/v1/chat/notification-preferences', { workspaceId: workspace.id }); setChatPreferences(response.data); setNotificationOpen(true); };
    /** Saves all four allowlisted chat preference rows atomically from the current dialog state. */
    const saveChatPreferences = async () => { if (!workspace)
        return; const response = await apiRequest<{
        data: ChatNotificationPreference[];
    }>('/api/v1/chat/notification-preferences', { method: 'PUT', workspaceId: workspace.id, body: JSON.stringify({ preferences: chatPreferences }) }); setChatPreferences(response.data); setNotificationOpen(false); };
    /** Loads contextual pins, bookmarks and recent files for one authorized conversation. */
    const loadConversationContext = async (conversationId: number) => {
        if (!workspace)
            return;
        const response = await apiRequest<{
            data: ConversationContext;
        }>(`/api/v1/chat/conversations/${conversationId}/context?limit=20`, { workspaceId: workspace.id, silent: true }).catch(() => null);
        if (response)
            setContextData(response.data);
    };
    /** Loads the next cursor page for each context collection and merges it without duplicates. */
    const loadMoreContext = async () => { if (!workspace || !selected || contextLoadingMore)
        return; const meta = contextData.meta ?? {}; if (!meta.pin_next && !meta.bookmark_next && !meta.file_next)
        return; setContextLoadingMore(true); try {
        const params = new URLSearchParams({ limit: '20' });
        params.set('pin_before', String(meta.pin_next ?? 0));
        params.set('bookmark_before', String(meta.bookmark_next ?? 0));
        params.set('file_before', String(meta.file_next ?? 0));
        const response = await apiRequest<{
            data: ConversationContext;
        }>(`/api/v1/chat/conversations/${selected}/context?${params}`, { workspaceId: workspace.id });
        setContextData(current => ({ pinned: [...current.pinned, ...response.data.pinned.filter(row => !current.pinned.some(existing => existing.id === row.id))], bookmarks: [...current.bookmarks, ...response.data.bookmarks.filter(row => !current.bookmarks.some(existing => existing.id === row.id))], files: [...current.files, ...response.data.files.filter(row => !current.files.some(existing => existing.id === row.id))], meta: response.data.meta }));
    }
    finally {
        setContextLoadingMore(false);
    } };
    /** Clears visible pins or private bookmarks through one bounded server operation. */
    const bulkContext = async (action: 'unpin' | 'delete_bookmarks') => { if (!workspace || !selected)
        return; const ids = action === 'unpin' ? contextData.pinned.map(row => row.id) : contextData.bookmarks.map(row => row.id); if (!ids.length)
        return; const response = await apiRequest<{
        data: ConversationContext;
    }>(`/api/v1/chat/conversations/${selected}/context/bulk`, { method: 'POST', workspaceId: workspace.id, body: JSON.stringify({ action, ids }) }); setContextData(response.data); };
    /** Opens a collaboration-inbox thread at its root message in the owning conversation. */
    const openInboxThread = (item: InboxThreadItem) => {
        setTargetMessageId(item.root_message_id);
        setSelected(item.conversation_id);
        setActivityOpen(false);
        setMobilePanel('chat');
    };
    /** Opens one unread direct conversation from the collaboration inbox. */
    const openInboxDirect = (conversation: Conversation) => {
        setSelected(conversation.id);
        setActivityOpen(false);
        setMobilePanel('chat');
    };
    /** Opens a search/saved result in its conversation and requests message positioning. */
    const openResult = (message: Message) => {
        setTargetMessageId(message.parent?.id ?? message.id);
        setSelected(message.conversation_id);
        setResults([]);
        setSearchMode('none');
        setSearch('');
        setMobilePanel('chat');
    };
    /** Loads public channels that the current member can discover and join. */
    const loadPublicChannels = async () => {
        if (!workspace)
            return;
        const response = await apiRequest<{
            data: PublicChannel[];
        }>('/api/v1/chat/public-channels', { workspaceId: workspace.id, silent: true }).catch(() => ({ data: [] as PublicChannel[] }));
        setPublicChannels(response.data);
    };
    /** Joins one discoverable public channel and opens it immediately. */
    const joinPublicChannel = async (conversationId: number) => {
        if (!workspace)
            return;
        await apiRequest(`/api/v1/chat/conversations/${conversationId}/join`, { method: 'POST', body: '{}', workspaceId: workspace.id });
        await loadConversations(true);
        await loadPublicChannels();
        setSelected(conversationId);
        setMobilePanel('chat');
    };
    /** Updates the current member's per-conversation notification delivery preference. */
    const setNotificationMode = async (mode: 'all' | 'mentions' | 'nothing') => {
        if (!workspace || !current)
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/notifications`, { method: 'PUT', body: JSON.stringify({ mode }), workspaceId: workspace.id });
        await loadConversations(true);
    };
    /** Loads links and WorkIntel resources pinned to a conversation. */
    const loadResources = async (conversationId: number) => {
        if (!workspace)
            return;
        const response = await apiRequest<{
            data: ChannelResource[];
        }>(`/api/v1/chat/conversations/${conversationId}/resources`, { workspaceId: workspace.id, silent: true }).catch(() => ({ data: [] as ChannelResource[] }));
        setResources(response.data);
    };
    /** Pins a validated link or authorized WorkIntel entity to the current conversation. */
    const addChannelResource = async () => {
        if (!workspace || !current)
            return;
        const selectedProject = options.projects.find(item => item.id === Number(resourceId));
        const selectedTask = options.tasks.find(item => item.id === Number(resourceId));
        const selectedDocument = options.documents.find(item => item.id === Number(resourceId));
        const fallbackLabel = resourceKind === 'project' ? selectedProject?.name : resourceKind === 'task' ? selectedTask?.title : resourceKind === 'document' ? selectedDocument?.filename : '';
        const label = resourceLabel.trim() || fallbackLabel || '';
        if (!label || (resourceKind === 'link' ? !resourceUrl.trim() : !resourceId))
            return;
        const payload: Record<string, unknown> = { kind: resourceKind, label };
        if (resourceKind === 'link')
            payload.url = resourceUrl.trim();
        else {
            payload.resource_id = Number(resourceId);
            payload.resource_type = resourceKind === 'document' ? 'generated_document' : resourceKind;
        }
        await apiRequest(`/api/v1/chat/conversations/${current.id}/resources`, { method: 'POST', body: JSON.stringify(payload), workspaceId: workspace.id });
        setResourceLabel('');
        setResourceUrl('');
        setResourceId('');
        await loadResources(current.id);
    };
    /** Removes one pinned conversation resource after explicit confirmation. */
    const removeChannelResource = async (resourceId: number) => {
        if (!workspace || !current || !await confirmAction({ title: 'Remove pinned resource?', description: 'The underlying WorkIntel item or link will not be deleted.', confirmLabel: 'Remove', danger: true }))
            return;
        await apiRequest(`/api/v1/chat/resources/${resourceId}`, { method: 'DELETE', workspaceId: workspace.id });
        await loadResources(current.id);
    };
    /** Locks or unlocks a governed channel using the channel administration API. */
    const toggleChannelLock = async () => {
        if (!workspace || !current)
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/channel`, { method: 'PUT', body: JSON.stringify({ is_locked: !current.is_locked }), workspaceId: workspace.id });
        await loadConversations(true);
    };
    /** Adds one active workspace member to the current governed channel. */
    const addChannelMember = async (memberId: number) => {
        if (!workspace || !current)
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/members`, { method: 'POST', body: JSON.stringify({ member_ids: [memberId] }), workspaceId: workspace.id });
        await loadConversations(true);
    };
    /** Updates one member's channel role. */
    const updateChannelRole = async (memberId: number, role: string) => {
        if (!workspace || !current)
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/members/${memberId}/role`, { method: 'PUT', body: JSON.stringify({ role }), workspaceId: workspace.id });
        await loadConversations(true);
    };
    /** Removes one member from the current governed channel. */
    const removeChannelMember = async (memberId: number) => {
        if (!workspace || !current || !await confirmAction({ title: 'Remove channel member?', description: 'Remove this member from the channel?', confirmLabel: 'Remove', danger: true }))
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/members/${memberId}`, { method: 'DELETE', workspaceId: workspace.id });
        await loadConversations(true);
    };
    /** Leaves the active public/private channel and returns to the conversation list. */
    const leaveCurrentChannel = async () => {
        if (!workspace || !current || !await confirmAction({ title: 'Leave channel?', description: 'You may lose access to future channel messages until you are added again.', confirmLabel: 'Leave channel', danger: true }))
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/leave`, { method: 'POST', body: '{}', workspaceId: workspace.id });
        setSelected(null);
        setDetailsOpen(false);
        setMobilePanel('list');
        await loadConversations(true);
        await loadPublicChannels();
    };
    /** Archives or restores the active governed channel. */
    const setChannelArchived = async (archived: boolean) => {
        if (!workspace || !current)
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/channel`, { method: 'PUT', body: JSON.stringify({ archived }), workspaceId: workspace.id });
        setDetailsOpen(false);
        setMobilePanel('list');
        await loadConversations(true);
    };
    /** Opens the message-to-work workflow modal and ensures project choices are available. */
    const startMessageAction = async (message: Message, type: MessageActionType) => {
        if (!workspace)
            return;
        setActionMessage(message);
        setActionType(type);
        setActionTitle((message.body ?? '').slice(0, 180));
        setActionProjectId(current?.project?.id ? String(current.project.id) : '');
        setMenuMessageId(null);
        if (!options.projects.length) {
            const response = await apiRequest<{
                data: CreationOptions;
            }>('/api/v1/chat/options', { workspaceId: workspace.id, silent: true }).catch(() => null);
            if (response)
                setOptions(response.data);
        }
    };
    /** Converts a source message into a real WorkIntel task, approval request or safety incident. */
    const submitMessageAction = async () => {
        if (!workspace || !actionMessage || !actionTitle.trim())
            return;
        setActionBusy(true);
        try {
            const payload: Record<string, unknown> = { action: actionType, title: actionTitle.trim() };
            if (actionType === 'task' && actionProjectId)
                payload.project_id = Number(actionProjectId);
            if (actionType === 'incident')
                payload.severity = 'medium';
            await apiRequest(`/api/v1/chat/messages/${actionMessage.id}/actions`, { method: 'POST', body: JSON.stringify(payload), workspaceId: workspace.id });
            setActionMessage(null);
            await loadMessages(actionMessage.conversation_id, true);
            await loadConversations(true);
        }
        catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Could not create workspace action.');
        }
        finally {
            setActionBusy(false);
        }
    };
    /** Opens the governed moderation dialog without browser-native prompt controls. */
    const moderateEnterpriseMessage = (message: Message, action: 'flag' | 'redact') => {
        setModerationMessage(message);
        setModerationAction(action);
        setModerationReason('');
        setMenuMessageId(null);
    };
    /** Applies one audited enterprise moderation action after explicit dialog confirmation. */
    const submitModeration = async () => {
        if (!workspace || !moderationMessage)
            return;
        try {
            await apiRequest(`/api/v1/chat/enterprise/messages/${moderationMessage.id}/moderate`, { method: 'POST', workspaceId: workspace.id, body: JSON.stringify({ action: moderationAction, reason: moderationReason.trim() || null }) });
            const conversationId = moderationMessage.conversation_id;
            setModerationMessage(null);
            setModerationReason('');
            await loadMessages(conversationId, true);
            await loadInbox(true);
            if (selected === conversationId)
                await loadConversationContext(conversationId);
        }
        catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Could not apply moderation action.');
        }
    };
    /** Opens the private bookmark-note editor for one saved message. */
    const editBookmarkNote = (bookmark: ContextBookmark) => {
        setBookmarkNoteMessage(bookmark.message);
        setBookmarkNote(bookmark.note ?? '');
    };
    /** Saves a private bookmark note without changing the shared chat message. */
    const saveBookmarkNote = async () => {
        if (!workspace || !bookmarkNoteMessage)
            return;
        await apiRequest(`/api/v1/chat/messages/${bookmarkNoteMessage.id}/save-note`, { method: 'PUT', workspaceId: workspace.id, body: JSON.stringify({ note: bookmarkNote.trim() || null }) });
        setBookmarkNoteMessage(null);
        setBookmarkNote('');
        if (selected)
            await loadConversationContext(selected);
    };
    /** Toggles mute state for the active conversation. */
    const muteCurrent = async () => {
        if (!workspace || !current)
            return;
        await apiRequest(`/api/v1/chat/conversations/${current.id}/mute`, { method: 'PUT', body: '{}', workspaceId: workspace.id });
        await loadConversations(true);
    };
    /** Preserves scroll position, preloads older pages near the top and batches read receipts near the bottom. */
    const handleMessageScroll = () => {
        if (!selected)
            return;
        const element = messageList.current;
        if (element && element.scrollTop < 120 && hasOlderMessages)
            void loadOlderMessages();
        if (!nearBottom(element))
            return;
        setShowJump(false);
        const last = messageState.current[messageState.current.length - 1];
        if (last)
            scheduleMarkRead(selected, last.id);
    };
    if (!workspace)
        return null;
    if (loading)
        return <PageLoadingState />;
    return <Page>
    <PageHeader title="Chat & Collaboration" description="Production-grade collaboration with cursor history, offline recovery, idempotent delivery, enterprise governance and private attachments." actions={<><Button size="sm" variant="outline" onClick={() => void loadChatPreferences()}><Bell size={14}/> Notifications</Button><Button size="sm" variant="outline" onClick={() => { void loadInbox(false); setActivityOpen(true); }}><Inbox size={14}/> Activity{inbox.counts.total ? ` ${inbox.counts.total}` : ''}</Button>{optionsLoaded && !isExternalViewer && <Button size="sm" variant="primary" onClick={() => setNewOpen(true)}><Plus size={14}/> New conversation</Button>}</>}/>
    {error && <Alert tone="danger">{error}</Alert>}
    <div className={`chat-shell chat-mobile-${mobilePanel}${detailsOpen ? ' is-details-open' : ''}`}>
      <aside className="chat-sidebar" aria-label="Conversations">
        <div className="chat-search">
          <Search size={14}/>
          <Input value={search} onChange={event => { setSearch(event.target.value); if (!event.target.value && searchMode === 'search') {
        setResults([]);
        setSearchMode('none');
    } }} onKeyDown={event => event.key === 'Enter' && void doSearch()} placeholder="Search or use from:, in:, has:file…"/>
          {search && <Pressable type="button" className="chat-icon-button" aria-label="Clear search" onClick={() => { setSearch(''); setResults([]); setSearchMode('none'); }}><X size={13}/></Pressable>}
        </div>
        <div className="chat-search-help">Try <code>from:12</code> <code>in:4</code> <code>before:2026-08-01</code> <code>after:2026-07-01</code> <code>has:file</code> <code>has:link</code></div>
        <div className="chat-sidebar-shortcuts">
          <Pressable type="button" onClick={() => { void loadInbox(false); setActivityOpen(true); }}><Inbox size={13}/> Activity{inbox.counts.total > 0 && <Badge tone="accent">{inbox.counts.total}</Badge>}</Pressable>
          <Pressable type="button" className={searchMode === 'saved' ? 'is-active' : ''} onClick={() => void loadSaved()}><Bookmark size={13}/> Saved Messages</Pressable>
        </div>
        {publicChannels.length > 0 && <div className="chat-public-channels">
          <div className="chat-section-label">Public channels</div>
          {publicChannels.slice(0, 8).map(channel => <Pressable key={channel.id} type="button" onClick={() => void joinPublicChannel(channel.id)}><Hash size={13}/><span><strong>{channel.name}</strong><small>{channel.channel_mode === 'announcement' ? 'Announcement' : `${channel.member_count} members`}</small></span><Plus size={12}/></Pressable>)}
        </div>}
        {searchMode !== 'none' && <div className="chat-search-results">
          <div className="chat-section-label">{searchMode === 'saved' ? 'Saved messages' : 'Search results'} · {results.length}</div>
          {results.map(result => <Pressable key={result.id} type="button" onClick={() => openResult(result)}><strong>{result.sender?.name ?? 'Unknown'}</strong><small>{result.body || (result.forwarded ? `Forwarded from ${result.forwarded.sender}` : 'Attachment or deleted message')}</small></Pressable>)}
          {!results.length && <div className="chat-empty">No matching messages.</div>}
        </div>}
        <div className="chat-conversation-filters"><Segmented value={conversationFilter} onChange={setConversationFilter} ariaLabel="Conversation filter" options={[{ value: 'all', label: 'All' }, { value: 'unread', label: `Unread${unreadConversationCount ? ` ${unreadConversationCount}` : ''}` }, { value: 'direct', label: 'Direct' }, { value: 'channels', label: 'Channels' }]}/></div>
        <div className="chat-section-label">Conversations · {filteredConversations.length}</div>
        <div className="chat-conversation-list">
          {filteredConversations.map(conversation => {
            const peers = conversation.members.filter(member => !member.is_self && member.id !== viewerMemberId);
            const online = peers.some(member => presence.some(item => item.member_id === member.id));
            return <Pressable key={conversation.id} type="button" className={selected === conversation.id ? 'is-active' : ''} onClick={() => { setSelected(conversation.id); setMobilePanel('chat'); }}>
              <span className="chat-conv-icon">{conversation.type === 'channel' ? <Hash size={15}/> : conversation.type === 'direct' ? <MessageCircle size={15}/> : <Users size={15}/>}</span>
              <span className="chat-conv-copy"><strong>{conversation.name}</strong><small>{conversation.draft?.body ? `Draft: ${conversation.draft.body}` : conversation.last_message?.body || conversation.description || conversation.type}</small></span>
              <span className="chat-conv-meta">{online && <i className="chat-online-dot" aria-label="Online"/>}{conversation.unread_count > 0 && <Badge tone="accent">{conversation.unread_count}</Badge>}</span>
            </Pressable>;
        })}
          {!filteredConversations.length && <div className="chat-empty">{conversations.length ? 'No conversations match this filter.' : 'No conversations yet.'}</div>}
        </div>
      </aside>

      <section className="chat-main" aria-label="Active conversation">
        {current ? <>
          <header className="chat-header">
            <div className="chat-header-title">
              <Pressable type="button" className="chat-icon-button chat-mobile-back" aria-label="Back to conversations" onClick={() => setMobilePanel('list')}><ArrowLeft size={16}/></Pressable>
              <div><strong>{current.name}</strong><small>{current.type}{current.visibility ? ` · ${current.visibility}` : ''}{current.channel_mode === 'announcement' ? ' · Announcement' : ''}{current.is_locked ? ' · Locked' : ''}{current.project ? ` · ${current.project.name}` : ''}{current.task ? ` · ${current.task.title}` : ''}{current.members.some(member => member.collaboration_type && member.collaboration_type !== 'internal') ? ' · External' : ''}{current.legal_hold ? ' · Legal hold' : ''}</small></div>
            </div>
            <div className="chat-header-tools">
              <span className={`chat-sync-state is-${syncState}`} title={syncState === 'live' ? 'Realtime/polling sync active' : syncState === 'reconnecting' ? 'Reconnecting and flushing queued messages' : 'Offline — text messages can be queued'}>{syncState === 'offline' ? <WifiOff size={12}/> : syncState === 'reconnecting' ? <RefreshCw size={12}/> : null}{syncState}</span>
              <div className="chat-header-members">{current.members.slice(0, 4).map(member => <Avatar key={member.id} name={member.name} size="sm"/>)}</div>
              {canEnterprise && <Pressable type="button" className="chat-icon-button" aria-label="Enterprise controls" title="Enterprise controls" onClick={() => setEnterpriseOpen(true)}><Shield size={16}/></Pressable>}
              <Pressable type="button" className="chat-icon-button" aria-label="Create poll" title="Create poll" onClick={() => setPollOpen(true)}><BarChart3 size={16}/></Pressable>
              <Pressable type="button" className="chat-icon-button chat-details-toggle" aria-label="Conversation details" onClick={() => { setThreadRoot(null); setThreadData(null); setDetailsOpen(true); setMobilePanel('details'); }}><Info size={16}/></Pressable>
            </div>
          </header>

          <div className="chat-messages" ref={messageList} onScroll={handleMessageScroll}>
            {hasOlderMessages && <div className="chat-history-loader"><Pressable type="button" disabled={loadingOlderMessages} onClick={() => void loadOlderMessages()}>{loadingOlderMessages ? 'Loading older messages…' : 'Load older messages'}</Pressable></div>}
            {messages.map(message => <div className="chat-message-row" key={message.id} data-message-id={message.id}>
              {unreadStartId === message.id && <div className="chat-unread-divider"><span>Unread messages</span></div>}
              <MessageCard message={message} members={current.members} canModerate={canModerateCurrent} formatTime={formatTime} onThread={() => void openThread(message)} onReact={emoji => void react(message, emoji)} onPin={() => void pin(message)} onSave={() => void saveMessage(message)} onEdit={() => startEdit(message)} onDelete={() => void deleteMessage(message)} onHistory={() => void showHistory(message)} onForward={() => startForward(message)} onAction={type => void startMessageAction(message, type)} onModerate={action => void moderateEnterpriseMessage(message, action)} onDownload={attachment => void download(attachment)} onPreview={attachment => void previewAttachment(attachment)} onVote={optionId => void votePoll(message, optionId)} menuOpen={menuMessageId === message.id} onToggleMenu={() => setMenuMessageId(value => value === message.id ? null : message.id)}/>
            </div>)}
            {outbox.filter(item => item.conversationId === selected).map(item => <div className={`chat-outbox-message is-${item.status}`} key={item.clientMessageId}>
              <div><strong>{item.status === 'queued' ? 'Queued for delivery' : 'Delivery failed'}</strong><span>{item.body}</span><small>{item.error ?? 'This message will use the same idempotency key when retried.'}</small></div>
              {item.status === 'failed' && <Pressable type="button" onClick={() => void retryOutboxMessage(item.clientMessageId)}><RefreshCw size={12}/> Retry</Pressable>}
            </div>)}
            <div ref={messageEnd}/>
          </div>

          {showJump && <Pressable type="button" className="chat-jump-latest" onClick={() => jumpToLatest('smooth')}><ChevronDown size={14}/> Jump to latest</Pressable>}
          <footer className="chat-composer">
            {files.length > 0 && <div className="chat-file-strip">{files.map((file, index) => <span key={`${file.name}-${index}`}>{file.name}<Pressable type="button" aria-label={`Remove ${file.name}`} onClick={() => setFiles(items => items.filter((_, itemIndex) => itemIndex !== index))}><X size={11}/></Pressable></span>)}</div>}
            <Textarea rows={2} value={body} onChange={event => { setBody(event.target.value); announceTyping(); }} onBlur={() => stopTyping()} onKeyDown={event => { if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void send();
        } }} placeholder={canPostCurrent ? 'Write a message or use /help…' : 'This channel is read-only for your role.'} disabled={!canPostCurrent}/>
            <div className="chat-composer-actions">
              <MediaFileField compact workspaceId={workspace?.id ?? 0} label="Chat attachment" multiple maxFiles={8} disabled={!canPostCurrent} onFiles={next => setFiles(current => [...current, ...next].slice(0, 8))}/>
              <Pressable type="button" className="chat-poll-trigger" disabled={!canPostCurrent} onClick={() => setPollOpen(true)}><BarChart3 size={13}/> Poll</Pressable>
              <span className="chat-hint"><AtSign size={12}/> {otherMembers.slice(0, 4).map(member => <Pressable key={member.id} type="button" onClick={() => setBody(value => `${value}${value && !value.endsWith(' ') ? ' ' : ''}@[member:${member.id}] `)}>@{member.name.split(' ')[0]}</Pressable>)}</span>
              <span className="chat-command-hint" title="Slash commands">/help · /task · /assign · /poll · /status</span>
              <span className={`chat-draft-status is-${draftStatus}`}>{draftStatus === 'saving' ? 'Saving draft…' : draftStatus === 'saved' ? 'Draft saved' : ''}</span>
              {outbox.some(item => item.conversationId === selected) && <span className="chat-outbox-count">{outbox.filter(item => item.conversationId === selected).length} queued</span>}
              <Button size="sm" variant="primary" loading={sending} disabled={!canPostCurrent || (!body.trim() && !files.length)} onClick={() => void send()}><Send size={13}/> Send</Button>
            </div>
          </footer>
        </> : <div className="chat-empty-main"><MessageCircle size={28}/><strong>Select a conversation</strong><span>Messages will appear here.</span></div>}
      </section>

      <aside className="chat-details" aria-label={threadRoot ? 'Message thread' : 'Conversation details'}>
        {current && (threadRoot && threadData ? <ThreadPanel data={threadData} members={current.members} body={threadBody} sending={threadSending} formatTime={formatTime} onBodyChange={setThreadBody} onSend={() => void sendThreadReply()} onClose={() => { setThreadRoot(null); setThreadData(null); setDetailsOpen(false); setMobilePanel('chat'); }} onFollow={() => void toggleThreadFollow()} onReact={(message, emoji) => void react(message, emoji)} onVote={(message, optionId) => void votePoll(message, optionId)} onDownload={attachment => void download(attachment)} onPreview={attachment => void previewAttachment(attachment)}/> : <ConversationDetails conversation={current} presence={presence} viewerMemberId={viewerMemberId} canGovern={canGovernCurrent} canModerate={canModerateCurrent} people={options.people} resources={resources} contextData={contextData} resourceLabel={resourceLabel} resourceUrl={resourceUrl} resourceKind={resourceKind} resourceId={resourceId} resourceOptions={options} onResourceLabel={setResourceLabel} onResourceUrl={setResourceUrl} onResourceKind={value => { setResourceKind(value); setResourceId(''); setResourceUrl(''); }} onResourceId={setResourceId} onAddResource={() => void addChannelResource()} onRemoveResource={id => void removeChannelResource(id)} onOpenMessage={message => { openResult(message); setDetailsOpen(false); }} onDownloadFile={file => void downloadContextFile(file)} onBookmarkNote={editBookmarkNote} onLoadMoreContext={() => void loadMoreContext()} contextLoadingMore={contextLoadingMore} onBulkContext={action => void bulkContext(action)} onAddMember={id => void addChannelMember(id)} onRoleChange={(id, role) => void updateChannelRole(id, role)} onRemoveMember={id => void removeChannelMember(id)} onNotification={mode => void setNotificationMode(mode)} onToggleLock={() => void toggleChannelLock()} onLeave={() => void leaveCurrentChannel()} onArchive={() => void setChannelArchived(true)} onClose={() => { setDetailsOpen(false); setMobilePanel('chat'); }} onMute={() => void muteCurrent()}/>)}
      </aside>
    </div>

    <Modal open={activityOpen} onClose={() => setActivityOpen(false)} title="Collaboration Activity" description="Triage mentions, followed threads and direct messages across devices." size="lg">
      <div className="chat-activity-summary"><Badge tone={inbox.counts.mentions ? 'accent' : 'neutral'}>{inbox.counts.mentions} mentions</Badge><Badge tone={inbox.counts.threads ? 'accent' : 'neutral'}>{inbox.counts.threads} threads</Badge><Badge tone={inbox.counts.direct ? 'accent' : 'neutral'}>{inbox.counts.direct} direct</Badge>{inbox.counts.total > 0 && <Button size="sm" variant="ghost" onClick={() => void triageActivity('read_all')}><CheckCircle2 size={12}/> Mark all done</Button>}</div>
      <div className="chat-activity-grid">
        <section><div className="chat-section-label"><AtSign size={12}/> Mentions</div>{inbox.mentions.map(message => <div key={message.id} className="chat-activity-item chat-activity-triage"><Pressable type="button" onClick={() => { openResult(message); setActivityOpen(false); }}><strong>{message.sender?.name ?? 'Member'}</strong><span>{displayBody(message.body, conversations.find(row => row.id === message.conversation_id)?.members ?? []) || 'Mentioned you in a message'}</span><small>{message.created_at ? formatTime(message.created_at) : ''}</small></Pressable>{message.activity_key && <div><Button size="sm" variant="ghost" onClick={() => void triageActivity('done', message.activity_key)}>Done</Button><Button size="sm" variant="ghost" onClick={() => void triageActivity('snooze', message.activity_key)}><Clock3 size={11}/> 1h</Button><Button size="sm" variant="ghost" onClick={() => void triageActivity('follow_up', message.activity_key)}>Tomorrow</Button></div>}</div>)}{!inbox.mentions.length && <div className="chat-empty">No unread mentions.</div>}</section>
        <section><div className="chat-section-label"><ListTree size={12}/> Followed threads</div>{inbox.threads.map(item => <div key={item.root_message_id} className="chat-activity-item chat-activity-triage"><Pressable type="button" onClick={() => openInboxThread(item)}><strong>{item.conversation_name}</strong><span>{item.root_body || 'Thread'}</span><small>{item.unread_count} new repl{item.unread_count === 1 ? 'y' : 'ies'} · latest by {item.latest_reply.sender?.name ?? 'member'}</small></Pressable>{item.activity_key && <div><Button size="sm" variant="ghost" onClick={() => void triageActivity('done', item.activity_key)}>Done</Button><Button size="sm" variant="ghost" onClick={() => void triageActivity('snooze', item.activity_key)}><Clock3 size={11}/> 1h</Button><Button size="sm" variant="ghost" onClick={() => void triageActivity('follow_up', item.activity_key)}>Tomorrow</Button></div>}</div>)}{!inbox.threads.length && <div className="chat-empty">No unread followed threads.</div>}</section>
        <section><div className="chat-section-label"><MessageCircle size={12}/> Direct messages</div>{inbox.direct.map(conversation => <div key={conversation.id} className="chat-activity-item chat-activity-triage"><Pressable type="button" onClick={() => openInboxDirect(conversation)}><strong>{conversation.name}</strong><span>{conversation.last_message?.body || 'Unread direct message'}</span><small>{conversation.unread_count} unread</small></Pressable>{conversation.activity_key && <div><Button size="sm" variant="ghost" onClick={() => void triageActivity('done', conversation.activity_key)}>Done</Button><Button size="sm" variant="ghost" onClick={() => void triageActivity('snooze', conversation.activity_key)}><Clock3 size={11}/> 1h</Button><Button size="sm" variant="ghost" onClick={() => void triageActivity('follow_up', conversation.activity_key)}>Tomorrow</Button></div>}</div>)}{!inbox.direct.length && <div className="chat-empty">No unread direct messages.</div>}</section>
      </div>
    </Modal>

    <Modal open={notificationOpen} onClose={() => setNotificationOpen(false)} title="Chat notification preferences" description="Choose delivery for mentions, followed threads, direct messages and channel activity." footer={<><Button variant="ghost" onClick={() => setNotificationOpen(false)}>Cancel</Button><Button variant="primary" disabled={chatPreferences.length !== 4} onClick={() => void saveChatPreferences()}>Save preferences</Button></>}>
      <div className="chat-notification-matrix">{chatPreferences.map((preference, index) => <div className="chat-notification-row" key={preference.category}><strong>{preference.category === 'chat_mentions' ? 'Mentions' : preference.category === 'chat_threads' ? 'Followed threads' : preference.category === 'chat_direct' ? 'Direct messages' : 'Channel activity'}</strong><Label><Checkbox checked={preference.in_app} onChange={event => setChatPreferences(rows => rows.map((row, rowIndex) => rowIndex === index ? { ...row, in_app: event.target.checked } : row))}/> In app</Label><Label><Checkbox checked={preference.email} onChange={event => setChatPreferences(rows => rows.map((row, rowIndex) => rowIndex === index ? { ...row, email: event.target.checked } : row))}/> Email</Label><Select value={preference.digest} onChange={event => setChatPreferences(rows => rows.map((row, rowIndex) => rowIndex === index ? { ...row, digest: event.target.value as ChatNotificationPreference['digest'] } : row))}><Option value="immediate">Immediate</Option><Option value="daily">Daily digest</Option><Option value="weekly">Weekly digest</Option></Select></div>)}</div>
    </Modal>

    <Modal open={Boolean(bookmarkNoteMessage)} onClose={() => { setBookmarkNoteMessage(null); setBookmarkNote(''); }} title="Bookmark note" description="This note is private to you and does not change the shared message." footer={<><Button variant="ghost" onClick={() => setBookmarkNoteMessage(null)}>Cancel</Button><Button variant="primary" onClick={() => void saveBookmarkNote()}>Save note</Button></>}><Field label="Private note"><Textarea rows={4} maxLength={500} value={bookmarkNote} onChange={event => setBookmarkNote(event.target.value)} placeholder="Why did you save this message?"/></Field></Modal>

    <Modal open={Boolean(moderationMessage)} onClose={() => { setModerationMessage(null); setModerationReason(''); }} title={moderationAction === 'redact' ? 'Redact message' : 'Flag message'} description={moderationAction === 'redact' ? 'The visible content will be redacted while governed audit history is preserved.' : 'Flag this message for enterprise moderation review.'} footer={<><Button variant="ghost" onClick={() => setModerationMessage(null)}>Cancel</Button><Button variant={moderationAction === 'redact' ? 'danger' : 'primary'} onClick={() => void submitModeration()}>{moderationAction === 'redact' ? 'Redact' : 'Flag'}</Button></>}><Field label="Reason (optional)"><Textarea rows={4} maxLength={500} value={moderationReason} onChange={event => setModerationReason(event.target.value)} placeholder="Add moderation context…"/></Field></Modal>

    <EnterpriseControls open={enterpriseOpen} onClose={() => setEnterpriseOpen(false)} workspaceId={workspace.id} conversation={current} permissions={{ guests: canGuestsManage, retention: canRetentionManage, export: canExportChat, legalHold: canLegalHoldManage, dlp: canDlpManage }} onChanged={async () => { await loadConversations(true); if (selected)
        await loadMessages(selected, true); }}/>

    <NewConversation open={newOpen} onClose={() => setNewOpen(false)} options={options} currentMemberId={viewerMemberId ?? workspace.memberId ?? options.current_member_id ?? null} workspaceId={workspace.id} canManage={canManage} onCreated={async (id) => { setNewOpen(false); await loadConversations(true); setSelected(id); setMobilePanel('chat'); }}/>

    <Modal open={Boolean(editMessage)} onClose={() => setEditMessage(null)} title="Edit message" description="Changes are retained in the message audit history." footer={<><Button variant="ghost" onClick={() => setEditMessage(null)}>Cancel</Button><Button variant="primary" disabled={!editBody.trim() || editBody.trim() === editMessage?.body} onClick={() => void saveEdit()}>Save changes</Button></>}>
      <Field label="Message"><Textarea rows={6} value={editBody} onChange={event => setEditBody(event.target.value)}/></Field>
    </Modal>

    <Modal open={Boolean(historyMessage)} onClose={() => { setHistoryMessage(null); setEditHistory([]); }} title="Edit history" description="Previous message versions visible to the sender and chat moderators.">
      <div className="chat-history-list">{editHistory.map(version => <article key={version.id}><div><strong>{version.editor || 'Member'}</strong><span>{version.edited_at ? formatTime(version.edited_at) : ''}</span></div><p>{version.body || 'Empty message'}</p></article>)}{!editHistory.length && <div className="chat-empty">No earlier versions.</div>}</div>
    </Modal>

    <Modal open={Boolean(forwardMessage)} onClose={() => setForwardMessage(null)} title="Forward message" description="The target conversation is re-authorized on the server before the forward is created." footer={<><Button variant="ghost" onClick={() => setForwardMessage(null)}>Cancel</Button><Button variant="primary" disabled={!forwardConversationId} onClick={() => void submitForward()}><Forward size={13}/> Forward</Button></>}>
      <Field label="Conversation"><Select value={forwardConversationId} onChange={event => setForwardConversationId(event.target.value)}><Option value="">Choose conversation…</Option>{conversations.map(conversation => <Option key={conversation.id} value={conversation.id}>{conversation.name}</Option>)}</Select></Field>
      <Field label="Optional note"><Textarea rows={3} value={forwardNote} onChange={event => setForwardNote(event.target.value)} placeholder="Add context for the forwarded message…"/></Field>
    </Modal>

    <PollModal open={pollOpen} question={pollQuestion} options={pollOptions} multiple={pollMultiple} closesAt={pollClosesAt} onClose={() => setPollOpen(false)} onQuestion={setPollQuestion} onOptions={setPollOptions} onMultiple={setPollMultiple} onClosesAt={setPollClosesAt} onCreate={() => void createPoll()}/>

    <Modal open={Boolean(actionMessage)} onClose={() => setActionMessage(null)} title={actionType === 'task' ? 'Create task' : actionType === 'approval' ? 'Create approval' : 'Create incident'} description="Create a real WorkIntel workspace action linked back to this chat message." footer={<><Button variant="ghost" onClick={() => setActionMessage(null)}>Cancel</Button><Button variant="primary" loading={actionBusy} disabled={!actionTitle.trim() || (actionType === 'task' && !actionProjectId && !current?.project)} onClick={() => void submitMessageAction()}>Create {actionType}</Button></>}>
      <Field label="Title"><Input value={actionTitle} onChange={event => setActionTitle(event.target.value)} placeholder="Action title"/></Field>
      {actionType === 'task' && <Field label="Project"><Select value={actionProjectId} onChange={event => setActionProjectId(event.target.value)}><Option value="">Choose project…</Option>{options.projects.map(project => <Option key={project.id} value={project.id}>{project.name}</Option>)}</Select></Field>}
      {actionType === 'approval' && <Alert tone="info">The request will use the workspace's Chat approval workflow.</Alert>}
      {actionType === 'incident' && <Alert tone="warning">A medium-severity safety incident will be created and linked to the source message.</Alert>}
    </Modal>

    <Modal open={Boolean(preview)} onClose={() => { if (preview)
        URL.revokeObjectURL(preview.url); setPreview(null); }} title={preview?.attachment.filename ?? 'Attachment preview'} description={preview ? `${preview.attachment.mime_type || 'File'} · ${size(preview.attachment.size_bytes)}` : ''} footer={preview ? <Button variant="outline" onClick={() => void download(preview.attachment)}><Download size={13}/> Download</Button> : undefined}>
      {preview && <AttachmentPreview preview={preview}/>}
    </Modal>
  </Page>;
}
