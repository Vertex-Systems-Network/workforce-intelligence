# Standalone agent builder

This optional builder turns `native-agent.mjs` into a self-contained executable for the **current build platform** using esbuild + Node Single Executable Applications + postject.

```bash
cd desktop-agent/standalone
npm ci
node build.mjs
```

Output is `dist/WorkIntelAgent.exe` on Windows or `dist/WorkIntelAgent` on macOS/Linux. Build each operating system on that operating system (or in the provided GitHub Actions matrix).

The output is not production-signed by this repository. Apply Authenticode signing on Windows and Developer ID signing/notarization on macOS with organization-owned certificates/secrets in your release pipeline.
