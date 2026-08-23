#!/usr/bin/env python3
"""Build deterministic deployment ZIPs without mutating published release bytes."""

from datetime import datetime, timezone
from pathlib import Path
import hashlib
import json
import os
import re
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'storage/app/releases'
OUT.mkdir(parents=True, exist_ok=True)
MANIFEST_PATH = OUT / 'manifest.json'
CHECKSUMS_PATH = OUT / 'SHA256SUMS.txt'
ZIP_EPOCH = (1980, 1, 1, 0, 0, 0)


def utc_now() -> str:
    """Return one UTC release-catalog timestamp."""
    return datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z')


def read_previous_manifest() -> dict:
    """Read the existing published release catalog when present."""
    if not MANIFEST_PATH.is_file():
        return {'version': 2, 'generated_at': None, 'releases': []}
    payload = json.loads(MANIFEST_PATH.read_text(encoding='utf-8'))
    if not isinstance(payload, dict) or not isinstance(payload.get('releases'), list):
        raise RuntimeError('Existing release manifest is invalid.')
    return payload


PREVIOUS_MANIFEST = read_previous_manifest()
PREVIOUS_BY_SLUG = {row['slug']: row for row in PREVIOUS_MANIFEST['releases'] if isinstance(row, dict) and row.get('slug')}

agent_source = (ROOT / 'desktop-agent/native-agent.mjs').read_text(encoding='utf-8')
agent_match = re.search(r"const VERSION = ['\"]([^'\"]+)['\"]", agent_source)
if not agent_match:
    raise RuntimeError('Could not determine native agent VERSION.')

agent_version = agent_match.group(1)
browser_version = '1.0.0'


def archive_mode(path: Path) -> int:
    """Return stable Unix permissions for one release archive entry."""
    return 0o755 if path.suffix.lower() in {'.sh', '.command'} else 0o644


def archive_files(entries):
    """Expand release inputs into a stable, path-sorted file list."""
    files = []
    for source, archive_path in entries:
        source = ROOT / source
        if source.is_dir():
            for child in source.rglob('*'):
                if child.is_file():
                    relative = child.relative_to(source).as_posix()
                    files.append((child, f"{Path(archive_path).as_posix().rstrip('/')}/{relative}"))
        else:
            files.append((source, Path(archive_path).as_posix()))
    return sorted(files, key=lambda item: item[1])


def add_file(archive: zipfile.ZipFile, source: Path, archive_path: str) -> None:
    """Write one file with normalized ZIP metadata and byte-stable storage."""
    info = zipfile.ZipInfo(filename=archive_path, date_time=ZIP_EPOCH)
    info.compress_type = zipfile.ZIP_STORED
    info.create_system = 3
    info.external_attr = (0o100000 | archive_mode(source)) << 16
    archive.writestr(info, source.read_bytes())


def write_candidate(package: Path, entries) -> None:
    """Write one deterministic candidate archive to a temporary path."""
    with zipfile.ZipFile(package, 'w', compression=zipfile.ZIP_STORED, strict_timestamps=True) as archive:
        for source, archive_path in archive_files(entries):
            add_file(archive, source, archive_path)


def archive_payload(path: Path) -> dict[str, bytes]:
    """Return normalized archive file payloads while ignoring ZIP container metadata."""
    with zipfile.ZipFile(path, 'r') as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise RuntimeError(f'Release archive contains duplicate entries: {path.name}')
        return {name: archive.read(name) for name in sorted(names) if not name.endswith('/')}


def verify_published_binary(package: Path, previous: dict) -> None:
    """Require the committed published binary to match its manifest metadata."""
    if not package.is_file():
        raise RuntimeError(f'Published release binary is missing: {package.name}')
    package_bytes = package.read_bytes()
    actual_hash = hashlib.sha256(package_bytes).hexdigest()
    expected_hash = str(previous.get('sha256') or '')
    if actual_hash != expected_hash or len(package_bytes) != int(previous.get('size_bytes') or -1):
        raise RuntimeError(f'Published release binary does not match manifest integrity metadata: {package.name}')


