import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { readSource } from './source-bundles.mjs'

/** Read one repository source file for dependency-free UX contract assertions. */
const read=file=>readSource(file)

test('silent auth restore and auth invalidation do not emit repeated unauthenticated request toasts',()=>{
  const auth=read('resources/js/auth/authService.ts')
  const client=read('resources/js/api/client.ts')
  assert.match(auth,/auth\/me',[\s\S]*silent:\s*true/)
  assert.match(client,/const authInvalidated\s*=\s*invalidateAuthOnStatus/)
  assert.match(client,/!silent\s*&&\s*!authInvalidated/)
})

test('date pickers and transient overlays use body portals with deterministic layering',()=>{
  const ui=read('resources/js/design-system/index.tsx')
  const css=read('resources/js/design-system/toolkit.css')
  assert.match(ui,/portalId="workintel-datepicker-portal"/)
  assert.match(ui,/workintel:overlay-open/)
  assert.match(ui,/document\.addEventListener\('pointerdown'/)
  assert.match(css,/#workintel-datepicker-portal\{z-index:1860\}/)
  assert.match(css,/\.ui-dropdown--portal,.ui-popover--portal,.ui-select-menu\{z-index:1850!important\}/)
  assert.match(css,/\.ui-tooltip\{z-index:1900\}/)
})

test('owner navigation follows the M3 module taxonomy without duplicate intent groups',()=>{
  const manifest=JSON.parse(read('resources/js/navigation.manifest.json'))
  const owner=manifest.owner
  const byId=new Map(owner.map(group=>[group.id,group.items.map(item=>item[0])]))
  assert.deepEqual(byId.get('time-attendance'),['schedule','attendance','leave','time'])
  assert.deepEqual(byId.get('content-studio'),['website','documents','media'])
  assert.deepEqual(byId.get('collaboration'),['chat'])
  assert.deepEqual(byId.get('work-management'),['approvals','projects','tasks','automations'])
  const access=read('resources/js/access.ts')
  assert.match(access,/MULTI_PAGE_MODULE_LABELS/)
  assert.match(access,/attendance/)
  assert.match(access,/activity/)
})

test('task filters separate scope from the common board-list presentation control',()=>{
  const tasks=read('resources/js/pages/Tasks.tsx')
  assert.match(tasks,/useState<\s*'all'\s*\|\s*'my'\s*>\s*\(\s*'all'\s*\)/)
  assert.match(tasks,/ViewModeToggle/)
  assert.match(tasks,/gridLabel="Board" tableLabel="List"/)
  assert.match(tasks,/FormSection title="Task details"/)
  assert.match(tasks,/FormSection title="Ownership & workflow"/)
  assert.match(tasks,/FormSection title="Planning"/)
})

test('file and image selection uses one chooser that contains library and upload modes',()=>{
  const field=read('resources/js/media/MediaFileField.tsx')
  const picker=read('resources/js/media/MediaPicker.tsx')
  const profile=read('resources/js/pages/MyAccess.tsx')
  assert.match(field,/Choose \{imagesOnly\?'image':'file'\}/)
  assert.equal((field.match(/onClick=\{\(\)=>setPicker\(true\)\}/g)||[]).length,1)
  assert.match(picker,/Media Library/)
  assert.match(picker,/> Upload</)
  assert.match(picker,/mediaUploadConstraintError/)
  assert.match(picker,/Use \{selectedAssets.length\} selected/)
  assert.match(profile,/Change photo/)
  assert.doesNotMatch(profile,/AvatarCropper/)
})

test('Website Document and Chat expose focused high-density professional work modes',()=>{
  const website=read('resources/js/pages/WebsiteStudio.tsx')
  const documents=read('resources/js/pages/Documents.tsx')
  const chat=read('resources/js/pages/Chat.tsx')
  assert.match(website,/websitePageAudit/)
  assert.match(website,/Publish preflight/)
  assert.match(website,/focusMode/)
  assert.match(documents,/documentPreflight/)
  assert.match(documents,/focusMode/)
  assert.match(documents,/Run server preflight/)
  assert.match(chat,/ConversationFilter/)
  assert.match(chat,/filteredConversations/)
  assert.match(chat,/Unread/)
})
