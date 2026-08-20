# Localization & Navigation V2

Localization & Navigation V2 stabilizes WorkIntel language switching, RTL rendering and role-aware navigation without mutating menu definitions at runtime.

## Core locales

The fully parity-checked interface catalogs are English (`en`), Turkish (`tr`), Russian (`ru`), Urdu (`ur`) and Arabic (`ar`). English remains the fallback for compatibility locales that do not yet have a complete dedicated catalog. Frontend and backend core packs are checked for exact key parity during release verification.

## User and workspace language behavior

Workspace owners choose a default language. A user can follow that workspace default or persist a personal override. Browser persistence is keyed by user ID so one signed-in account cannot inherit another account's language from the same browser. Changing language updates `html[lang]`, `html[dir]` and `body[dir]` without refreshing the authenticated session.

## Immutable navigation

`resources/js/navigation.manifest.json` stores stable navigation IDs and translation keys only. The Sidebar resolves translated labels at render time and uses stable group/item IDs as React keys. Language switching therefore cannot append translated copies of existing navigation entries.

The release test simulates repeated locale resolution across English, Turkish, Arabic, Urdu and Russian for twenty cycles and verifies that navigation IDs and counts never change.

## Information architecture

Owner/Admin navigation is grouped into Home, Work, People, Operations, Clients, Money, Insights, Administration and Account. Other roles receive smaller role-appropriate group sets from the same immutable manifest.

Scheduling has one sidebar destination. Schedule Board and Shift Templates now live inside `SchedulingHub`; the historical `shifts` page ID remains only for backward compatibility and is not exposed as a second menu item.

## Shared UI localization

Shared WorkIntel controls translate registered labels at render time, including page headers, cards, buttons, fields, segmented controls, tabs, badges, dialogs, drawers, alerts, file controls, selects, empty states, refresh feedback and DataGrid controls. Dynamic business data is left unchanged.

Status, role and work-mode labels are resolved through the typed localization catalog and the legacy page-copy bridge where required. DEV-08 removed the unused `humanLabels.tsx` prototype after the import graph confirmed that no runtime surface consumed it.

## RTL

Urdu and Arabic activate RTL at the document root. Shared overlays, selects, dropdowns, DataGrid sorting/pagination, date-picker controls, sidebars, forms and scheduling controls use logical CSS or explicit RTL rules. Page scrollbars use the same design tokens as the sidebar scrollbar.

## Verification

Dependency-free source gate:

```bat
php tools\localization-navigation-v2-smoke.php
```

Targeted PHPUnit contract:

```bat
php artisan test --filter=LocalizationNavigationV2ContractTest
```

Both `verify-release.cmd` and `verify-clean-install.cmd` run these gates before a release can pass.
