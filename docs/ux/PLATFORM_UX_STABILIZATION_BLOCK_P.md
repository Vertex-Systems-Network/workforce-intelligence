# Platform UX Stabilization — Block P

Block P standardizes the user-facing information architecture and interaction foundations that span WorkIntel rather than adding another isolated feature surface.

## Navigation taxonomy

Workspace menus are grouped by user intent: Home, Collaboration, Work, Time & Attendance, People, Operations, Clients, Content Studio, Money, Insights, Administration, and Account. Role-specific manifests expose only groups that contain pages available to that role. Modules that expose multiple distinct pages (for example Attendance → Attendance + Leave, Activity → Activity + Apps + Live, Scheduling → Schedule + Shift Templates) keep page-specific navigation labels instead of reusing one configurable module label for every destination.

## Forms and collection views

`FormSection`, `FormGrid`, shared field controls, `DataGrid`, `TableWrap`, and `ViewModeToggle` are the supported foundations. Grid/Table-style collections display the user-facing labels Grid/List consistently, while Tasks maps the same shared presentation control to Board/List and keeps task scope (`All tasks` / `My tasks`) separate.

## Overlay behavior

Date pickers render through the shared body portal. Dropdowns, popovers, date pickers, tooltips, modal/drawer surfaces, and toast notifications use an explicit layer order. Opening a modal/drawer or starting a pointer/navigation action dismisses stale tooltips.

## Media selection and upload

Any workflow that needs an image or file should use `MediaFileField` or `MediaPicker`. A single chooser opens a modal containing `Media Library` and `Upload` modes. Multiple-file workflows can select multiple reusable library assets or upload multiple files. Media capabilities expose private-storage writability plus application/PHP request limits before upload; server-side MIME inspection and malware/quarantine rules remain authoritative.

## Studios and Chat

Website Studio adds page-quality preflight and focus mode on top of responsive design/history/zoom controls. Document Studio adds focus mode on top of versioned history, undo/redo, live preflight, zoom, reusable components, comments, approvals, sharing and signing. Chat adds conversation filters (All, Unread, Direct, Channels) while retaining its governed channel, thread, offline outbox, DLP, moderation, eDiscovery and collaboration features.

## Release gates

`tools/platform-ux-stabilization-smoke.php`, `tests/frontend/platform-ux-stabilization.test.mjs`, and `tests/Unit/PlatformUxStabilizationContractTest.php` prevent the reported regressions from returning. Both Windows verification scripts execute the new source smoke and PHPUnit contract before the full suite.
