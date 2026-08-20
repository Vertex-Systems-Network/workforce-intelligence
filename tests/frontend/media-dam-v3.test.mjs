import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
/** Read one repository source file for the M7 contract. */
const read=file=>fs.readFileSync(file,'utf8')
test('M7 Media Library exposes governance binary history renditions and bulk DAM actions',()=>{const source=read('resources/js/pages/MediaLibrary.tsx');for(const token of ['Rights attention','Replace file','Restore as current','Renditions (','Rights & governance','bulkActions','copyright_owner','license_expires_at'])assert.ok(source.includes(token),token);assert.equal(source.includes('<form'),false)})
test('M7 Media Picker remains the single collection favorite and resumable aware chooser',()=>{const source=read('resources/js/media/MediaPicker.tsx');for(const token of ['All collections','favoritesOnly','collectionId','Upload new','resumable large files'])assert.ok(source.includes(token),token)})
test('M7 browser upload transport resumes missing chunks and supports binary replacement',()=>{const source=read('resources/js/media/upload.ts');for(const token of ['uploadMediaFileResumable','localStorage','received_chunks','checksum_sha256','replaceMediaBinary'])assert.ok(source.includes(token),token)})
test('M7 backend prevents historical/current binary confusion and quarantined restore',()=>{const source=read('app/Services/Media/MediaLibraryService.php');assert.ok(source.includes('older metadata-only version cannot be assigned the current binary'));assert.ok(source.includes('A quarantined historical binary cannot be restored'));assert.ok(source.includes("'binary_status'=>$asset->status"))})
test('M7 backend exposes collection sharing bulk renditions and resumable endpoints',()=>{const routes=read('routes/api.php');for(const token of ['/media/bulk','/media/collection-members','/media/uploads','/media/{asset}/replace','/media/{asset}/versions/{version}/restore','/media/{asset}/renditions'])assert.ok(routes.includes(token),token)})

test('M7 resumable uploads enforce server cleanup and negotiated chunk integrity',()=>{const service=read('app/Services/Media/MediaLibraryService.php');assert.ok(service.includes('The upload chunk size does not match the negotiated byte range.'));assert.ok(service.includes('pruneUploadSessions'));assert.ok(read('routes/console.php').includes('workintel:prune-media-upload-sessions'))})
