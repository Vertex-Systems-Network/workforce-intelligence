import { useEffect, useMemo, useState } from 'react'
import { AlertTriangle, Archive, Check, Copy, Download, FileSearch, LockKeyhole, RefreshCw, Shield, UserPlus, Users } from 'lucide-react'
import { apiDownload, apiRequest } from '../../api/client'
import { useConfirmAction, Alert, Badge, Button, Field, Input, Modal, Select, Textarea, Checkbox, Label, Option, LoadingState } from '../../design-system'

type ConversationEnterpriseState = {
  id: number
  name: string
  external_access?: boolean
  retention_days?: number | null
  legal_hold?: boolean
  export_policy?: 'admins' | 'moderators' | 'members' | 'disabled'
  dlp_mode?: 'inherit' | 'off'
}

type ExternalMember = {
  id: number
  name: string
  email?: string | null
  status: string
  collaboration_type: 'guest' | 'client' | 'vendor'
  external_company?: string | null
  external_expires_at?: string | null
  expired?: boolean
}

type ExternalInvitation = {
  id: number
  email: string
  collaboration_type: string
  external_company?: string | null
  external_expires_at?: string | null
  conversation_id?: number | null
  expires_at?: string | null
}

type LegalHold = {
  id: number
  conversation_id?: number | null
  name: string
  reason?: string | null
  status: 'active' | 'released'
  created_at?: string | null
  released_at?: string | null
}

type ExportJob = {
  id: number
  conversation_id: number
  format: 'json' | 'csv'
  status: 'running' | 'completed' | 'failed' | 'expired'
  size_bytes?: number | null
  created_at?: string | null
  expires_at?: string | null
  error?: string | null
}

type DlpPolicy = {
  id: number
  name: string
  mode: 'monitor' | 'quarantine' | 'block'
  keywords?: string[] | null
  file_extensions?: string[] | null
  max_file_bytes?: number | null
  active: boolean
}

type DlpEvent = {
  id: number
  conversation_id?: number | null
  message_id?: number | null
  attachment_id?: number | null
  action: string
  matched_rules?: string[] | Record<string, unknown> | null
  created_at?: string | null
}

type ModerationEvent = {
  id: number
  conversation_id?: number | null
  message_id?: number | null
  action: string
  reason?: string | null
  created_at?: string | null
}

type EnterpriseOverview = {
  external_members: ExternalMember[]
  pending_external_invitations: ExternalInvitation[]
  legal_holds: LegalHold[]
  exports: ExportJob[]
  dlp_policies: DlpPolicy[]
  dlp_events: DlpEvent[]
  moderation_events: ModerationEvent[]
  workspace_chat_retention?: { retention_days?: number | null; legal_hold?: boolean } | null
}

type EnterprisePermissions = {
  guests: boolean
  retention: boolean
  export: boolean
  legalHold: boolean
  dlp: boolean
}

