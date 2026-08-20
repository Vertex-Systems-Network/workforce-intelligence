import fs from 'node:fs'
/** Read one repository file for dependency-free M10 source contracts. */
const read=file=>fs.readFileSync(file,'utf8')
const failures=[]
const page=read('resources/js/pages/Chat.tsx')+'\n'+read('resources/js/components/chat/ChatPanels.tsx')+'\n'+read('resources/js/components/chat/chatUtils.ts')+'\n'+read('resources/js/components/chat/chatTypes.ts'),service=read('app/Services/Chat/ChatService.php'),collab=read('app/Services/Chat/ChatWorkspaceCollaborationService.php'),routes=read('routes/chat.php'),css=read('resources/css/app.css')
/** Record one missing M10 source marker without aborting the remaining audit checks. */
const need=(src,token,label)=>{if(!src.includes(token))failures.push(`${label}: ${token}`)}
for(const token of ["/inbox","/conversations/{conversation}/context","/save-note"])need(routes,token,'route')
for(const token of ['collaborationInbox','conversationContext','updateSavedNote','GeneratedDocument'])need(service,token,'service')
for(const token of ['Activity','Pinned messages','Recent files','Your bookmarks','Generated document','Bookmark note'])need(page,token,'ui')
for(const token of ['chat-activity-grid','chat-context-list','chat-resource-row'])need(css,token,'styles')
for(const token of ['GeneratedDocument','canViewProject','canViewTask'])need(collab,token,'resource auth')

need(read('database/migrations/2026_08_20_001200_create_chat_activity_states.php'),'chat_activity_states','triage schema')
for(const token of ['triageInbox','chatNotificationPreferences','updateChatNotificationPreferences','bulkContext','pin_next','bookmark_next','file_next'])need(service,token,'closure service')
for(const token of ['Mark all done','Chat notification preferences','Load older context','Clear visible','No longer available'])need(page,token,'closure ui')
for(const token of ['/inbox/triage','/notification-preferences','/context/bulk'])need(routes,token,'closure route')
for(const token of ['resourcePayload','available','due_at'])need(collab,token,'resource cards')
if(page.includes('window.prompt('))failures.push('Chat.tsx still contains browser-native window.prompt().')
if(!page.includes('MediaFileField'))failures.push('Shared Media Library / Upload attachment workflow is missing.')
if(failures.length){for(const failure of failures)console.error('FAIL:',failure);process.exit(1)}
console.log('M10 Chat & Collaboration V4 audit: PASS')
