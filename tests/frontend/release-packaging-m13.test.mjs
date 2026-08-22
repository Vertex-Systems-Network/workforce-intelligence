import assert from 'node:assert/strict'
import fs from 'node:fs'
import test from 'node:test'

const read = file => fs.readFileSync(file, 'utf8')

test('M13 release packages use normalized deterministic ZIP metadata', () => {
  const builder = read('tools/build-releases.py')
  for (const token of [
    'ZIP_EPOCH = (1980, 1, 1, 0, 0, 0)',
    'zipfile.ZIP_STORED',
    'zipfile.ZipInfo',
    'archive.writestr',
    'sorted(files, key=lambda item: item[1])',
    'info.external_attr = (0o100000 | archive_mode(source)) << 16',
  ]) assert.ok(builder.includes(token), token)
  assert.ok(!builder.includes('archive.write('))
})

test('M13 release reproducibility audit is enforced by Linux certification', () => {
  const audit = read('tools/release-reproducibility-audit.py')
  const ci = read('.github/workflows/ci.yml')
  for (const token of [
    'os.utime(source, (stamp, stamp))',
    'Release ZIP hashes changed after mtime-only mutations',
    'validate_catalog(work_root, first_hashes)',
    'validate_catalog(work_root, second_hashes)',
  ]) assert.ok(audit.includes(token), token)
  assert.ok(ci.includes('python3 tools/release-reproducibility-audit.py'))
})
