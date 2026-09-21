# Runner Benchmark Backlog

`registry.json` is the schema-v2 authorization-aware source of truth. `docs/release/RUNNER_BENCHMARK_REGISTER.md` defines workflow semantics.

Safe non-blocking work defaults to the final batch. Security, exact-head merge, migration/auth/secrets/data-safety, current-change integration-safety, and incident/recovery tasks are immediate when required by the active milestone.

Immediate classification never grants authority. Without current authority, the task is blocked.

Do not defer cheap source verification into the Runner Benchmark.
