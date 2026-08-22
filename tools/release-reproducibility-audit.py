#!/usr/bin/env python3
"""Prove release ZIP hashes are stable when source mtimes change."""

from pathlib import Path
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def package_hashes(release_dir: Path) -> dict[str, str]:
    """Return SHA-256 values for every generated release ZIP."""
    return {
        package.name: hashlib.sha256(package.read_bytes()).hexdigest()
        for package in sorted(release_dir.glob('*.zip'))
    }


def run_builder(work_root: Path) -> None:
    """Run the copied release builder in an isolated repository fixture."""
    subprocess.run(
        [sys.executable, str(work_root / 'tools/build-releases.py')],
        cwd=work_root,
        check=True,
    )


def validate_catalog(work_root: Path, expected_hashes: dict[str, str]) -> None:
    """Verify manifest and checksum catalog agree with generated ZIP bytes."""
    release_dir = work_root / 'storage/app/releases'
    manifest = json.loads((release_dir / 'manifest.json').read_text(encoding='utf-8'))
    manifest_hashes = {row['filename']: row['sha256'] for row in manifest['releases']}
    if manifest_hashes != expected_hashes:
        raise SystemExit(f'Manifest hashes do not match package bytes: {manifest_hashes} != {expected_hashes}')

    checksum_hashes = {}
    for line in (release_dir / 'SHA256SUMS.txt').read_text(encoding='utf-8').splitlines():
        checksum, filename = line.split('  ', 1)
        checksum_hashes[filename] = checksum
    if checksum_hashes != expected_hashes:
        raise SystemExit(f'SHA256SUMS does not match package bytes: {checksum_hashes} != {expected_hashes}')


with tempfile.TemporaryDirectory(prefix='workintel-release-audit-') as temporary:
    work_root = Path(temporary) / 'repo'
    (work_root / 'tools').mkdir(parents=True)
    shutil.copytree(ROOT / 'desktop-agent', work_root / 'desktop-agent')
    shutil.copytree(ROOT / 'browser-extension', work_root / 'browser-extension')
    shutil.copy2(ROOT / 'tools/build-releases.py', work_root / 'tools/build-releases.py')

    run_builder(work_root)
    release_dir = work_root / 'storage/app/releases'
    first_hashes = package_hashes(release_dir)
    if not first_hashes:
        raise SystemExit('Release builder produced no ZIP packages.')
    validate_catalog(work_root, first_hashes)

    stamp = 2_000_000_000
    for source_root in [work_root / 'desktop-agent', work_root / 'browser-extension']:
        for source in sorted(source_root.rglob('*')):
            if source.is_file():
                os.utime(source, (stamp, stamp))
                stamp += 1

    run_builder(work_root)
    second_hashes = package_hashes(release_dir)
    validate_catalog(work_root, second_hashes)

    if second_hashes != first_hashes:
        changed = {
            filename: {'first': first_hashes.get(filename), 'second': second_hashes.get(filename)}
            for filename in sorted(set(first_hashes) | set(second_hashes))
            if first_hashes.get(filename) != second_hashes.get(filename)
        }
        raise SystemExit(f'Release ZIP hashes changed after mtime-only mutations: {changed}')

print(f'Release reproducibility audit passed for {len(first_hashes)} package(s).')
