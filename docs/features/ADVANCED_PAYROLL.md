# Phase 21 — Advanced Payroll & Compliance Packs

Phase 21 extends the existing payroll engine without replacing the approved-payroll snapshot model.

## Compliance packs

Compliance packs are workspace-owned, effective-dated rule sets. A pack can contain percentage, fixed or bracket rules for:

- tax
- statutory deductions
- ordinary deductions
- employee benefits
- allowances
- employer contributions

Rules can be conditioned by worker classification, residency and pay type. The payroll item stores the calculated line and a rule snapshot so changing a future rule cannot silently rewrite an older calculated/approved payroll run.

> Compliance packs are a configurable calculation framework. They are not represented as certified or jurisdiction-authoritative statutory tax software. A customer must configure or integrate verified local rules before relying on a pack for statutory filing.

## Effective assignment

Members can be assigned an active compliance pack with effective dates. Overlapping active assignments are rejected. A payroll run can also explicitly snapshot its compliance pack.

## Benefits and allowances

Member benefits support payroll, monthly, annual and one-time frequencies. Monthly/annual/one-time benefits are deduplicated against prior payroll compliance lines so they are not charged repeatedly just because another payroll run exists in the same period.

## Contractor and special runs

The payroll run supports regular, contractor, off-cycle and termination-style member-scoped runs. Contractor withholding configured on a contractor payment profile becomes an actual compliance line rather than configuration-only metadata.

## Retro pay and termination

Retro adjustments record the source period, amount, reason and application state. Termination settlement preview/approval records a configurable estimate. The generic service-year calculation is deliberately not described as a jurisdiction statutory entitlement.

## Exports

Payroll exports are private files generated only for calculated/review/approved/paid runs. Existing authorization/entitlement checks remain in front of the export controller.

## Doctor

```bash
php artisan workintel:payroll-compliance-doctor
```

Checks all Phase 21 schema landmarks and payroll item compliance-total columns.
