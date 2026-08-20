import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'
import { readSource } from './source-bundles.mjs'

/** Read one repository source file for dependency-free contract assertions. */
const read=file=>readSource(file)

test('Website Studio exposes high-level responsive design history zoom and reusable media workflows',()=>{
  const source=read('resources/js/pages/WebsiteStudio.tsx')
  const renderer=read('resources/js/website/WebsiteRenderer.tsx')
  assert.match(source,/undoSchema/)
  assert.match(source,/redoSchema/)
  assert.match(source,/website-zoom-controls/)
  assert.match(source,/title="Design"|label:'Design'/)
  assert.match(source,/hide_desktop/)
  assert.match(source,/content_width/)
  assert.match(renderer,/sectionPresentation/)
  assert.match(renderer,/is-hidden-mobile/)
})

test('Document Studio exposes bounded history preflight zoom and keyboard editor controls',()=>{
  const source=read('resources/js/pages/Documents.tsx')
  assert.match(source,/documentPreflight/)
  assert.match(source,/commitEditor/)
  assert.match(source,/undoEditor/)
  assert.match(source,/redoEditor/)
  assert.match(source,/Run server preflight/)
  assert.match(source,/document-v4-zoom-controls/)
  assert.match(source,/event\.key\.toLowerCase\(\)\s*===\s*'s'/)
})

test('shared media chooser offers Media Library and Upload across file workflows',()=>{
  const chooser=read('resources/js/media/MediaFileField.tsx')
  const picker=read('resources/js/media/MediaPicker.tsx')
  assert.match(chooser,/Choose \{imagesOnly\?'image':'file'\}/)
  assert.match(chooser,/mediaAssetToFile/)
  assert.match(picker,/Media Library/)
  assert.match(picker,/Upload new/)
  for(const file of ['Settings.tsx','Tasks.tsx','FinanceOps.tsx','Hris.tsx','Chat.tsx']){
    assert.match(read(`resources/js/pages/${file}`),/MediaFileField/,`${file} must use the shared media/file chooser`)
  }
})

test('collection view switching and table foundations are shared instead of page-local',()=>{
  const ui=read('resources/js/design-system/index.tsx')
  const css=read('resources/js/design-system/toolkit.css')
  assert.match(ui,/export function ViewModeToggle/)
  assert.match(ui,/export function TableWrap/)
  assert.match(ui,/export function DataGrid/)
  assert.match(css,/\.ui-view-mode-toggle/)
  assert.match(css,/\.ui-table-wrap/)
  for(const file of ['People.tsx','Projects.tsx','MediaLibrary.tsx'])assert.match(read(`resources/js/pages/${file}`),/ViewModeToggle/)
  const pageFiles=fs.readdirSync(path.resolve('resources/js/pages')).filter(file=>file.endsWith('.tsx'))
  for(const file of pageFiles)assert.equal(read(`resources/js/pages/${file}`).includes('<table'),false,`${file} contains a raw table instead of the shared table foundation`)
})

test('Media Library upload transport reports progress storage health and server limits',()=>{
  const page=read('resources/js/pages/MediaLibrary.tsx')
  const controller=read('app/Http/Controllers/Api/V1/MediaController.php')
  const api=read('resources/js/api/client.ts')
  assert.match(api,/XMLHttpRequest/)
  assert.match(page,/media\/capabilities/)
  assert.match(page,/uploadProgress/)
  assert.match(page,/Storage ready/)
  assert.match(controller,/max_files_per_request/)
  assert.match(controller,/files\[\]/)
})
