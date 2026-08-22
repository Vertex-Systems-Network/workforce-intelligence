#!/usr/bin/env python3
"""Rebuild deterministic deployment release ZIPs and the release catalog."""

from datetime import datetime, timezone
from pathlib import Path
import hashlib
import json
import re
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'storage/app/releases'
OUT.mkdir(parents=True, exist_ok=True)
ZIP_EPOCH = (1980, 1, 1, 0, 0, 0)

agent_source = (ROOT / 'desktop-agent/native-agent.mjs').read_text(encoding='utf-8')
agent_match = re.search(r"const VERSION = ['\"]([^'\"]+)['\"]", agent_source)
if not agent_match:
    raise RuntimeError('Could not determine native agent VERSION.')

agent_version = agent_match.group(1)
browser_version = '1.0.0'

for package in OUT.glob('*.zip'):
    package.unlink()


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


def build_zip(name, entries):
    """Build one deterministic release archive independent of source mtimes."""
    package = OUT / name
    with zipfile.ZipFile(package, 'w', compression=zipfile.ZIP_STORED, strict_timestamps=True) as archive:
        for source, archive_path in archive_files(entries):
            add_file(archive, source, archive_path)
    return package


common = [
    (Path('desktop-agent/native-agent.mjs'), 'desktop-agent/native-agent.mjs'),
    (Path('desktop-agent/PRODUCTION_AGENT.md'), 'desktop-agent/PRODUCTION_AGENT.md'),
]

windows = build_zip(
    f'WorkIntel-Agent-Windows-{agent_version}.zip',
    common + [(Path('desktop-agent/installers/windows'), 'desktop-agent/installers/windows')],
)
macos = build_zip(
    f'WorkIntel-Agent-macOS-{agent_version}.zip',
    common + [(Path('desktop-agent/installers/macos'), 'desktop-agent/installers/macos')],
)
linux = build_zip(
    f'WorkIntel-Agent-Linux-{agent_version}.zip',
    common + [(Path('desktop-agent/installers/linux'), 'desktop-agent/installers/linux')],
)
chrome = build_zip(
    f'WorkIntel-Browser-Chrome-Edge-{browser_version}.zip',
    [(Path('browser-extension') / name, name) for name in ['manifest.json', 'service-worker.js', 'popup.html', 'popup.js', 'popup.css', 'README.md']],
)
firefox = build_zip(
    f'WorkIntel-Browser-Firefox-{browser_version}.zip',
    [(Path('browser-extension/firefox') / name, name) for name in ['manifest.json', 'service-worker.js', 'popup.html', 'popup.js', 'popup.css']]
    + [(Path('browser-extension/README.md'), 'README.md')],
)


def release_row(slug, platform, kind, package, version, requirements, notes, guide_key):
    """Return one release-manifest row from the final package bytes."""
    package_bytes = package.read_bytes()
    return {
        'slug': slug,
        'platform': platform,
        'kind': kind,
        'channel': 'stable',
        'version': version,
        'released_at': datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z'),
        'requirements': requirements,
        'filename': package.name,
        'file': package.name,
        'size_bytes': len(package_bytes),
        'sha256': hashlib.sha256(package_bytes).hexdigest(),
        'mime_type': 'application/zip',
        'notes': notes,
        'guide_key': guide_key,
    }


rows = [
    release_row('agent-windows-x64', 'Windows 10/11', 'agent', windows, agent_version, 'Windows 10/11, Node.js 20+, PowerShell 5+, curl', 'Per-user Scheduled Task installer with persistent state and managed self-update supervisor.', 'windows-agent'),
    release_row('agent-macos', 'macOS', 'agent', macos, agent_version, 'macOS 12+, Node.js 20+, curl', 'Per-user LaunchAgent installer with managed self-update.', 'macos-agent'),
    release_row('agent-linux', 'Linux', 'agent', linux, agent_version, 'Modern Linux desktop, Node.js 20+, curl, systemd user session', 'systemd-user installer with managed self-update.', 'linux-agent'),
    release_row('browser-chrome-edge', 'Chrome / Microsoft Edge', 'extension', chrome, browser_version, 'Chromium Manifest V3', 'Domain-only browser tracker package.', 'chrome-edge-extension'),
    release_row('browser-firefox', 'Mozilla Firefox', 'extension', firefox, browser_version, 'Firefox 128+', 'Firefox Manifest V3 domain-only tracker package.', 'firefox-extension'),
]

(OUT / 'manifest.json').write_text(
    json.dumps(
        {
            'version': 2,
            'generated_at': datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z'),
            'releases': rows,
        },
        indent=2,
    ) + '\n',
    encoding='utf-8',
)
(OUT / 'SHA256SUMS.txt').write_text(
    ''.join(f"{row['sha256']}  {row['filename']}\n" for row in rows),
    encoding='utf-8',
)
print(f'Built {len(rows)} deterministic release packages in {OUT}')
