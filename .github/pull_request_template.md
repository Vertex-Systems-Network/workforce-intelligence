## Summary

Describe the focused change and the reason it is needed.

## Safety / impact

- Database impact:
- Runtime / deployment impact:
- Security / permissions impact:
- Backward-compatibility impact:

## Validation

- [ ] Relevant source/unit/feature tests pass.
- [ ] WorkIntel CI job `test` is green.
- [ ] WorkIntel Windows Certification job `windows-certification` is green when required.
- [ ] Migration/seed behavior is safe and repeatable when database code changed.
- [ ] Browser/accessibility certification remains green when UI behavior changed.
- [ ] No live-database destructive verifier was used.

## Remaining acceptance gates

List any external, physical, credentialed, or environment-specific proof that hosted CI cannot truthfully provide. Write `None` when fully closed.

## Scope check

- [ ] No unrelated files or features are included.
- [ ] Documentation/acceptance criteria were updated when the contract changed.
