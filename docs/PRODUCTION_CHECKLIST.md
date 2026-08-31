# WorkIntel production checklist

Before traffic:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] strong `APP_KEY`
- [ ] HTTPS `APP_URL`
- [ ] database backup completed
- [ ] `php artisan workintel:migration-doctor` reviewed
- [ ] `php artisan migrate --force` completes
- [ ] `npm run typecheck` and `npm run build` complete
- [ ] `php artisan test` passes
- [ ] `php artisan optimize` completes
- [ ] scheduler runs once per minute
- [ ] queue worker is supervised
- [ ] `/health/live` returns 200
- [ ] `/health/ready` returns 200
- [ ] SMTP/provider settings configured where needed
- [ ] screenshot/report storage selected for deployment topology
- [ ] webhook private-network access remains disabled unless intentionally required
- [ ] manual SaaS invoice settlement remains disabled unless this is an internal deployment
- [ ] desktop/browser canonical release checksums verified
- [ ] trusted standalone artifact source SHA matches the intended release revision
- [ ] Windows standalone distribution has a verified Authenticode signature and RFC 3161 timestamp when Windows enterprise distribution is in scope
- [ ] macOS standalone distribution has a verified Developer ID signature and an accepted Apple notarization result when macOS enterprise distribution is in scope
- [ ] Linux standalone distribution has final SHA-256/provenance evidence and is not described as platform-signed unless such signing actually occurred
- [ ] every trusted standalone artifact has a machine-readable M14 receipt whose final digest matches the distributed bytes
- [ ] M13 canonical ZIP bytes/version immutability remains unchanged by trusted standalone distribution
- [ ] backup and rollback procedure tested
- [ ] backup-to-restore evidence exists before recovery is described as `restore verified`

## Release-state truthfulness

Do not collapse these evidence states:

- `BUILT` — deterministic source build completed;
- `HASH_VERIFIED` — final artifact checksum/provenance verified;
- `SIGNED` — platform signature actually applied and verified;
- `NOTARIZED` — Apple notary service actually returned an accepted result for the macOS distribution;
- `RELEASED` — the intended immutable distribution assets were actually published;
- `PRODUCTION_VERIFIED` — the exact released revision/artifact was actually verified on the stated real target.

Hosted CI or a merged PR can prove source/build contracts but cannot, by itself, prove signing credentials existed, Apple notarization ran, a release was published, or a production target was healthy. Missing evidence remains `Not Verified`.

## Block I certification

- [ ] `php artisan workintel:production-doctor --json --require-build` passes
- [ ] `npm run test:e2e:public` passes on the deployment host or release candidate
- [ ] `npm run test:e2e:full` passes on a disposable seeded certification database
- [ ] dropdowns remain open/anchored during nested page scroll
- [ ] table row-action menus are not clipped by table overflow
- [ ] desktop, tablet and mobile browser projects have no viewport-wide overflow
- [ ] repeated EN/TR/AR/UR/RU switching does not duplicate navigation
- [ ] Arabic RTL browser journey passes
- [ ] Seller Platform remains separate from tenant navigation
- [ ] final release archive was re-extracted and dependency-free audits were rerun
