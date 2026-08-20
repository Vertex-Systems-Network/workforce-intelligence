import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'
import { readSource } from './source-bundles.mjs'

const root = path.resolve(import.meta.dirname, '../..')

/** Reads one WorkIntel source file for dependency-free Chat V2.1 contract tests. */
function read(relativePath) {
  return readSource(relativePath)
}

test('new conversation picker excludes the current workspace member on both layers', () => {
  const service = read('app/Services/Chat/ChatService.php')
  const page = read('resources/js/pages/Chat.tsx')
  assert.match(service, /where\('id', '!=', \$member->id\)/)
  assert.match(service, /SELF_CONVERSATION_NOT_ALLOWED/)
  assert.match(page, /options\.people\.filter\(person\s*=>\s*person\.id\s*!==\s*currentMemberId/)
})

test('chat UI has one-panel mobile navigation and scroll-safe unread behavior', () => {
  const page = read('resources/js/pages/Chat.tsx')
  const css = read('resources/css/app.css')
  for (const token of ['chat-mobile-', 'Jump to latest', 'chat-unread-divider', 'nearBottom', 'announceTyping']) assert.ok(page.includes(token), token)
  assert.ok(css.includes('@media(max-width:760px)'))
  assert.ok(css.includes('border-inline-start:3px solid var(--warning)'))
  assert.ok(css.includes('html[dir="rtl"] .chat-mobile-back svg'))
})

test('presence dots do not use the signed-in member as their online peer', () => {
  const page = read('resources/js/pages/Chat.tsx')
  assert.match(page, /conversation\.members\.filter\(member\s*=>\s*!member\.is_self\s*&&\s*member\.id\s*!==\s*viewerMemberId\)/)
  assert.match(page, /otherMembers\.slice\(0,\s*4\)/)
})
