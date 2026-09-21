# Runner Benchmark Backlog

`registry.json` is the schema-v3 committed task-definition registry. Exact-head runtime PASS/FAIL does **not** live in the candidate source tree.

`result-envelope.schema.json` defines the machine-readable exact-head terminal result envelope. Store terminal envelopes on immutable non-source evidence surfaces so evidence recording cannot change and invalidate the candidate SHA.

- Validate task definitions: `npm run audit:runner-benchmarks`
- Validate a result envelope: `npm run validate:runner-result -- <result.json>`

Runner registration never grants execution authority. Immediate work without current authority is blocked. Do not defer cheap source verification into Runner Benchmark.
