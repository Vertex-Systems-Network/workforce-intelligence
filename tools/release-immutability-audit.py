#!/usr/bin/env python3
"""Prove published release bytes cannot drift or partially publish on failed validation."""

from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def copy_fixture(destination: Path) -> Path:
    """Copy only the release-builder inputs and published catalog into an isolated repo."""
    work_root = destination / 'repo'
    (work_root / 'tools').mkdir(parents=True)
    (work_root / 'storage/app').mkdir(parents=True)
    shutil.copytree(ROOT / 'desktop-agent', work_root / 'desktop-agent')
    shutil.copytree(ROOT / 'browser-extension', work_root / 'browser-extension')
    shutil.copytree(ROOT / 'storage/app/releases', work_root / 'storage/app/releases')
    shutil.copy2(ROOT / 'tools/build-releases.py', work_root / 'tools/build-releases.py')
    return work_root


def release_snapshot(work_root: Path) -> dict[str, bytes]:
    """Return byte-for-byte state for the complete published release directory."""
    release_dir = work_root / 'storage/app/releases'
    return {
        path.relative_to(release_dir).as_posix(): path.read_bytes()
        for path in sorted(release_dir.rglob('*'))
        if path.is_file()
    }


def run_builder(work_root: Path, expect_success: bool) -> subprocess.CompletedProcess[str]:
    """Run the copied release builder and enforce the expected process outcome."""
    result = subprocess.run(
        [sys.executable, str(work_root / 'tools/build-releases.py')],
        cwd=work_root,
        text=True,
        capture_output=True,
    )
    if expect_success and result.returncode != 0:
        raise SystemExit(f'Release builder unexpectedly failed:\n{result.stdout}\n{result.stderr}')
    if not expect_success and result.returncode == 0:
        raise SystemExit('Release builder unexpectedly accepted an invalid publication attempt.')
    return result


def assert_unchanged_rebuild() -> None:
    """An unchanged same-version rebuild must preserve every published byte and catalog timestamp."""
    with tempfile.TemporaryDirectory(prefix='workintel-release-immutable-noop-') as temporary:
        work_root = copy_fixture(Path(temporary))
        before = release_snapshot(work_root)
        run_builder(work_root, expect_success=True)
        after = release_snapshot(work_root)
        if after != before:
            changed = sorted(set(before) | set(after))
            changed = [name for name in changed if before.get(name) != after.get(name)]
            raise SystemExit(f'Unchanged rebuild mutated published release state: {changed}')


def assert_same_version_mutation_rejected(relative_source: str) -> None:
    """Changing one packaged source without a version bump must fail before published state changes."""
    with tempfile.TemporaryDirectory(prefix='workintel-release-immutable-drift-') as temporary:
        work_root = copy_fixture(Path(temporary))
        before = release_snapshot(work_root)
        source = work_root / relative_source
        source.write_bytes(source.read_bytes() + b'\nM13_IMMUTABILITY_AUDIT_MUTATION\n')
        result = run_builder(work_root, expect_success=False)
        output = f'{result.stdout}\n{result.stderr}'
        if 'changed without a version bump' not in output:
            raise SystemExit(f'Builder failed for the wrong reason after mutating {relative_source}:\n{output}')
        after = release_snapshot(work_root)
        if after != before:
            changed = sorted(set(before) | set(after))
            changed = [name for name in changed if before.get(name) != after.get(name)]
            raise SystemExit(f'Failed same-version build mutated published state for {relative_source}: {changed}')


def assert_manifest_integrity_is_authoritative() -> None:
    """A committed release binary that no longer matches its manifest must never be silently repaired."""
    with tempfile.TemporaryDirectory(prefix='workintel-release-immutable-corrupt-') as temporary:
        work_root = copy_fixture(Path(temporary))
        package = work_root / 'storage/app/releases/WorkIntel-Agent-Windows-1.2.1.zip'
        package.write_bytes(package.read_bytes() + b'corruption')
        before = release_snapshot(work_root)
        result = run_builder(work_root, expect_success=False)
        output = f'{result.stdout}\n{result.stderr}'
        if 'does not match manifest integrity metadata' not in output:
            raise SystemExit(f'Corrupted published artifact failed for the wrong reason:\n{output}')
        if release_snapshot(work_root) != before:
            raise SystemExit('Builder modified release state while rejecting a corrupted published artifact.')


def assert_mixed_version_validation_is_transactional() -> None:
    """A valid new agent version must not publish if a later same-version browser package fails validation."""
    with tempfile.TemporaryDirectory(prefix='workintel-release-transactional-mixed-') as temporary:
        work_root = copy_fixture(Path(temporary))
        before = release_snapshot(work_root)

        agent = work_root / 'desktop-agent/native-agent.mjs'
        source = agent.read_text(encoding='utf-8')
        match = re.search(r"const VERSION = ['\"](\d+)\.(\d+)\.(\d+)['\"]", source)
        if not match:
            raise SystemExit('Could not find semantic agent version for transactional release audit.')
        current = match.group(0)
        next_version = f'{match.group(1)}.{match.group(2)}.{int(match.group(3)) + 1}'
        replacement = current.replace(match.group(1) + '.' + match.group(2) + '.' + match.group(3), next_version)
        agent.write_text(source.replace(current, replacement, 1), encoding='utf-8')

        browser = work_root / 'browser-extension/popup.css'
        browser.write_bytes(browser.read_bytes() + b'\nM13_TRANSACTIONAL_BROWSER_DRIFT\n')

        result = run_builder(work_root, expect_success=False)
        output = f'{result.stdout}\n{result.stderr}'
        if 'changed without a version bump' not in output:
            raise SystemExit(f'Mixed-version transaction failed for the wrong reason:\n{output}')

        after = release_snapshot(work_root)
        if after != before:
            changed = sorted(set(before) | set(after))
            changed = [name for name in changed if before.get(name) != after.get(name)]
            raise SystemExit(f'Failed mixed-version validation partially published release state: {changed}')

        unexpected = sorted((work_root / 'storage/app/releases').glob(f'WorkIntel-Agent-*-{next_version}.zip'))
        if unexpected:
            raise SystemExit(f'Failed mixed-version validation left new agent artifacts behind: {[path.name for path in unexpected]}')


assert_unchanged_rebuild()
for packaged_source in [
    'desktop-agent/PRODUCTION_AGENT.md',
    'browser-extension/popup.css',
    'browser-extension/firefox/popup.css',
]:
    assert_same_version_mutation_rejected(packaged_source)
assert_manifest_integrity_is_authoritative()
assert_mixed_version_validation_is_transactional()

print('Release immutability audit passed: no-op rebuild preserved bytes; drift/corruption were rejected; mixed-version validation remained transactional.')
