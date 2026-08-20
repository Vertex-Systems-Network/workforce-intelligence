# DataGrid V2

DataGrid V2 is the shared WorkIntel table foundation for searchable, sortable, filterable and paginated operational datasets. It replaces page-specific table behavior with one controlled React table contract while preserving specialized semantic tables such as weekly schedules, financial pivots and document canvases.

## Frontend engine

The shared `DataGrid` component uses the stable TanStack React Table v8 adapter. It supports client-side datasets and controlled server-side datasets through the same column definition model.

Core behavior:

- global search
- column text/select/date-range filters
- ascending and descending sorting
- pagination and page-size selection
- column visibility
- row selection and bulk actions
- named saved views
- refresh feedback
- loading skeletons
- normal and filtered empty states
- responsive mobile cards
- RTL-safe pagination and action placement
- workspace/user persistence through `UserPagePreference`

## Saved views

A grid with `persistKey="clients.directory"` persists under a normalized user page preference key such as `grid.clients.directory`. Search, sorting, filters, column visibility, page size and saved named views are scoped by both user and workspace.

## Client-side mode

Use the default mode when the endpoint intentionally returns a bounded dataset. TanStack performs filtering, sorting and pagination in the browser.

## Server-side mode

Set `server`, `totalRows` and `onQueryChange` for large datasets. `dataGridQueryParams()` serializes the controlled state to the WorkIntel list endpoint contract:

- `page`
- `per_page`
- `search`
- `sort=name,-created_at`
- `filters[status]=active`
- `filters[created_at][from]=2026-08-01`
- `filters[created_at][to]=2026-08-31`

Backend endpoints should parse those values through `App\Support\DataGridRequest`. The helper accepts explicit sortable/filterable identifiers and applies sorting only through a server-owned UI-to-database column map. User-provided column names must never be passed directly to SQL ordering.

## Initial migrations

The first Block C migration wave covers the highest-use operational list screens:

- People
- Clients
- Projects
- Tasks

Specialized pivot/schedule tables remain on purpose until their interaction model is redesigned rather than forcing them into a generic grid.

## Row actions

Row action menus use the shared portal/floating `Dropdown` from UI Foundation V3. The menu is not contained by the table scroll box, so short tables do not clip action menus or create an artificial vertical scrollbar.

## Release gates

Block C must pass:

- `php tools/data-grid-v2-smoke.php`
- `php artisan test --filter=DataGridRequestTest`
- frontend `npm test`
- TypeScript typecheck and production build when dependencies are installed
- full PHPUnit in the normal release verification pipeline
