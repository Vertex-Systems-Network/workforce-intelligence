# Standalone agent builder

This optional builder turns `native-agent.mjs` into a self-contained executable for the **current build platform** using esbuild + Node Single Executable Applications + postject.

## Deterministic build contract

The repository release lane uses the committed `package-lock.json` and exact Node `22.23.2`. Use the same runtime when reproducing release artifacts locally or in another controlled build environment.

```bash
cd desktop-agent/standalone
npm ci --no-audit --no-fund
npm audit --audit-level=high
node build.mjs
```

Do not replace `npm ci` with unlocked `npm install` in the release path. Dependency or Node-runtime changes require a reviewed lock/runtime update and fresh cross-platform evidence.

Output is `dist/WorkIntelAgent.exe` on Windows or `dist/WorkIntelAgent` on macOS/Linux. Build each operating system on that operating system (or in the provided GitHub Actions matrix). The normal `Desktop Agent Standalone Build` workflow remains the deterministic, unsigned PR/build certification lane.

## Trusted distribution lane

M14 adds a separate `Desktop Agent Trusted Release` workflow. It is intentionally not triggered by pull requests and uses the `production-release` GitHub environment for privileged release operations.

Trusted release invariants:

- every build is checked out from one exact source SHA;
- the source SHA must be contained by protected `main`;
- manual trusted candidates must equal the current protected-main head;
- tag publication requires `agent-v<version>` to match the version declared by `native-agent.mjs`;
- Windows requires organization-owned Authenticode certificate material plus an RFC 3161 timestamp URL, then verifies the resulting signature;
- macOS requires an organization-owned Developer ID certificate and Apple notarization credentials, then requires an accepted `notarytool` result;
- Linux remains checksum/provenance based and does not claim a platform signing service that has not been configured;
- every platform emits a machine-readable trust receipt containing the pre-trust and final SHA-256 evidence without including private signing material;
- manual dispatch uploads candidate evidence only; tag runs may publish a new GitHub Release after all platform trust jobs succeed;
- publication refuses an existing release target and never overwrites same-version assets.

The repository does not contain signing certificates, private keys or notary credentials. Missing required organization credentials fail the corresponding trusted release job closed; they must never cause an unsigned fallback to be labelled signed or notarized.

## Canonical package boundary

The M13 canonical ZIP catalog under `storage/app/releases` remains a separate immutable provenance anchor. M14 trusted standalone executables are separately named distribution outputs and do not rewrite canonical ZIP bytes, manifest rows or same-version checksums.

A source/PR merge does not itself prove that production signing, Apple notarization or real-target deployment verification has occurred. Those evidence classes remain `Not Verified` until the trusted workflow and any required production-target checks actually run successfully.