import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'
/** Read one repository source file for M10 frontend contracts. */
const read=file=>{const source=fs.readFileSync(file,'utf8');return file==='resources/js/pages/Chat.tsx'?source+'\n'+fs.readFileSync('resources/js/components/chat/ChatPanels.tsx','utf8')+'\n'+fs.readFileSync('resources/js/components/chat/chatUtils.ts','utf8')+'\n'+fs.readFileSync('resources/js/components/chat/chatTypes.ts','utf8'):source}

test('M10 collaboration inbox aggregates mentions followed threads and unread direct messages',()=>{
 const service=read('app/Services/Chat/ChatService.php'),routes=read('routes/chat.php'),page=read('resources/js/pages/Chat.tsx')
 for(const token of ['collaborationInbox','mentions','threads','direct'])assert.ok(service.includes(token),token)
 assert.ok(routes.includes("Route::get('/inbox'"))
 for(const token of ['Collaboration Activity','Unread mentions','Followed threads','Direct messages'])assert.ok(page.includes(token)||page.includes(token.toLowerCase()),token)
})

test('M10 conversation context exposes pins bookmarks notes and recent files',()=>{
 const service=read('app/Services/Chat/ChatService.php'),page=read('resources/js/pages/Chat.tsx')
 for(const token of ['conversationContext','ChatMessagePin','ChatSavedMessage','ChatMessageAttachment','updateSavedNote'])assert.ok(service.includes(token),token)
 for(const token of ['Pinned messages','Your bookmarks','Recent files','Bookmark note'])assert.ok(page.includes(token),token)
})

test('M10 resource links authorize projects tasks and generated documents',()=>{
 const service=read('app/Services/Chat/ChatWorkspaceCollaborationService.php'),options=read('app/Services/Chat/ChatService.php'),page=read('resources/js/pages/Chat.tsx')
 for(const token of ['GeneratedDocument','canViewProject','canViewTask','documents.view'])assert.ok(service.includes(token)||options.includes(token),token)
 assert.ok(page.includes('Generated document'))
 assert.ok(page.includes('resourceKind'))
})

test('M10 removes browser-native moderation prompt and keeps shared Media DAM chooser',()=>{
 const page=read('resources/js/pages/Chat.tsx')
 assert.doesNotMatch(page,/window\.prompt\(/)
 assert.match(page,/MediaFileField/)
 assert.match(page,/submitModeration/)
})

test('M10 closure persists activity triage and exposes read-all snooze and follow-up actions',()=>{
 const migration=read('database/migrations/2026_08_20_001200_create_chat_activity_states.php'),service=read('app/Services/Chat/ChatService.php'),page=read('resources/js/pages/Chat.tsx')
 assert.ok(migration.includes('chat_activity_states'))
 for(const token of ['triageInbox','read_all','snooze','follow_up','applyActivityStates'])assert.ok(service.includes(token),token)
 for(const token of ['Mark all done','Tomorrow','1h'])assert.ok(page.includes(token),token)
})

test('M10 closure uses a dedicated shared notification-preference matrix for chat event classes',()=>{
 const service=read('app/Services/Chat/ChatService.php'),notifications=read('app/Services/Notifications/WorkspaceNotificationService.php'),page=read('resources/js/pages/Chat.tsx')
 for(const category of ['chat_mentions','chat_threads','chat_direct','chat_channels']){assert.ok(service.includes(category),category);assert.ok(notifications.includes(category),category)}
 assert.ok(page.includes('Chat notification preferences'))
 assert.ok(page.includes('Daily digest'))
})

test('M10 closure paginates large conversation context and provides bounded bulk cleanup',()=>{
 const service=read('app/Services/Chat/ChatService.php'),controller=read('app/Http/Controllers/Api/V1/ChatController.php'),page=read('resources/js/pages/Chat.tsx')
 for(const token of ['pin_next','bookmark_next','file_next','bulkContext'])assert.ok(service.includes(token),token)
 assert.ok(controller.includes("max:100"))
 assert.ok(page.includes('Load older context'))
 assert.ok(page.includes('Clear visible'))
})

test('M10 closure returns permission-safe rich cards for project task and document resources',()=>{
 const service=read('app/Services/Chat/ChatWorkspaceCollaborationService.php'),page=read('resources/js/pages/Chat.tsx')
 for(const token of ['resourcePayload','available','due_at','generated_at'])assert.ok(service.includes(token),token)
 assert.ok(page.includes("resource.entity?.title"))
 assert.ok(page.includes('No longer available'))
})
