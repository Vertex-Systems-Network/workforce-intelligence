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

test('M13 published releases are immutable under the same semantic version', () => {
  const builder = read('tools/build-releases.py')
  const audit = read('tools/release-immutability-audit.py')
  const ci = read('.github/workflows/ci.yml')
  for (const token of [
    'verify_published_binary(destination, previous)',
    'archive_payload(destination) != archive_payload(candidate)',
    'changed without a version bump',
    'Refusing to overwrite untracked release binary',
    "previous.get('released_at') if previous else utc_now()",
  ]) assert.ok(builder.includes(token), token)
  for (const token of [
    'assert_unchanged_rebuild()',
    "'desktop-agent/PRODUCTION_AGENT.md'",
    "'browser-extension/popup.css'",
    "'browser-extension/firefox/popup.css'",
    'assert_manifest_integrity_is_authoritative()',
  ]) assert.ok(audit.includes(token), token)
  assert.ok(ci.includes('python3 tools/release-immutability-audit.py'))
})

test('M13 release publication validates the whole catalog before committing new artifacts', () => {
  const builder = read('tools/build-releases.py')
  const audit = read('tools/release-immutability-audit.py')
  for (const token of [
    'def prepare_zip(staging: Path, slug: str, name: str, version: str, entries)',
    'def commit_release_transaction(plans: list[dict], manifest_bytes: bytes, checksums_bytes: bytes, staging: Path)',
    "with tempfile.TemporaryDirectory(prefix='workintel-release-transaction-')",
    'for plan in plans:',
    'Refusing to overwrite release binary that appeared after validation',
    'atomic_restore(MANIFEST_PATH, old_manifest, staging)',
  ]) assert.ok(builder.includes(token), token)
  for (const token of [
    'assert_mixed_version_validation_is_transactional()',
    'M13_TRANSACTIONAL_BROWSER_DRIFT',
    'Failed mixed-version validation partially published release state',
    "glob(f'WorkIntel-Agent-*-{next_version}.zip')",
  ]) assert.ok(audit.includes(token), token)
})
