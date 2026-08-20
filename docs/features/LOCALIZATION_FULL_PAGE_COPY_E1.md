# Localization Full Page Copy E.1

Block E.1 finishes the high-visibility legacy page-copy layer after Localization & Navigation V2 established stable navigation, RTL and shared UI catalogs.

## Architecture

New code continues to use typed localization keys from `catalog.ts`. Legacy deep-module pages are localized by `LegacyLocalizationBridge`, which translates only deterministic registered English UI copy. The bridge remembers the original English source in weak maps so repeated locale switching can render TR, RU, UR, AR and back to EN without accumulating translated DOM or duplicating navigation state.

The bridge never writes form values. It skips business-data and rich-content boundaries marked with `data-business-value="true"`, `data-no-auto-i18n="true"`, contenteditable regions, `pre`, `code`, `script` and `style`.

## Copy coverage

The page-copy translator combines short domain terms, sentence templates and three exact deep-page registries. The E.1 audit covers major static UI copy across Activity, Apps & Websites, Attendance, Automations, Billing, Chat, Clients, Enterprise, Field Workforce, Finance, HRIS, Insights, Live Workforce, Marketing, Organization, Payroll, Reports, Screenshots, Tasks, Timesheets, authentication, settings, storage, Attendance 2.0, dashboard customization and enterprise chat controls.

Technical examples such as API paths, search operators, hashes, commands, sample domains and placeholders remain literal by design. Runtime business data such as employee, client, project and uploaded-file names is not translated unless it exactly matches a registered product label.

The static JSX audit used for this release measured 96.2% deterministic coverage of product-copy occurrences after excluding intentional technical literals. Residual candidates are retained in the validation report rather than being blindly translated.

## Certification

`tools/localization-page-copy-e1-smoke.php` and `LocalizationPageCopyE1ContractTest` protect bridge mounting, source preservation, business-data safety, deep-module copy and technical-literal boundaries. Existing Localization & Navigation V2 tests continue to exercise 20 repeated locale-switch cycles and stable navigation IDs.
