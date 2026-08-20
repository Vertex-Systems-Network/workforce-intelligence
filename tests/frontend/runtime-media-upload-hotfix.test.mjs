import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import path from 'node:path'

/** Read one repository source file for the runtime hotfix contract. */
const read=file=>fs.readFileSync(path.resolve(file),'utf8')

test('People and Website Studio import every shared UI component used by their new controls',()=>{
  const people=read('resources/js/pages/People.tsx')
  const website=read('resources/js/pages/WebsiteStudio.tsx')
  assert.match(people,/import \{[^\n]*ViewModeToggle[^\n]*\} from '\.\.\/design-system'/)
  assert.match(website,/import \{[^\n]*SearchInput[^\n]*\} from '\.\.\/design-system'/)
})

test('Media upload inspection accepts a readable UploadedFile pathname without requiring realpath',()=>{
  const security=read('app/Services/Security/UploadSecurityService.php')
  const media=read('app/Services/Media/MediaLibraryService.php')
  assert.match(security,/getPathname\(\)/)
  assert.match(security,/getRealPath\(\)/)
  assert.match(security,/is_readable\(\$candidate\)/)
  assert.match(security,/\'path\' => \$path/)
  assert.match(media,/\$realPath = \(string\) \$inspection\['path'\]/)
  assert.doesNotMatch(media,/\$file->getRealPath\(\)/)
})

test('Native upload inputs are never rendered as visible browser controls',()=>{
  const page=read('resources/js/pages/MediaLibrary.tsx')
  const picker=read('resources/js/media/MediaPicker.tsx')
  assert.doesNotMatch(page,/className="sr-only"/)
  assert.doesNotMatch(picker,/className="sr-only"/)
  assert.match(page,/<HiddenFileInput ref=\{fileRef\}/)
  assert.match(picker,/<HiddenFileInput ref=\{inputRef\}/)
})
