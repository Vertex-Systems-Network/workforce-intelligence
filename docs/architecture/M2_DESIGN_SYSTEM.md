# M2 — WorkIntel Design System V1

## Decision

`resources/js/design-system` is the **single authoritative UI implementation surface** for the application. Feature modules consume WorkIntel components instead of creating parallel controls or raw interactive/form/media markup. The public Website renderer remains the explicit semantic-output exception because it renders end-user website content rather than the authenticated application UI.

M2 deliberately does **not** add an unverified second visual library. The current React/TypeScript foundation, Lucide React, TanStack Table and existing certified WorkIntel primitives remain the runtime baseline. React Aria or another headless accessibility layer may later be adopted *inside* individual WorkIntel primitives after target-environment dependency and browser certification; it must never become a second competing design system.

## Source rules

1. Feature/page code may not render raw `button`, `input`, `select`, `option`, `textarea`, `table`, `img`, `form`, `label`, `a` or `progress` elements. These elements are owned by the WorkIntel Design System.
2. `WebsiteRenderer.tsx` is the explicit exception for generated public website semantics.
3. `lucide-react` is the only interface icon library. Hand-written SVG action icons and competing icon packages are blocked.
4. Layout uses `Box`, `Stack`, `Inline`, `Grid`, form composition primitives and semantic `--wi-*` tokens before page-local styles.
5. Module-specific dynamic styles may remain only under the inline-style ratchet; they may decrease but never increase.
6. Generic `.ui-*` state rules belong in `design-system/toolkit.css`. Page CSS may scope a WorkIntel primitive to a module root but may not create top-level `.ui-*` overrides.
7. New visual primitives require keyboard focus, hover/active where relevant, disabled/error/loading states, RTL, reduced-motion and responsive behavior before feature use.

## Authoritative V1 primitives

### Layout and structure
`Page`, `PageHeader`, `Box`, `Stack`, `Inline`, `Grid`, `Card`, `CardHeader`, `CardBody`, `CardFooter`, `Divider`.

### Typography and links
`Text`, `Link`, `Kbd`.

### Form composition and controls
`Form`, `FormSection`, `FormGrid`, `FormActions`, `Field`, `Label`, `Input`, `SearchInput`, `Textarea`, `Select`, `Option`, `Checkbox`, `Radio`, `ChoiceInput`, `Switch`, `HiddenFileInput`.

### Actions and navigation
`Button`, `IconButton`, `Pressable`, `Segmented`, `Tabs`, `ViewModeToggle`, `Dropdown`, `Popover`, `Tooltip`.

### Overlays
`Modal`, `Drawer`.

### Data, status and media
`DataGrid`, `TableWrap`, `Badge`, `Avatar`, `Image`, `Progress`, `ProgressBar`, `StatCard`, `Alert`, `EmptyState`.

## Batch 1 foundation

- Removed the parallel legacy `resources/js/ui` source and promoted the proven toolkit into `resources/js/design-system`.
- Migrated application imports to the authoritative path.
- Removed raw feature-level `button/input/select/textarea/table/img` markup.
- Locked Lucide-only icons and no-growth inline-style audit.
- Added semantic WorkIntel design tokens and low-level choice/media/progress primitives.

## Batch 2 maturity work

- Added shared `Box`, `Grid`, expanded `Stack`/`Inline`, and `Text` composition contracts.
- Migrated **685** static layout/typography style nodes into WorkIntel composition primitives.
- Added the shared semantic `Form` root and migrated **99** feature forms.
- Added `Option`, `Label` and `Link` and migrated **587 options**, **26 labels**, and **5 application links**.
- Feature source now contains **zero raw interactive/form/media/link elements** covered by the M2 policy.
- Reduced the feature inline-style ratchet from **1,308 to 545** objects (**58.3% reduction**). Remaining styles are concentrated in module-specific/dynamic views and are assigned to M3–M10 migrations instead of being mechanically rewritten.
- Added shared field error/required states, explicit disabled input states, loading-button behavior and keyboard focus for pressables/links.
- Added automated visual-state markers for focus, hover, disabled, invalid, loading, overlays, RTL, reduced motion and mobile adaptation.
- Locked page CSS to the four existing module-specific stylesheets and blocks any new unregistered page CSS or unscoped top-level `.ui-*` override.

## M2 acceptance status

### Code-side acceptance
- One authoritative design-system source: **complete**.
- Raw interactive/form/media/link markup migration: **complete**.
- Lucide-only icon policy: **complete**.
- Composition primitives: **complete for the shared foundation**.
- Inline style reduction/no-growth ratchet: **complete**; module-specific remainder is explicitly deferred to module conversion phases.
- Generic CSS ownership and page-CSS isolation: **complete**.
- Source-level visual-state contract: **complete**.

### Target-runtime closure still required
M2 is not marked 100% until the target Laragon environment returns clean `npm run typecheck`, `npm run build`, and browser certification for the migrated component surface. Those gates require installed Node dependencies/system browser support unavailable in the packaging sandbox.
