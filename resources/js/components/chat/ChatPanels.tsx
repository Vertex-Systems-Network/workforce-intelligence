import { useEffect, useMemo, useState } from 'react';
import { BarChart3, BellOff, Bookmark, Download, FileText, Film, Forward, History, Image as ImageIcon, Info, ListTree, MoreHorizontal, Music, Paperclip, Pencil, Pin, Plus, Reply, Send, Shield, Smile, Trash2, X } from 'lucide-react';
import { apiRequest } from '../../api/client';
import { Alert, Avatar, Badge, Box, Button, Checkbox, ChoiceInput, Field, Image, Input, Label, Link, Modal, Option, Pressable, Select, Textarea } from '../../design-system';
import type { Attachment, ChannelResource, ContextBookmark, ContextFile, Conversation, ConversationContext, CreationOptions, Member, Message, MessageActionType, Poll, Presence, PreviewState, ThreadData } from './chatTypes';
import { attachmentKind, displayBody, messageFlagged, size } from './chatUtils';

/** Renders one message with professional actions, forwarded content, polls and attachment previews. */
export function MessageCard({ message, members, canModerate, formatTime, onThread, onReact, onPin, onSave, onEdit, onDelete, onHistory, onForward, onAction, onModerate, onDownload, onPreview, onVote, menuOpen, onToggleMenu }: {
    message: Message;
    members: Member[];
    canModerate: boolean;
    formatTime: (value: string | Date | number, options?: Intl.DateTimeFormatOptions) => string;
    onThread: () => void;
    onReact: (emoji: string) => void;
    onPin: () => void;
    onSave: () => void;
    onEdit: () => void;
    onDelete: () => void;
    onHistory: () => void;
    onForward: () => void;
    onAction: (type: MessageActionType) => void;
    onModerate: (action: 'flag' | 'redact') => void;
    onDownload: (attachment: Attachment) => void;
    onPreview: (attachment: Attachment) => void;
    onVote: (optionId: number) => void;
    menuOpen: boolean;
    onToggleMenu: () => void;
}) {
    return <article className={`chat-message${message.mine ? ' is-mine' : ''}${message.pinned ? ' is-pinned' : ''}${message.saved ? ' is-saved' : ''}${message.sender?.kind === 'bot' ? ' is-bot' : ''}${message.message_type === 'action' ? ' is-action-card' : ''}`}>
    <Avatar name={message.sender?.name ?? '?'} size="sm"/>
    <div className="chat-message-body">
      <div className="chat-message-meta"><strong>{message.sender?.name ?? 'Deleted user'}</strong>{message.sender?.kind === 'bot' && <Badge tone="accent">BOT</Badge>}{message.sender?.collaboration_type && message.sender.collaboration_type !== 'internal' && <Badge tone="warning">External · {message.sender.collaboration_type}</Badge>}{messageFlagged(message) && <Badge tone="warning">Flagged</Badge>}<span>{formatTime(message.created_at)}{message.edited_at ? ' · edited' : ''}</span>{message.mine && <span className="chat-delivery-state">{message.read_by > 0 ? `Read by ${message.read_by}` : (message.delivered_to ?? 0) > 0 ? `Delivered to ${message.delivered_to}` : 'Sent'}</span>}{message.pinned && <Pin size={11}/>}{message.saved && <Bookmark size={11}/>}</div>
      {message.forwarded && <div className="chat-forwarded"><Forward size={12}/><div><strong>Forwarded from {message.forwarded.sender || 'Unknown member'}</strong>{message.forwarded.deleted ? <em>Original message deleted</em> : <p>{message.forwarded.body || `${message.forwarded.attachment_count} attachment(s)`}</p>}</div></div>}
      {message.parent && <div className="chat-reply-preview"><Reply size={11}/><span><b>{message.parent.sender}</b> {message.parent.body}</span></div>}
      {message.deleted_at ? <em className="chat-deleted">Message deleted</em> : <div className="chat-message-text">{displayBody(message.body, members)}</div>}
      {message.message_type === 'action' && message.metadata && <div className="chat-work-action-card"><strong>{String(message.metadata.action_type ?? 'Workspace action').replaceAll('_', ' ')}</strong><small>{message.metadata.task_id ? `Task #${message.metadata.task_id}` : message.metadata.approval_request_id ? `Approval #${message.metadata.approval_request_id}` : message.metadata.incident_id ? `Incident #${message.metadata.incident_id}` : 'Linked WorkIntel action'}</small></div>}
      {message.poll && <PollCard poll={message.poll} onVote={onVote}/>}
      {message.attachments.map(attachment => <AttachmentCard key={attachment.id} attachment={attachment} onDownload={() => onDownload(attachment)} onPreview={() => onPreview(attachment)}/>)}
      <div className="chat-reactions">{message.reactions.map(reaction => <Pressable key={reaction.emoji} className={reaction.mine ? 'is-mine' : ''} onClick={() => onReact(reaction.emoji)}>{reaction.emoji} {reaction.count}</Pressable>)}</div>
      {message.mine && message.read_by > 0 && <div className="chat-read-receipt">Read by {message.read_by}</div>}
      <div className="chat-message-actions">
        <Pressable onClick={onThread}><ListTree size={12}/> Thread{message.thread_reply_count > 0 ? ` ${message.thread_reply_count}` : ''}{message.thread_unread_count > 0 ? ` · ${message.thread_unread_count} new` : ''}</Pressable>
        <Pressable onClick={() => onReact('👍')}><Smile size={12}/> React</Pressable>
        <Pressable onClick={onSave}><Bookmark size={12}/> {message.saved ? 'Unsave' : 'Save'}</Pressable>
        <div className="chat-message-menu-wrap"><Pressable aria-label="More message actions" onClick={onToggleMenu}><MoreHorizontal size={13}/> More</Pressable>{menuOpen && <div className="chat-message-menu">
          <Pressable onClick={onPin}><Pin size={12}/> {message.pinned ? 'Unpin' : 'Pin'}</Pressable>
          <Pressable onClick={onForward} disabled={Boolean(message.deleted_at)}><Forward size={12}/> Forward</Pressable>
          {message.mine && !message.deleted_at && <Pressable onClick={onEdit}><Pencil size={12}/> Edit</Pressable>}
          {(message.mine || canModerate) && (message.edited_at || message.deleted_at) && <Pressable onClick={onHistory}><History size={12}/> Edit history</Pressable>}
          {!message.deleted_at && message.sender?.kind !== 'bot' && <><Pressable onClick={() => onAction('task')}><Plus size={12}/> Create task</Pressable><Pressable onClick={() => onAction('approval')}><FileText size={12}/> Create approval</Pressable><Pressable onClick={() => onAction('incident')}><Info size={12}/> Create incident</Pressable></>}
          {canModerate && !message.deleted_at && <><Pressable onClick={() => onModerate('flag')}><Shield size={12}/> Flag for review</Pressable><Pressable className="is-danger" onClick={() => onModerate('redact')}><Shield size={12}/> Redact with audit</Pressable></>}
          {(message.mine || canModerate) && !message.deleted_at && <Pressable className="is-danger" onClick={onDelete}><Trash2 size={12}/> Delete</Pressable>}
        </div>}</div>
      </div>
    </div>
  </article>;
}
/** Renders a poll with aggregate counts and the current member's selected options. */
export function PollCard({ poll, onVote }: {
    poll: Poll;
    onVote: (optionId: number) => void;
}) {
    const maxVotes = Math.max(1, ...poll.options.map(option => option.votes));
    return <div className="chat-poll-card">
    <div className="chat-poll-meta"><span>{poll.allows_multiple ? 'Multiple choice' : 'Single choice'}</span><span>{poll.closed ? 'Closed' : `${poll.total_voters} voter${poll.total_voters === 1 ? '' : 's'}`}</span></div>
    {poll.options.map(option => <Pressable key={option.id} type="button" className={option.mine ? 'is-selected' : ''} disabled={poll.closed} onClick={() => onVote(option.id)}><Box as="span" className="chat-poll-bar" width={`${Math.round((option.votes / maxVotes) * 100)}%`}/><span>{option.label}</span><strong>{option.votes}</strong></Pressable>)}
  </div>;
}
/** Renders a private attachment with media-aware preview and download actions. */
export function AttachmentCard({ attachment, onDownload, onPreview }: {
    attachment: Attachment;
    onDownload: () => void;
    onPreview: () => void;
}) {
    const kind = attachmentKind(attachment);
    const icon = kind === 'image' ? <ImageIcon size={14}/> : kind === 'video' ? <Film size={14}/> : kind === 'audio' ? <Music size={14}/> : <FileText size={14}/>;
    return <div className="chat-attachment"><Pressable type="button" className="chat-attachment-main" onClick={kind === 'file' ? onDownload : onPreview}>{icon}<span><strong>{attachment.filename}</strong><small>{attachment.mime_type || 'File'} · {size(attachment.size_bytes)}</small>{attachment.security_status && attachment.security_status !== 'clear' && <Badge tone={attachment.security_status === 'quarantined' ? 'warning' : 'neutral'}>{attachment.security_status === 'quarantined' ? 'Quarantined' : 'Review'}</Badge>}</span></Pressable><Pressable type="button" className="chat-attachment-download" aria-label={`Download ${attachment.filename}`} onClick={onDownload}><Download size={13}/></Pressable></div>;
}
/** Renders authenticated media after the attachment bytes have been fetched through the API client. */
export function AttachmentPreview({ preview }: {
    preview: PreviewState;
}) {
    const kind = attachmentKind(preview.attachment);
    if (kind === 'image')
        return <Image className="chat-preview-media" src={preview.url} alt={preview.attachment.filename}/>;
    if (kind === 'video')
        return <video className="chat-preview-media" src={preview.url} controls/>;
    if (kind === 'audio')
        return <audio className="chat-preview-audio" src={preview.url} controls/>;
    return <div className="chat-empty"><FileText size={28}/> Preview is not available for this file type.</div>;
}
/** Renders the open thread with follow controls and an isolated reply composer. */
export function ThreadPanel({ data, members, body, sending, formatTime, onBodyChange, onSend, onClose, onFollow, onReact, onVote, onDownload, onPreview }: {
    data: ThreadData;
    members: Member[];
    body: string;
    sending: boolean;
    formatTime: (value: string | Date | number, options?: Intl.DateTimeFormatOptions) => string;
    onBodyChange: (value: string) => void;
    onSend: () => void;
    onClose: () => void;
    onFollow: () => void;
    onReact: (message: Message, emoji: string) => void;
    onVote: (message: Message, optionId: number) => void;
    onDownload: (attachment: Attachment) => void;
    onPreview: (attachment: Attachment) => void;
}) {
    return <div className="chat-thread-panel">
    <div className="chat-details-heading"><div><strong>Thread</strong><small>{data.replies.length} repl{data.replies.length === 1 ? 'y' : 'ies'}</small></div><Pressable type="button" className="chat-icon-button" aria-label="Close thread" onClick={onClose}><X size={14}/></Pressable></div>
    <Pressable type="button" className={`chat-thread-follow${data.following ? ' is-following' : ''}`} onClick={onFollow}>{data.following ? 'Following thread' : 'Follow thread'}</Pressable>
    <div className="chat-thread-scroll">
      {[data.root, ...data.replies].map((message, index) => <div key={message.id} className={index === 0 ? 'chat-thread-root' : ''}><div className="chat-thread-message-meta"><Avatar name={message.sender?.name ?? '?'} size="sm"/><span><strong>{message.sender?.name ?? 'Deleted user'}</strong><small>{formatTime(message.created_at)}</small></span></div>{message.deleted_at ? <em className="chat-deleted">Message deleted</em> : <p>{displayBody(message.body, members)}</p>}{message.poll && <PollCard poll={message.poll} onVote={optionId => onVote(message, optionId)}/>}{message.attachments.map(attachment => <AttachmentCard key={attachment.id} attachment={attachment} onDownload={() => onDownload(attachment)} onPreview={() => onPreview(attachment)}/>)}<div className="chat-reactions">{message.reactions.map(reaction => <Pressable key={reaction.emoji} className={reaction.mine ? 'is-mine' : ''} onClick={() => onReact(message, reaction.emoji)}>{reaction.emoji} {reaction.count}</Pressable>)}</div></div>)}
    </div>
    <div className="chat-thread-composer"><Textarea rows={3} value={body} onChange={event => onBodyChange(event.target.value)} onKeyDown={event => { if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        onSend();
    } }} placeholder="Reply in thread…"/><Button size="sm" variant="primary" loading={sending} disabled={!body.trim()} onClick={onSend}><Send size={12}/> Reply</Button></div>
  </div>;
}
/** Renders channel governance, collaboration context, internal resources and member controls in the side panel. */
export function ConversationDetails({ conversation, presence, viewerMemberId, canGovern, canModerate, people, resources, contextData, resourceLabel, resourceUrl, resourceKind, resourceId, resourceOptions, onResourceLabel, onResourceUrl, onResourceKind, onResourceId, onAddResource, onRemoveResource, onOpenMessage, onDownloadFile, onBookmarkNote, onLoadMoreContext, contextLoadingMore, onBulkContext, onAddMember, onRoleChange, onRemoveMember, onNotification, onToggleLock, onLeave, onArchive, onClose, onMute }: {
    conversation: Conversation;
    presence: Presence[];
    viewerMemberId: number | null;
    canGovern: boolean;
    canModerate: boolean;
    people: Member[];
    resources: ChannelResource[];
    contextData: ConversationContext;
    resourceLabel: string;
    resourceUrl: string;
    resourceKind: 'link' | 'project' | 'task' | 'document';
    resourceId: string;
    resourceOptions: CreationOptions;
    onResourceLabel: (value: string) => void;
    onResourceUrl: (value: string) => void;
    onResourceKind: (value: 'link' | 'project' | 'task' | 'document') => void;
    onResourceId: (value: string) => void;
    onAddResource: () => void;
    onRemoveResource: (id: number) => void;
    onOpenMessage: (message: Message) => void;
    onDownloadFile: (file: ContextFile) => void;
    onBookmarkNote: (bookmark: ContextBookmark) => void;
    onLoadMoreContext: () => void;
    contextLoadingMore: boolean;
    onBulkContext: (action: 'unpin' | 'delete_bookmarks') => void;
    onAddMember: (id: number) => void;
    onRoleChange: (id: number, role: string) => void;
    onRemoveMember: (id: number) => void;
    onNotification: (mode: 'all' | 'mentions' | 'nothing') => void;
    onToggleLock: () => void;
    onLeave: () => void;
    onArchive: () => void;
    onClose: () => void;
    onMute: () => void;
}) {
    const memberIds = new Set(conversation.members.map(member => member.id));
    const addable = people.filter(person => !memberIds.has(person.id)).slice(0, 30);
    const selectedResources = resourceKind === 'project' ? resourceOptions.projects.map(item => ({ id: item.id, label: item.name })) : resourceKind === 'task' ? resourceOptions.tasks.map(item => ({ id: item.id, label: item.title })) : resourceKind === 'document' ? resourceOptions.documents.map(item => ({ id: item.id, label: item.filename })) : [];
    const resourceReady = resourceKind === 'link' ? Boolean(resourceLabel.trim() && resourceUrl.trim()) : Boolean(resourceId);
    return <>
    <div className="chat-details-heading"><div><strong>{conversation.name}</strong><small>{conversation.viewer_role || 'member'} · {conversation.visibility || 'private'}</small></div><Pressable type="button" className="chat-icon-button" aria-label="Close conversation details" onClick={onClose}><X size={14}/></Pressable></div>
    <div className="chat-section-label">Notifications</div>
    <Field label="Delivery"><Select value={conversation.notification_mode || (conversation.is_muted ? 'nothing' : 'all')} onChange={event => onNotification(event.target.value as 'all' | 'mentions' | 'nothing')}><Option value="all">All messages</Option><Option value="mentions">Mentions only</Option><Option value="nothing">Nothing</Option></Select></Field>
    <div className="chat-section-label">Members</div>
    {conversation.members.map(member => {
            const online = member.is_self || member.id === viewerMemberId ? true : presence.some(item => item.member_id === member.id);
            const typing = !member.is_self && member.id !== viewerMemberId && presence.some(item => item.member_id === member.id && item.conversation_id === conversation.id && item.is_typing);
            return <div className="chat-member chat-member-governed" key={member.id}><Avatar name={member.name} size="sm"/><span><strong>{member.name}{(member.is_self || member.id === viewerMemberId) ? ' (You)' : ''}{member.collaboration_type && member.collaboration_type !== 'internal' && <Badge tone="warning">External</Badge>}</strong><small>{typing ? 'Typing…' : online ? 'Online' : 'Offline'} · {member.role || 'member'}{member.external_company ? ` · ${member.external_company}` : ''}</small></span>{canGovern && !member.is_self && <div className="chat-member-admin"><Select value={member.role || 'member'} onChange={event => onRoleChange(member.id, event.target.value)}><Option value="owner">Owner</Option><Option value="admin">Admin</Option><Option value="moderator">Moderator</Option><Option value="member">Member</Option><Option value="read_only">Read-only</Option></Select><Pressable type="button" className="chat-icon-button" title="Remove member" onClick={() => onRemoveMember(member.id)}><X size={12}/></Pressable></div>}<i className={online ? 'is-online' : ''}/></div>;
        })}
    {canGovern && addable.length > 0 && <Field label="Add member"><Select defaultValue="" onChange={event => { const id = Number(event.target.value); if (id)
        onAddMember(id); event.currentTarget.value = ''; }}><Option value="">Choose active member…</Option>{addable.map(person => <Option key={person.id} value={person.id}>{person.name}</Option>)}</Select></Field>}
    <div className="chat-section-label">Conversation</div>
    <div className="chat-detail-row"><span>Type</span><strong>{conversation.type}</strong></div>
    <div className="chat-detail-row"><span>Visibility</span><strong>{conversation.visibility || 'private'}</strong></div>
    <div className="chat-detail-row"><span>Mode</span><strong>{conversation.channel_mode === 'announcement' ? 'Announcement' : 'Standard'}</strong></div>
    <div className="chat-detail-row"><span>Posting</span><strong>{conversation.posting_policy === 'admins' ? 'Moderators only' : 'Members'}</strong></div>
    <div className="chat-detail-row"><span>Status</span><strong>{conversation.is_locked ? 'Locked' : 'Open'}</strong></div>
    {canGovern && ['channel', 'project', 'task'].includes(conversation.type) && <div className="chat-governance-actions"><Button variant="outline" size="sm" onClick={onToggleLock}>{conversation.is_locked ? 'Unlock channel' : 'Lock channel'}</Button><Button variant="outline" size="sm" onClick={onArchive}>Archive channel</Button></div>}

    <div className="chat-section-label"><Pin size={12}/> Pinned messages {canModerate && contextData.pinned.length > 1 && <Button size="sm" variant="ghost" onClick={() => onBulkContext('unpin')}>Clear visible</Button>}</div>
    <div className="chat-context-list">{contextData.pinned.map(message => <Pressable key={message.id} type="button" className="chat-context-item" onClick={() => onOpenMessage(message)}><strong>{message.sender?.name ?? 'Member'}</strong><span>{message.body || 'Attachment or system message'}</span><small>{message.created_at}</small></Pressable>)}{!contextData.pinned.length && <div className="chat-empty">No pinned messages.</div>}</div>

    <div className="chat-section-label"><Bookmark size={12}/> Your bookmarks {contextData.bookmarks.length > 1 && <Button size="sm" variant="ghost" onClick={() => onBulkContext('delete_bookmarks')}>Clear visible</Button>}</div>
    <div className="chat-context-list">{contextData.bookmarks.map(bookmark => <div key={bookmark.id} className="chat-context-item chat-context-bookmark"><Pressable type="button" onClick={() => onOpenMessage(bookmark.message)}><strong>{bookmark.message.sender?.name ?? 'Member'}</strong><span>{bookmark.message.body || 'Saved message'}</span>{bookmark.note && <small>Note: {bookmark.note}</small>}</Pressable><Button size="sm" variant="ghost" onClick={() => onBookmarkNote(bookmark)}>Note</Button></div>)}{!contextData.bookmarks.length && <div className="chat-empty">No saved messages in this conversation.</div>}</div>

    <div className="chat-section-label"><Paperclip size={12}/> Recent files</div>
    <div className="chat-context-list">{contextData.files.map(file => <div key={file.id} className="chat-context-file"><FileText size={13}/><span><strong>{file.filename}</strong><small>{file.sender || 'Member'} · {size(file.size_bytes)}</small></span><Button size="sm" variant="ghost" onClick={() => onDownloadFile(file)}><Download size={12}/></Button></div>)}{!contextData.files.length && <div className="chat-empty">No recent files.</div>}</div>
    {(contextData.meta?.pin_next || contextData.meta?.bookmark_next || contextData.meta?.file_next) && <Button size="sm" variant="outline" loading={contextLoadingMore} onClick={onLoadMoreContext}>Load older context</Button>}

    <div className="chat-section-label">Channel resources</div>
    <div className="chat-resource-list">{resources.map(resource => <div key={resource.id} className={`chat-resource-row${resource.available === false ? ' is-unavailable' : ''}`}>{resource.url ? <Link href={resource.url} target="_blank" rel="noreferrer"><FileText size={12}/><span><strong>{resource.label}</strong><small>External link</small></span></Link> : <div className="chat-resource-static"><FileText size={12}/><span><strong>{resource.entity?.title || resource.label}</strong><small>{resource.available === false ? 'No longer available' : resource.entity ? `${resource.entity.type}${resource.entity.status ? ` · ${resource.entity.status}` : ''}${resource.entity.priority ? ` · ${resource.entity.priority}` : ''}` : `${resource.kind}${resource.resource_id ? ` #${resource.resource_id}` : ''}`}</small>{resource.entity?.due_at && <small>Due {String(resource.entity.due_at).slice(0, 10)}</small>}</span></div>}{canModerate && <Button size="sm" variant="ghost" iconOnly aria-label={`Remove ${resource.label}`} onClick={() => onRemoveResource(resource.id)}><X size={12}/></Button>}</div>)}{!resources.length && <div className="chat-empty">No pinned resources.</div>}</div>
    {canModerate && <div className="chat-resource-form"><Select value={resourceKind} onChange={event => onResourceKind(event.target.value as 'link' | 'project' | 'task' | 'document')}><Option value="link">External link</Option><Option value="project">Project</Option><Option value="task">Task</Option>{resourceOptions.documents.length > 0 && <Option value="document">Generated document</Option>}</Select>{resourceKind === 'link' ? <Input value={resourceUrl} onChange={event => onResourceUrl(event.target.value)} placeholder="https://…"/> : <Select value={resourceId} onChange={event => onResourceId(event.target.value)}><Option value="">Choose {resourceKind}…</Option>{selectedResources.map(item => <Option key={item.id} value={item.id}>{item.label}</Option>)}</Select>}<Input value={resourceLabel} onChange={event => onResourceLabel(event.target.value)} placeholder={resourceKind === 'link' ? 'Resource label' : 'Optional custom label'}/><Button size="sm" variant="outline" disabled={!resourceReady} onClick={onAddResource}><Plus size={12}/> Add resource</Button></div>}
    {conversation.type === 'channel' ? <Button className="chat-detail-footer-action" variant="outline" size="sm" onClick={onLeave}>Leave channel</Button> : <Button className="chat-detail-footer-action" variant="outline" size="sm" onClick={onMute}><BellOff size={13}/> {conversation.is_muted ? 'Unmute conversation' : 'Mute conversation'}</Button>}
  </>;
}
/** Renders a professional poll-creation dialog with two to ten unique options. */
export function PollModal({ open, question, options, multiple, closesAt, onClose, onQuestion, onOptions, onMultiple, onClosesAt, onCreate }: {
    open: boolean;
    question: string;
    options: string[];
    multiple: boolean;
    closesAt: string;
    onClose: () => void;
    onQuestion: (value: string) => void;
    onOptions: (value: string[]) => void;
    onMultiple: (value: boolean) => void;
    onClosesAt: (value: string) => void;
    onCreate: () => void;
}) {
    const valid = question.trim().length > 0 && options.filter(option => option.trim()).length >= 2;
    return <Modal open={open} onClose={onClose} title="Create poll" description="Create a single- or multiple-choice poll for this conversation." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!valid} onClick={onCreate}><BarChart3 size={13}/> Create poll</Button></>}>
    <Field label="Question"><Input value={question} onChange={event => onQuestion(event.target.value)} placeholder="What should the team decide?"/></Field>
    <div className="chat-poll-options-editor">{options.map((option, index) => <div key={index}><Input value={option} onChange={event => onOptions(options.map((item, itemIndex) => itemIndex === index ? event.target.value : item))} placeholder={`Option ${index + 1}`}/>{options.length > 2 && <Pressable type="button" className="chat-icon-button" aria-label={`Remove option ${index + 1}`} onClick={() => onOptions(options.filter((_, itemIndex) => itemIndex !== index))}><X size={13}/></Pressable>}</div>)}{options.length < 10 && <Button variant="outline" size="sm" onClick={() => onOptions([...options, ''])}><Plus size={12}/> Add option</Button>}</div>
    <Label className="chat-checkbox-row"><Checkbox checked={multiple} onChange={event => onMultiple(event.target.checked)}/><span>Allow multiple choices</span></Label>
    <Field label="Close at (optional)"><Input type="datetime-local" value={closesAt} onChange={event => onClosesAt(event.target.value)}/></Field>
  </Modal>;
}
/** Renders a validated conversation-creation dialog that never offers the current user as a participant. */
export function NewConversation({ open, onClose, options, currentMemberId, workspaceId, canManage, onCreated }: {
    open: boolean;
    onClose: () => void;
    options: CreationOptions;
    currentMemberId: number | null;
    workspaceId: number;
    canManage: boolean;
    onCreated: (id: number) => void;
}) {
    const [type, setType] = useState('direct');
    const [name, setName] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [projectId, setProjectId] = useState('');
    const [taskId, setTaskId] = useState('');
    const [visibility, setVisibility] = useState<'public' | 'private'>('private');
    const [channelMode, setChannelMode] = useState<'standard' | 'announcement'>('standard');
    const [peopleQuery, setPeopleQuery] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const people = useMemo(() => options.people.filter(person => person.id !== currentMemberId && (`${person.name} ${person.email ?? ''} ${person.job_title ?? ''}`).toLowerCase().includes(peopleQuery.trim().toLowerCase())), [options.people, currentMemberId, peopleQuery]);
    const valid = type === 'direct' ? selected.length === 1 : type === 'group' ? selected.length >= 1 : type === 'channel' ? Boolean(name.trim()) : type === 'project' ? Boolean(projectId) : type === 'task' ? Boolean(taskId) : true;
    useEffect(() => {
        if (!open)
            return;
        setType('direct');
        setName('');
        setSelected([]);
        setProjectId('');
        setTaskId('');
        setVisibility('private');
        setChannelMode('standard');
        setPeopleQuery('');
        setError('');
    }, [open]);
    /** Creates a validated conversation and reuses an existing direct-message pair when returned by the API. */
    const create = async () => {
        if (!valid)
            return;
        setBusy(true);
        setError('');
        try {
            const payload: {
                type: string;
                name: string | null;
                member_ids: number[];
                project_id?: number;
                task_id?: number;
                visibility?: 'public' | 'private';
                channel_mode?: 'standard' | 'announcement';
                posting_policy?: 'members' | 'admins';
            } = { type, name: name || null, member_ids: selected.filter(id => id !== currentMemberId) };
            if (type === 'project' && projectId)
                payload.project_id = Number(projectId);
            if (type === 'task' && taskId)
                payload.task_id = Number(taskId);
            if (type === 'channel') {
                payload.visibility = visibility;
                payload.channel_mode = channelMode;
                payload.posting_policy = channelMode === 'announcement' ? 'admins' : 'members';
            }
            const response = await apiRequest<{
                data: {
                    id: number;
                };
            }>('/api/v1/chat/conversations', { method: 'POST', workspaceId, body: JSON.stringify(payload) });
            onCreated(response.data.id);
        }
        catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Could not create conversation.');
        }
        finally {
            setBusy(false);
        }
    };
    return <Modal open={open} onClose={onClose} title="New conversation" description="Create a direct message, group, channel, project thread or task thread." footer={<><Button variant="ghost" onClick={onClose}>Cancel</Button><Button variant="primary" loading={busy} disabled={!valid} onClick={() => void create()}>Create</Button></>}>
    <div className="chat-new-conversation">
      {error && <Alert tone="danger">{error}</Alert>}
      <Field label="Type"><Select value={type} onChange={event => { setType(event.target.value); setSelected([]); setProjectId(''); setTaskId(''); setError(''); }}><Option value="direct">Direct message</Option><Option value="group">Group</Option>{canManage && <><Option value="channel">Channel</Option><Option value="project">Project thread</Option><Option value="task">Task thread</Option></>}</Select></Field>
      {type === 'project' && <Field label="Project"><Select value={projectId} onChange={event => setProjectId(event.target.value)}><Option value="">Choose project…</Option>{options.projects.map(project => <Option key={project.id} value={project.id}>{project.name}</Option>)}</Select></Field>}
      {type === 'task' && <Field label="Task"><Select value={taskId} onChange={event => setTaskId(event.target.value)}><Option value="">Choose task…</Option>{options.tasks.map(task => <Option key={task.id} value={task.id}>{task.title}</Option>)}</Select></Field>}
      {type !== 'direct' && <Field label="Name"><Input value={name} onChange={event => setName(event.target.value)} placeholder="Conversation name"/></Field>}
      {type === 'channel' && <div className="chat-channel-create-options"><Field label="Visibility"><Select value={visibility} onChange={event => setVisibility(event.target.value as 'public' | 'private')}><Option value="private">Private</Option><Option value="public">Public</Option></Select></Field><Field label="Channel mode"><Select value={channelMode} onChange={event => setChannelMode(event.target.value as 'standard' | 'announcement')}><Option value="standard">Standard</Option><Option value="announcement">Announcement</Option></Select></Field></div>}
      <Field label={type === 'direct' ? 'Choose one member' : 'Members'}><Input value={peopleQuery} onChange={event => setPeopleQuery(event.target.value)} placeholder="Search active members…"/><div className="chat-member-picker">{people.map(person => <Label key={person.id}><ChoiceInput type={type === 'direct' ? 'radio' : 'checkbox'} checked={selected.includes(person.id)} onChange={() => setSelected(type === 'direct' ? [person.id] : selected.includes(person.id) ? selected.filter(id => id !== person.id) : [...selected, person.id])}/><Avatar name={person.name} size="sm"/><span><strong>{person.name}{person.collaboration_type && person.collaboration_type !== 'internal' ? ' · External' : ''}</strong><small>{person.external_company || person.job_title || person.email || 'Active member'}</small></span></Label>)}{!people.length && <div className="chat-empty">No eligible active members found.</div>}</div></Field>
    </div>
  </Modal>;
}
