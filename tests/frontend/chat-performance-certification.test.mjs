import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'
import { readSource } from './source-bundles.mjs'

const page = readSource('resources/js/pages/Chat.tsx')
const styles = fs.readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8')
const service = fs.readFileSync(new URL('../../app/Services/Chat/ChatService.php', import.meta.url), 'utf8')
const routes = fs.readFileSync(new URL('../../routes/chat.php', import.meta.url), 'utf8')

/** Verifies the client uses cursor history instead of replacing the full message history on every poll. */
test('chat uses older/newer cursor synchronization', () => {
  for (const token of ['?after=', '?before=', '?around=', 'loadOlderMessages', 'mergeMessageWindow', 'Load older messages']) assert.ok(page.includes(token), token)
  assert.ok(service.includes('messagePage'))
  assert.ok(service.includes("where('id', '>', $after)"))
})

/** Verifies reconnect delivery uses persistent idempotency keys and visible retry state. */
test('chat owns persistent offline outbox and idempotency recovery', () => {
  for (const token of ['workintel-chat-outbox', 'createClientMessageId', 'client_message_id', 'Queued for delivery', 'Delivery failed', 'retryOutboxMessage']) assert.ok(page.includes(token), token)
  assert.ok(service.includes('client_message_id'))
  assert.ok(service.includes('lockForUpdate'))
})

/** Verifies sibling tabs and browser rendering are bounded for long-running chat sessions. */
test('chat synchronizes tabs and bounds browser rendering work', () => {
  assert.ok(page.includes('BroadcastChannel'))
  assert.ok(page.includes('workintel-chat:'))
  assert.ok(styles.includes('content-visibility:auto'))
  assert.ok(styles.includes('.chat-sync-state'))
})

/** Verifies server-side rate and attachment safety controls remain authoritative. */
test('chat applies rate and attachment safety boundaries', () => {
  for (const token of ['throttle:600,1', 'throttle:60,1', 'throttle:120,1']) assert.ok(routes.includes(token), token)
  for (const token of ['attachment_total_mb', 'blocked_extensions', 'Combined attachment size exceeds']) assert.ok(service.includes(token), token)
})
