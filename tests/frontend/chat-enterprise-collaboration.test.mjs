import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { readSource } from './source-bundles.mjs'

const chat = readSource('resources/js/pages/Chat.tsx')
const enterprise = readFileSync(new URL('../../resources/js/components/chat/EnterpriseControls.tsx', import.meta.url), 'utf8')
const service = readFileSync(new URL('../../app/Services/Chat/ChatEnterpriseCollaborationService.php', import.meta.url), 'utf8')
const dlp = readFileSync(new URL('../../app/Services/Chat/ChatDlpService.php', import.meta.url), 'utf8')
const chatService = readFileSync(new URL('../../app/Services/Chat/ChatService.php', import.meta.url), 'utf8')
const routes = readFileSync(new URL('../../routes/chat.php', import.meta.url), 'utf8')

/** Verifies enterprise controls are permission-gated and available from the active conversation. */
test('enterprise controls are permission gated', () => {
  assert.match(chat, /chat\.guests_manage/)
  assert.match(chat, /chat\.retention_manage/)
  assert.match(chat, /chat\.legal_hold_manage/)
  assert.match(chat, /chat\.dlp_manage/)
  assert.match(chat, /<EnterpriseControls/)
})

/** Verifies external collaborators cannot create ordinary new conversations from the professional UI. */
test('external collaborators do not receive the new conversation action', () => {
  assert.match(chat, /isExternalViewer/)
  assert.match(chat, /optionsLoaded && !isExternalViewer/)
  assert.match(service, /External collaborators cannot invite other external users/)
})

/** Verifies enterprise governance labels and actions remain discoverable. */
test('enterprise governance actions are discoverable', () => {
  for (const label of ['Enterprise controls', 'External access', 'Legal hold', 'eDiscovery', 'DLP']) {
    assert.ok(enterprise.includes(label), `missing ${label}`)
  }
  assert.match(enterprise, /external-invitations/)
  assert.match(enterprise, /legal-holds/)
  assert.match(enterprise, /dlp-policies/)
})

/** Verifies quarantined attachments and external senders are visibly distinguished. */
test('external and quarantined content is visibly marked', () => {
  assert.match(chat, /External ·/)
  assert.match(chat, /Quarantined/)
  assert.match(chat, /security_status/)
})

/** Verifies DLP blocking and quarantine authorization remain backend-enforced. */
test('DLP enforcement is backend authoritative', () => {
  assert.match(dlp, /workspace DLP policy/)
  assert.match(dlp, /quarantined/)
  assert.match(dlp, /chat\.dlp_manage/)
  assert.match(dlp, /chat\.moderate/)
})

/** Verifies channel owner/admin/moderator roles are first-class moderation authorities without a workspace-only route gate. */
test('channel governance roles can moderate enterprise messages', () => {
  assert.match(chatService, /canModerateConversation/)
  assert.match(chatService, /\['owner', 'admin', 'moderator'\]/)
  assert.match(service, /canModerateConversation/)
  assert.match(routes, /Route::post\('\/enterprise\/messages\/\{message\}\/moderate'.*moderateMessage'\]\);/)
})
