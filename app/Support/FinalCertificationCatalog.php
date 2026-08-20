<?php

namespace App\Support;

/** Defines final M12 release budgets shared by runtime doctors and source contracts. */
final class FinalCertificationCatalog
{
    public const ROUTES_MIN = 700;
    public const ROUTES_MAX = 760;
    public const SCHEDULED_WORKINTEL_MIN = 30;
    public const SCHEDULED_WORKINTEL_MAX = 40;

    public const REQUIRED_RELEASE_FILES = [
        'docs/release/M12_CERTIFICATION_BUDGETS.json',
        'tools/performance-budget-audit.mjs',
        'tools/m12-final-certification-audit.mjs',
        'tests/e2e/accessibility-platform.spec.mjs',
        'tests/e2e/authenticated-platform.spec.mjs',
        'verify-release.cmd',
        'verify-clean-install.cmd',
    ];
}
