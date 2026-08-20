import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { readSource } from './source-bundles.mjs'

const root = path.resolve(import.meta.dirname, '../..')

/** Reads one WorkIntel source file for dependency-free Chat V2.2 contract tests. */
function read(relativePath) {
  return readSource(relativePath)
}

test('professional chat routes include durable drafts threads saved messages forwarding polls and edit history', () => {
  const routes = read('routes/chat.php')
  for (const token of ['/draft', '/thread', '/save', '/forward', '/history', '/polls/{poll}/vote']) assert.ok(routes.includes(token), token)
})

test('professional chat UI exposes real message actions and advanced search operators', () => {
  const page = read('resources/js/pages/Chat.tsx')
  for (const token of ['Saved Messages', 'Edit history', 'Forward message', 'Create poll', 'Reply in thread', 'from:', 'in:', 'before:', 'after:', 'has:file', 'has:link']) assert.ok(page.includes(token), token)
})

test('chat professional migration is additive and keeps the original collaboration tables intact', () => {
  const migration = read('database/migrations/2026_08_13_000400_create_chat_professional_messaging.php')
  for (const token of ['chat_message_edit_history', 'chat_saved_messages', 'chat_drafts', 'chat_polls', 'chat_poll_options', 'chat_poll_votes', 'chat_thread_follows', 'forwarded_from_message_id']) assert.ok(migration.includes(token), token)
  assert.ok(!migration.includes("Schema::dropIfExists('chat_messages')"))
})
