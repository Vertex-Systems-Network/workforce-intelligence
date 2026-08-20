import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import test from 'node:test'

const root = path.resolve(import.meta.dirname, '../..')
/** Reads a project source file for dependency-free Block D frontend contract checks. */
function read(relativePath) { return fs.readFileSync(path.join(root, relativePath), 'utf8') }

test('Media Library and Trash Center are first-class routed workspace surfaces', () => {
  const app = read('resources/js/WorkforceApp.tsx')
  const navigation = read('resources/js/navigation.manifest.json')
  for (const token of ['MediaLibrary', 'TrashCenter', "case 'media'", "case 'trash'"]) assert.ok(app.includes(token), token)
  for (const token of ['\"media\"', '\"trash\"']) assert.ok(navigation.includes(token), token)
})

test('avatar workflow uses one reusable Media Library picker with upload mode', () => {
  const profile = read('resources/js/pages/MyAccess.tsx')
  const picker = read('resources/js/media/MediaPicker.tsx')
  assert.equal(profile.includes('AvatarCropper'), false)
  assert.ok(profile.includes('MediaPicker'))
  assert.ok(profile.includes('/api/v1/media/avatar'))
  assert.ok(profile.includes('Change photo'))
  assert.ok(picker.includes('Media Library'))
  assert.ok(picker.includes('Upload new'))
})

test('people administration uses managed Media Library photos instead of raw avatar URLs', () => {
  const people = read('resources/js/pages/People.tsx')
  assert.ok(people.includes('MediaPicker'))
  assert.ok(people.includes('/avatar'))
  assert.equal(people.includes('label="Avatar URL"'), false)
})

test('business tables expose Archive separately from recoverable Trash', () => {
  const clients = read('resources/js/pages/Clients.tsx')
  const projects = read('resources/js/pages/Projects.tsx')
  const tasks = read('resources/js/pages/Tasks.tsx')
  assert.ok(clients.includes('Archive client') && clients.includes('Move to Trash'))
  assert.ok(projects.includes('Archive project') && projects.includes('Move to Trash'))
  assert.ok(tasks.includes('/api/v1/lifecycle/task/') && tasks.includes('Move to Trash'))
})

test('lazy routes use destination-shaped loading skeletons', () => {
  const app = read('resources/js/WorkforceApp.tsx')
  for (const token of ['MediaLibraryLoadingState', 'TableLoadingState', 'BoardLoadingState', 'ProfileLoadingState', 'FormLoadingState']) assert.ok(app.includes(token), token)
  assert.ok(app.includes('pageLoadingFallback(page)'))
})
