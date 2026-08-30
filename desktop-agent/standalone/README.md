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

Output is `dist/WorkIntelAgent.exe` on Windows or `dist/WorkIntelAgent` on macOS/Linux. Build each operating system on that operating system (or in the provided GitHub Actions matrix). The release workflow verifies that the expected artifact exists, is non-empty, and records its SHA-256 digest before upload.

The output is not production-signed by this repository. Apply Authenticode signing on Windows and Developer ID signing/notarization on macOS with organization-owned certificates/secrets in your release pipeline.