def prepare_zip(staging: Path, slug: str, name: str, version: str, entries) -> dict:
    """Build and validate one release candidate without publishing any new bytes."""
    destination = OUT / name
    previous = PREVIOUS_BY_SLUG.get(slug)
    candidate = staging / name
    write_candidate(candidate, entries)

    if previous and str(previous.get('version')) == version:
        previous_name = str(previous.get('filename') or previous.get('file') or '')
        if previous_name != name:
            raise RuntimeError(f'Published release filename changed without a version bump for {slug}.')
        verify_published_binary(destination, previous)
        if archive_payload(destination) != archive_payload(candidate):
            raise RuntimeError(
                f'Published release content changed without a version bump for {slug} {version}. '
                'Bump the release version before rebuilding.'
            )
        candidate.unlink()
        return {'slug': slug, 'destination': destination, 'candidate': None, 'previous': previous}

    if destination.exists() and (not previous or str(previous.get('version')) != version):
        raise RuntimeError(f'Refusing to overwrite untracked release binary: {destination.name}')

    return {'slug': slug, 'destination': destination, 'candidate': candidate, 'previous': None}


def package_for(plan: dict) -> Path:
    """Return the validated bytes used to construct catalog metadata before publication."""
    return plan['candidate'] if plan['candidate'] is not None else plan['destination']


def release_row(slug, platform, kind, package, version, requirements, notes, guide_key, previous=None):
    """Return one release-manifest row, preserving original publish time for immutable releases."""
    package_bytes = package.read_bytes()
    return {
        'slug': slug,
        'platform': platform,
        'kind': kind,
        'channel': 'stable',
        'version': version,
        'released_at': previous.get('released_at') if previous else utc_now(),
        'requirements': requirements,
        'filename': package.name,
        'file': package.name,
        'size_bytes': len(package_bytes),
        'sha256': hashlib.sha256(package_bytes).hexdigest(),
        'mime_type': 'application/zip',
        'notes': notes,
        'guide_key': guide_key,
    }


def atomic_restore(path: Path, previous: bytes | None, staging: Path) -> None:
    """Restore one catalog file during a failed commit without exposing partial bytes."""
    if previous is None:
        path.unlink(missing_ok=True)
        return
    rollback = staging / f'rollback-{path.name}'
    rollback.write_bytes(previous)
    os.replace(rollback, path)


def commit_release_transaction(plans: list[dict], manifest_bytes: bytes, checksums_bytes: bytes, staging: Path) -> None:
    """Publish validated packages and catalogs together, rolling back newly-created outputs on commit failure."""
    manifest_candidate = staging / 'manifest.json'
    checksums_candidate = staging / 'SHA256SUMS.txt'
    manifest_candidate.write_bytes(manifest_bytes)
    checksums_candidate.write_bytes(checksums_bytes)

    old_manifest = MANIFEST_PATH.read_bytes() if MANIFEST_PATH.exists() else None
    old_checksums = CHECKSUMS_PATH.read_bytes() if CHECKSUMS_PATH.exists() else None
    published: list[Path] = []
    manifest_replaced = False
    checksums_replaced = False

    try:
        for plan in plans:
            candidate = plan['candidate']
            if candidate is None:
                continue
            destination = plan['destination']
            if destination.exists():
                raise RuntimeError(f'Refusing to overwrite release binary that appeared after validation: {destination.name}')
            os.replace(candidate, destination)
            published.append(destination)

        os.replace(manifest_candidate, MANIFEST_PATH)
        manifest_replaced = True
        os.replace(checksums_candidate, CHECKSUMS_PATH)
        checksums_replaced = True
    except Exception:
        for destination in reversed(published):
            destination.unlink(missing_ok=True)
        if manifest_replaced:
            atomic_restore(MANIFEST_PATH, old_manifest, staging)
        if checksums_replaced:
            atomic_restore(CHECKSUMS_PATH, old_checksums, staging)
        raise


common = [
    (Path('desktop-agent/native-agent.mjs'), 'desktop-agent/native-agent.mjs'),
    (Path('desktop-agent/PRODUCTION_AGENT.md'), 'desktop-agent/PRODUCTION_AGENT.md'),
]

