# Development Closure After M12 Batch 1

Laragon/target certification is intentionally deferred. This development-only closure removes the remaining shared UX debt that can be safely completed without target PHP/Node/browser dependencies.

## Closed

- Migrated all 11 remaining tenant workspace legacy `TableWrap` surfaces to DataGrid V3.
- Live Workforce table/compact modes now use persistent, sortable DataGrid V3 columns.
- Devices & Agents uses DataGrid V3 with employee/device/connection/search/action semantics.
- Field Workforce work orders, checkpoints and incidents use DataGrid V3.
- Apps & Websites usage, classification rules, privacy exclusions and browser connections use DataGrid V3.
- Security Events and Audit Logs use DataGrid V3 with saved user views/search/sort behavior.
- Tenant feature DataGrid adoption increases from 64 to 75.
- Tenant feature `TableWrap` debt decreases from 11 to 0 and the M4 audit now rejects any regression above zero.
- M2 inline-style baseline decreases from 545 to 445 and the design-system audit now ratchets against that lower baseline.

The isolated Client Portal invoice tables remain semantic document/payment tables outside the tenant workspace feature audit; they are not part of the M4 tenant TableWrap debt.

## Deferred by request

- Laragon DB-backed PHPUnit certification.
- Installed-node TypeScript/Vite production build certification.
- Playwright browser/mobile/RTL/accessibility certification.
- Final production performance bundle evidence.
