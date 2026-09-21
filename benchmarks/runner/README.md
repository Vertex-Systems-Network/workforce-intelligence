# Runner Benchmark Backlog

This directory is the canonical machine-readable backlog for expensive verification that should be accumulated during AI-native implementation and executed in the final certification phase.

- `registry.json` is the source of truth for benchmark entries and status.
- `docs/release/RUNNER_BENCHMARK_REGISTER.md` defines the workflow and field semantics.
- `npm run audit:runner-benchmarks` validates the register cheaply during normal development.

Do not use this backlog to postpone ordinary local/source verification. Unit tests, typecheck, source audits, targeted checks, and other inexpensive evidence still run during implementation.

When implementation discovers a new verification requirement that genuinely needs a GitHub-hosted runner, Windows-only environment, installed system-browser matrix, external public target, or similarly expensive real-target certification, append or update a benchmark entry in the same checkpoint and mark the item `Not Verified — deferred to final runner batch`.

The final batch is one release phase, not an instruction to run incompatible jobs concurrently. Respect dependencies and workflow ordering while draining all required entries against the exact settled head.
