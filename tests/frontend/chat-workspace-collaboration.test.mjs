import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { readSource } from './source-bundles.mjs'

const root = path.resolve(import.meta.dirname, '../..')

/** Reads one WorkIntel source file for dependency-free Chat V2.3 contract tests. */
function read(relativePath) {
  return readSource(relativePath)
}

test('workspace collaboration routes expose governed channels resources notifications and message actions', () => {
  const routes = read('routes/chat.php')
  for (const token of ['/public-channels', '/join', '/leave', '/members/{member}/role', '/notifications', '/resources', '/messages/{message}/actions']) assert.ok(routes.includes(token), token)
})

test('workspace collaboration UI exposes professional channel governance and workflow actions', () => {
  const page = read('resources/js/pages/Chat.tsx')
  for (const token of ['Public channels', 'Announcement', 'Create task', 'Create approval', 'Create incident', 'Mentions only', 'Channel resources', 'Lock channel', 'Read-only', '/assign']) assert.ok(page.includes(token), token)
})

test('slash commands use backend workflows and include task assignment', () => {
  const service = read('app/Services/Chat/ChatWorkspaceCollaborationService.php')
  for (const token of ["'/help'", "'/task'", "'/assign'", "'/poll'", "'/status'", 'commandAssign', 'createTaskFromMessage', 'createApprovalFromMessage', 'createIncidentFromMessage']) assert.ok(service.includes(token), token)
})

test('notification modes drive real backend delivery and legacy mute stays synchronized', () => {
  const service = read('app/Services/Chat/ChatService.php')
  const migration = read('database/migrations/2026_08_13_000500_create_chat_workspace_collaboration.php')
  assert.ok(service.includes('notifyConversationMembers'))
  assert.ok(service.includes("'notification_mode' => $muted ? 'nothing' : 'all'"))
  assert.ok(migration.includes("where('is_muted', true)->update(['notification_mode' => 'nothing'])"))
})
