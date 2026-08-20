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
- [ ] desktop/browser release checksums verified
- [ ] native executables/packages signed/notarized before enterprise distribution
- [ ] backup and rollback procedure tested

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