/** Converts a future Date into the local datetime input representation expected by browsers. */
function localDateTimeInput(date: Date): string {
  const offset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

/** Downloads one authenticated enterprise export without exposing its private storage path. */
function triggerDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

/** Renders enterprise guest, retention, legal-hold, eDiscovery and DLP controls for Chat V2.4. */
export function EnterpriseControls({ open, onClose, workspaceId, conversation, permissions, onChanged }: {
  open: boolean
  onClose: () => void
  workspaceId: number
  conversation: ConversationEnterpriseState | null
  permissions: EnterprisePermissions
  onChanged: () => Promise<void> | void
}) {
  const confirmAction = useConfirmAction()
  const [overview, setOverview] = useState<EnterpriseOverview | null>(null)
  const [loading, setLoading] = useState(false)
  const [busy, setBusy] = useState('')
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [inviteEmail, setInviteEmail] = useState('')
  const [inviteType, setInviteType] = useState<'guest' | 'client' | 'vendor'>('guest')
  const [inviteCompany, setInviteCompany] = useState('')
  const [inviteExpiry, setInviteExpiry] = useState(localDateTimeInput(new Date(Date.now() + 30 * 86400000)))
  const [inviteLink, setInviteLink] = useState('')
  const [externalAccess, setExternalAccess] = useState(false)
  const [retentionDays, setRetentionDays] = useState('')
  const [exportPolicy, setExportPolicy] = useState<'admins' | 'moderators' | 'members' | 'disabled'>('admins')
  const [dlpMode, setDlpMode] = useState<'inherit' | 'off'>('inherit')
  const [holdName, setHoldName] = useState('')
  const [holdReason, setHoldReason] = useState('')
  const [holdScope, setHoldScope] = useState<'conversation' | 'workspace'>('conversation')
  const [dlpName, setDlpName] = useState('')
  const [dlpPolicyMode, setDlpPolicyMode] = useState<'monitor' | 'quarantine' | 'block'>('monitor')
  const [dlpKeywords, setDlpKeywords] = useState('')
  const [dlpExtensions, setDlpExtensions] = useState('')
  const [dlpMaxMb, setDlpMaxMb] = useState('')

  const activeHolds = useMemo(() => (overview?.legal_holds ?? []).filter(hold => hold.status === 'active' && (!hold.conversation_id || hold.conversation_id === conversation?.id)), [overview?.legal_holds, conversation?.id])
  const conversationExports = useMemo(() => (overview?.exports ?? []).filter(job => job.conversation_id === conversation?.id), [overview?.exports, conversation?.id])

  /** Loads the enterprise collaboration dashboard available to the current administrator. */
  const load = async () => {
    if (!open) return
    setLoading(true)
    setError('')
    try {
      const response = await apiRequest<{ data: EnterpriseOverview }>('/api/v1/chat/enterprise/overview', { workspaceId, silent: true })
      setOverview(response.data)
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Could not load enterprise controls.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (!open || !conversation) return
    setExternalAccess(Boolean(conversation.external_access))
    setRetentionDays(conversation.retention_days ? String(conversation.retention_days) : '')
    setExportPolicy(conversation.export_policy ?? 'admins')
    setDlpMode(conversation.dlp_mode ?? 'inherit')
    setInviteLink('')
    setNotice('')
    void load()
  }, [open, conversation?.id])

  /** Saves the current conversation's enterprise collaboration and retention policy. */
  const savePolicy = async () => {
    if (!conversation) return
    setBusy('policy')
    setError('')
    try {
      const payload: Record<string, unknown> = {}
      if (permissions.guests) payload.external_access = externalAccess
      if (permissions.retention) payload.retention_days = retentionDays ? Number(retentionDays) : null
      if (permissions.export) payload.export_policy = exportPolicy
      if (permissions.dlp) payload.dlp_mode = dlpMode
      await apiRequest(`/api/v1/chat/enterprise/conversations/${conversation.id}/policy`, {
        method: 'PUT', workspaceId, body: JSON.stringify(payload),
      })
      setNotice('Enterprise conversation policy saved.')
      await onChanged()
      await load()
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Could not save enterprise policy.')
    } finally { setBusy('') }
  }

  /** Creates a single-conversation external collaborator invitation and exposes its one-time URL once. */
  const invite = async () => {
    if (!conversation || !inviteEmail.trim() || !inviteExpiry) return
    setBusy('invite')
    setError('')
    try {
      const response = await apiRequest<{ invite_url: string; warning?: string }>(`/api/v1/chat/enterprise/conversations/${conversation.id}/external-invitations`, {
        method: 'POST', workspaceId,
        body: JSON.stringify({ email: inviteEmail.trim(), collaboration_type: inviteType, external_company: inviteCompany.trim() || null, external_expires_at: new Date(inviteExpiry).toISOString() }),
      })
      setInviteLink(response.invite_url)
      setNotice(response.warning || 'External invitation created.')
      setInviteEmail('')
      await load()
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Could not invite external collaborator.')
    } finally { setBusy('') }
  }

  /** Revokes an external collaborator while keeping historical message attribution and audit records. */
  const revokeExternal = async (member: ExternalMember) => {
    if (!await confirmAction({ title: 'Revoke external collaborator?', description: `Revoke ${member.name || member.email || 'this collaborator'}? Historical message attribution and audit records will be preserved.`, confirmLabel: 'Revoke', danger: true })) return
    setBusy(`member-${member.id}`)
    try {
      await apiRequest(`/api/v1/chat/enterprise/external-members/${member.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ action: 'revoke' }) })
      await load()
      await onChanged()
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not revoke collaborator.') } finally { setBusy('') }
  }

  /** Restores an unexpired external collaborator after an administrator review. */
  const restoreExternal = async (member: ExternalMember) => {
    setBusy(`member-${member.id}`)
    try {
      await apiRequest(`/api/v1/chat/enterprise/external-members/${member.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ action: 'restore' }) })
      await load()
      await onChanged()
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not restore collaborator.') } finally { setBusy('') }
  }

  /** Extends an external collaborator's hard expiry by thirty days from the later of now or current expiry. */
  const extendExternal = async (member: ExternalMember) => {
    const currentExpiry = member.external_expires_at ? new Date(member.external_expires_at).getTime() : Date.now()
    const extended = new Date(Math.max(Date.now(), currentExpiry) + 30 * 86400000).toISOString()
    setBusy(`member-${member.id}`)
    try {
      await apiRequest(`/api/v1/chat/enterprise/external-members/${member.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ action: 'update', external_expires_at: extended }) })
      await load(); await onChanged()
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not extend collaborator access.') } finally { setBusy('') }
  }

  /** Creates a workspace-wide or conversation-scoped legal hold that overrides retention deletion. */
  const createHold = async () => {
    if (!conversation || !holdName.trim()) return
    setBusy('hold')
    try {
      await apiRequest('/api/v1/chat/enterprise/legal-holds', { method: 'POST', workspaceId, body: JSON.stringify({ name: holdName.trim(), reason: holdReason.trim() || null, conversation_id: holdScope === 'conversation' ? conversation.id : null }) })
      setHoldName(''); setHoldReason('')
      await load(); await onChanged()
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not create legal hold.') } finally { setBusy('') }
  }

  /** Releases one active legal hold without deleting its lifecycle record. */
  const releaseHold = async (hold: LegalHold) => {
    if (!await confirmAction({ title: 'Release legal hold?', description: `Release legal hold “${hold.name}”? Retention deletion may resume where policy allows.`, confirmLabel: 'Release hold', danger: true })) return
    setBusy(`hold-${hold.id}`)
    try {
      await apiRequest(`/api/v1/chat/enterprise/legal-holds/${hold.id}/release`, { method: 'POST', workspaceId, body: '{}' })
      await load(); await onChanged()
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not release legal hold.') } finally { setBusy('') }
  }

  /** Generates and immediately downloads a private expiring eDiscovery export. */
  const createExport = async (format: 'json' | 'csv') => {
    if (!conversation) return
    setBusy(`export-${format}`)
    try {
      const response = await apiRequest<{ data: ExportJob }>(`/api/v1/chat/enterprise/conversations/${conversation.id}/exports`, { method: 'POST', workspaceId, body: JSON.stringify({ format }) })
      await load()
      if (response.data.status === 'completed') {
        const file = await apiDownload(`/api/v1/chat/enterprise/exports/${response.data.id}/download`, workspaceId)
        triggerDownload(file.blob, file.filename)
      }
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not create eDiscovery export.') } finally { setBusy('') }
  }

  /** Downloads an existing completed eDiscovery export owned by the current administrator. */
  const downloadExport = async (job: ExportJob) => {
    try {
      const file = await apiDownload(`/api/v1/chat/enterprise/exports/${job.id}/download`, workspaceId)
      triggerDownload(file.blob, file.filename)
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not download export.') }
  }

  /** Creates a deterministic workspace DLP policy for chat text and attachment metadata. */
  const createDlp = async () => {
    if (!dlpName.trim()) return
    setBusy('dlp')
    try {
      await apiRequest('/api/v1/chat/enterprise/dlp-policies', {
        method: 'POST', workspaceId,
        body: JSON.stringify({
          name: dlpName.trim(), mode: dlpPolicyMode,
          keywords: dlpKeywords.split(',').map(value => value.trim()).filter(Boolean),
          file_extensions: dlpExtensions.split(',').map(value => value.trim().replace(/^\./, '')).filter(Boolean),
          max_file_bytes: dlpMaxMb ? Math.round(Number(dlpMaxMb) * 1024 * 1024) : null,
          active: true,
        }),
      })
      setDlpName(''); setDlpKeywords(''); setDlpExtensions(''); setDlpMaxMb('')
      await load()
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not create DLP policy.') } finally { setBusy('') }
  }

  /** Enables or disables an existing DLP policy without deleting its audit history. */
  const toggleDlp = async (policy: DlpPolicy) => {
    setBusy(`dlp-${policy.id}`)
    try {
      await apiRequest(`/api/v1/chat/enterprise/dlp-policies/${policy.id}`, { method: 'PATCH', workspaceId, body: JSON.stringify({ active: !policy.active }) })
      await load()
    } catch (exception) { setError(exception instanceof Error ? exception.message : 'Could not update DLP policy.') } finally { setBusy('') }
  }

  return <Modal open={open} onClose={onClose} size="lg" title="Enterprise controls" description="Govern external access, retention, Legal hold, eDiscovery exports and DLP for workplace chat.">
    <div className="chat-enterprise-controls">
      {error && <Alert tone="danger">{error}</Alert>}
      {notice && <Alert tone="success">{notice}</Alert>}
      {loading && <LoadingState compact title="Loading enterprise collaboration state…" text="Refreshing governance, external access and retention controls."/>}
      {conversation && <>
        <section className="chat-enterprise-section">
          <div className="chat-enterprise-section-title"><Shield size={15} /><div><strong>Conversation policy</strong><small>{conversation.name}</small></div></div>
          <Label className="chat-checkbox-row"><Checkbox checked={externalAccess} disabled={!permissions.guests} onChange={event => setExternalAccess(event.target.checked)} /><span><strong>External access</strong><small>Allow explicitly invited guests, clients or vendors in this conversation.</small></span></Label>
          <div className="chat-enterprise-grid">
            <Field label="Retention days"><Input type="number" min={1} max={3650} disabled={!permissions.retention} value={retentionDays} onChange={event => setRetentionDays(event.target.value)} placeholder={String(overview?.workspace_chat_retention?.retention_days ?? 3650)} /></Field>
            <Field label="Export policy"><Select disabled={!permissions.export && !permissions.retention} value={exportPolicy} onChange={event => setExportPolicy(event.target.value as typeof exportPolicy)}><Option value="admins">Admins</Option><Option value="moderators">Moderators</Option><Option value="members">Members</Option><Option value="disabled">Disabled</Option></Select></Field>
            <Field label="DLP"><Select disabled={!permissions.dlp} value={dlpMode} onChange={event => setDlpMode(event.target.value as 'inherit' | 'off')}><Option value="inherit">Inherit workspace DLP</Option><Option value="off">Off for this conversation</Option></Select></Field>
          </div>
          {(permissions.guests || permissions.retention || permissions.export || permissions.dlp) && <Button size="sm" variant="primary" loading={busy === 'policy'} onClick={() => void savePolicy()}><Check size={13} /> Save policy</Button>}
        </section>

        {permissions.guests && <section className="chat-enterprise-section">
          <div className="chat-enterprise-section-title"><UserPlus size={15} /><div><strong>External collaborators</strong><small>Single-conversation access with a hard expiry.</small></div></div>
          {!externalAccess && <Alert tone="warning">Enable External access and save the conversation policy before sending an invitation.</Alert>}
          <div className="chat-enterprise-grid">
            <Field label="Email"><Input type="email" value={inviteEmail} onChange={event => setInviteEmail(event.target.value)} placeholder="guest@partner.com" /></Field>
            <Field label="Type"><Select value={inviteType} onChange={event => setInviteType(event.target.value as typeof inviteType)}><Option value="guest">Guest</Option><Option value="client">Client</Option><Option value="vendor">Vendor</Option></Select></Field>
            <Field label="Company"><Input value={inviteCompany} onChange={event => setInviteCompany(event.target.value)} placeholder="External company" /></Field>
            <Field label="Access expires"><Input type="datetime-local" value={inviteExpiry} onChange={event => setInviteExpiry(event.target.value)} /></Field>
          </div>
          <Button size="sm" variant="outline" loading={busy === 'invite'} disabled={!externalAccess || !inviteEmail.trim() || !inviteExpiry} onClick={() => void invite()}><UserPlus size={13} /> Create invitation</Button>
          {inviteLink && <div className="chat-enterprise-secret"><strong>Copy this invitation now</strong><code>{inviteLink}</code><Button size="sm" variant="outline" onClick={() => void navigator.clipboard.writeText(inviteLink)}><Copy size={12} /> Copy</Button></div>}
          <div className="chat-enterprise-list">
            {(overview?.external_members ?? []).map(member => <article key={member.id}><div><strong>{member.name || member.email || 'External collaborator'}</strong><small>{member.external_company || member.email || 'External'} · expires {member.external_expires_at ? new Date(member.external_expires_at).toLocaleString() : 'not set'}</small></div><Badge tone={member.status === 'active' && !member.expired ? 'success' : 'neutral'}>{member.collaboration_type}</Badge><Button size="sm" variant="outline" loading={busy === `member-${member.id}`} onClick={() => void extendExternal(member)}>+30 days</Button>{member.status === 'active' ? <Button size="sm" variant="outline" loading={busy === `member-${member.id}`} onClick={() => void revokeExternal(member)}>Revoke</Button> : <Button size="sm" variant="outline" loading={busy === `member-${member.id}`} disabled={member.expired} onClick={() => void restoreExternal(member)}>Restore</Button>}</article>)}
            {(overview?.pending_external_invitations ?? []).filter(inviteRow => inviteRow.conversation_id === conversation.id).map(inviteRow => <article key={`invite-${inviteRow.id}`}><div><strong>{inviteRow.email}</strong><small>Pending invitation · {inviteRow.external_company || inviteRow.collaboration_type}</small></div><Badge tone="warning">pending</Badge></article>)}
          </div>
        </section>}

        {permissions.legalHold && <section className="chat-enterprise-section">
          <div className="chat-enterprise-section-title"><LockKeyhole size={15} /><div><strong>Legal hold</strong><small>Preserve governed chat data regardless of normal retention deletion.</small></div></div>
          <Field label="Scope"><Select value={holdScope} onChange={event => setHoldScope(event.target.value as 'conversation' | 'workspace')}><Option value="conversation">This conversation</Option><Option value="workspace">All workspace chat</Option></Select></Field>
          <Field label="Hold name"><Input value={holdName} onChange={event => setHoldName(event.target.value)} placeholder="Case or matter name" /></Field>
          <Field label="Reason"><Textarea rows={2} value={holdReason} onChange={event => setHoldReason(event.target.value)} placeholder="Optional legal or investigation context" /></Field>
          <Button size="sm" variant="outline" loading={busy === 'hold'} disabled={!holdName.trim()} onClick={() => void createHold()}><LockKeyhole size={13} /> Place legal hold</Button>
          <div className="chat-enterprise-list">{activeHolds.map(hold => <article key={hold.id}><div><strong>{hold.name}</strong><small>{hold.conversation_id ? 'Conversation hold' : 'Workspace-wide hold'}{hold.reason ? ` · ${hold.reason}` : ''}</small></div><Badge tone="warning">active</Badge><Button size="sm" variant="outline" loading={busy === `hold-${hold.id}`} onClick={() => void releaseHold(hold)}>Release</Button></article>)}{!activeHolds.length && <div className="chat-empty">No active legal hold affects this conversation.</div>}</div>
        </section>}

        {permissions.export && <section className="chat-enterprise-section">
          <div className="chat-enterprise-section-title"><FileSearch size={15} /><div><strong>eDiscovery</strong><small>Generate a private, expiring export of authorized conversation data.</small></div></div>
          <div className="chat-enterprise-actions"><Button size="sm" variant="outline" loading={busy === 'export-json'} disabled={exportPolicy === 'disabled'} onClick={() => void createExport('json')}><Archive size={13} /> JSON export</Button><Button size="sm" variant="outline" loading={busy === 'export-csv'} disabled={exportPolicy === 'disabled'} onClick={() => void createExport('csv')}><Archive size={13} /> CSV export</Button></div>
          <div className="chat-enterprise-list">{conversationExports.map(job => <article key={job.id}><div><strong>{job.format.toUpperCase()} export #{job.id}</strong><small>{job.status} · expires {job.expires_at ? new Date(job.expires_at).toLocaleString() : 'soon'}</small></div><Badge tone={job.status === 'completed' ? 'success' : job.status === 'failed' ? 'danger' : 'neutral'}>{job.status}</Badge>{job.status === 'completed' && <Button size="sm" variant="outline" onClick={() => void downloadExport(job)}><Download size={12} /> Download</Button>}</article>)}{!conversationExports.length && <div className="chat-empty">No exports generated by you for this conversation.</div>}</div>
        </section>}

        {permissions.dlp && <section className="chat-enterprise-section">
          <div className="chat-enterprise-section-title"><AlertTriangle size={15} /><div><strong>DLP</strong><small>Monitor, quarantine or block sensitive chat content using deterministic policies.</small></div></div>
          <div className="chat-enterprise-grid">
            <Field label="Policy name"><Input value={dlpName} onChange={event => setDlpName(event.target.value)} placeholder="Sensitive financial data" /></Field>
            <Field label="Mode"><Select value={dlpPolicyMode} onChange={event => setDlpPolicyMode(event.target.value as typeof dlpPolicyMode)}><Option value="monitor">Monitor</Option><Option value="quarantine">Quarantine</Option><Option value="block">Block</Option></Select></Field>
            <Field label="Keywords"><Input value={dlpKeywords} onChange={event => setDlpKeywords(event.target.value)} placeholder="secret, card number, confidential" /></Field>
            <Field label="File extensions"><Input value={dlpExtensions} onChange={event => setDlpExtensions(event.target.value)} placeholder="exe, key, pem" /></Field>
            <Field label="Max file MB"><Input type="number" min={0.001} value={dlpMaxMb} onChange={event => setDlpMaxMb(event.target.value)} placeholder="25" /></Field>
          </div>
          <Button size="sm" variant="outline" loading={busy === 'dlp'} disabled={!dlpName.trim()} onClick={() => void createDlp()}><Shield size={13} /> Create DLP policy</Button>
          <div className="chat-enterprise-list">{(overview?.dlp_policies ?? []).map(policy => <article key={policy.id}><div><strong>{policy.name}</strong><small>{policy.mode} · {(policy.keywords ?? []).length} keyword rules · {(policy.file_extensions ?? []).length} file rules</small></div><Badge tone={policy.active ? 'success' : 'neutral'}>{policy.active ? 'active' : 'disabled'}</Badge><Button size="sm" variant="outline" loading={busy === `dlp-${policy.id}`} onClick={() => void toggleDlp(policy)}>{policy.active ? 'Disable' : 'Enable'}</Button></article>)}</div>
          {(overview?.dlp_events ?? []).length > 0 && <div className="chat-enterprise-audit"><strong>Recent DLP events</strong>{overview!.dlp_events.slice(0, 8).map(event => <small key={event.id}>#{event.id} · {event.action} · conversation {event.conversation_id ?? 'n/a'}</small>)}</div>}
        </section>}

        <section className="chat-enterprise-section">
          <div className="chat-enterprise-section-title"><Users size={15} /><div><strong>Moderation audit</strong><small>Recent immutable enterprise governance events.</small></div></div>
          <div className="chat-enterprise-audit">{(overview?.moderation_events ?? []).slice(0, 12).map(event => <small key={event.id}>#{event.id} · {event.action}{event.reason ? ` · ${event.reason}` : ''}</small>)}{!(overview?.moderation_events ?? []).length && <span>No moderation events yet.</span>}</div>
        </section>
      </>}
    </div>
  </Modal>
}