with tempfile.TemporaryDirectory(prefix='workintel-release-transaction-') as temporary:
    staging = Path(temporary)
    plans = [
        prepare_zip(
            staging,
            'agent-windows-x64',
            f'WorkIntel-Agent-Windows-{agent_version}.zip',
            agent_version,
            common + [(Path('desktop-agent/installers/windows'), 'desktop-agent/installers/windows')],
        ),
        prepare_zip(
            staging,
            'agent-macos',
            f'WorkIntel-Agent-macOS-{agent_version}.zip',
            agent_version,
            common + [(Path('desktop-agent/installers/macos'), 'desktop-agent/installers/macos')],
        ),
        prepare_zip(
            staging,
            'agent-linux',
            f'WorkIntel-Agent-Linux-{agent_version}.zip',
            agent_version,
            common + [(Path('desktop-agent/installers/linux'), 'desktop-agent/installers/linux')],
        ),
        prepare_zip(
            staging,
            'browser-chrome-edge',
            f'WorkIntel-Browser-Chrome-Edge-{browser_version}.zip',
            browser_version,
            [(Path('browser-extension') / name, name) for name in ['manifest.json', 'service-worker.js', 'popup.html', 'popup.js', 'popup.css', 'README.md']],
        ),
        prepare_zip(
            staging,
            'browser-firefox',
            f'WorkIntel-Browser-Firefox-{browser_version}.zip',
            browser_version,
            [(Path('browser-extension/firefox') / name, name) for name in ['manifest.json', 'service-worker.js', 'popup.html', 'popup.js', 'popup.css']]
            + [(Path('browser-extension/README.md'), 'README.md')],
        ),
    ]

    by_slug = {plan['slug']: plan for plan in plans}
    rows = [
        release_row('agent-windows-x64', 'Windows 10/11', 'agent', package_for(by_slug['agent-windows-x64']), agent_version, 'Windows 10/11, Node.js 20+, PowerShell 5+, curl', 'Per-user Scheduled Task installer with persistent state and managed self-update supervisor.', 'windows-agent', by_slug['agent-windows-x64']['previous']),
        release_row('agent-macos', 'macOS', 'agent', package_for(by_slug['agent-macos']), agent_version, 'macOS 12+, Node.js 20+, curl', 'Per-user LaunchAgent installer with managed self-update.', 'macos-agent', by_slug['agent-macos']['previous']),
        release_row('agent-linux', 'Linux', 'agent', package_for(by_slug['agent-linux']), agent_version, 'Modern Linux desktop, Node.js 20+, curl, systemd user session', 'systemd-user installer with managed self-update.', 'linux-agent', by_slug['agent-linux']['previous']),
        release_row('browser-chrome-edge', 'Chrome / Microsoft Edge', 'extension', package_for(by_slug['browser-chrome-edge']), browser_version, 'Chromium Manifest V3', 'Domain-only browser tracker package.', 'chrome-edge-extension', by_slug['browser-chrome-edge']['previous']),
        release_row('browser-firefox', 'Mozilla Firefox', 'extension', package_for(by_slug['browser-firefox']), browser_version, 'Firefox 128+', 'Firefox Manifest V3 domain-only tracker package.', 'firefox-extension', by_slug['browser-firefox']['previous']),
    ]

    previous_rows = PREVIOUS_MANIFEST.get('releases', [])
    generated_at = PREVIOUS_MANIFEST.get('generated_at') if previous_rows == rows else utc_now()
    manifest = {'version': 2, 'generated_at': generated_at, 'releases': rows}
    manifest_bytes = (json.dumps(manifest, indent=2) + '\n').encode('utf-8')
    checksums_bytes = ''.join(f"{row['sha256']}  {row['filename']}\n" for row in rows).encode('utf-8')

    commit_release_transaction(plans, manifest_bytes, checksums_bytes, staging)

referenced = {row['filename'] for row in rows}
for package in OUT.glob('*.zip'):
    if package.name not in referenced:
        package.unlink()

preserved = sum(plan['previous'] is not None for plan in plans)
print(f'Built release catalog transaction with {preserved} immutable published package(s) preserved and {len(rows) - preserved} new package(s) published.')
