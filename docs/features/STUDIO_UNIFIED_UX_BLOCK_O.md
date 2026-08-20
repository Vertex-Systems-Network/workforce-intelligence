# Block O — Studio & Unified UX

Block O raises the Website Studio, Document Studio and Media Library while consolidating file selection and collection presentation on shared UI contracts.

## Website Studio

Website Studio keeps its existing versioned publishing, public forms, reusable sections, SEO, localization and domain model, but adds editor-grade controls rather than creating a second website subsystem.

- bounded undo/redo history for section-schema editing
- desktop/tablet/mobile preview modes with preview zoom
- searchable section toolbox
- per-section background, text color, vertical spacing, content width and alignment
- section anchor IDs and desktop/tablet/mobile visibility controls
- body/heading font and base-size theme controls
- section presentation is shared by Studio preview and public rendering
- Media Library picker includes direct upload, so image and OpenGraph selection do not require leaving the editor

## Document Studio

Document Studio V4 remains the governed document engine. Block O adds:

- bounded editor undo/redo
- Cmd/Ctrl+Z, Shift+Cmd/Ctrl+Z, Cmd/Ctrl+Y and Cmd/Ctrl+S workflows
- live preflight for missing media, data sources, formulas, reusable components and duplicate block IDs
- preview zoom controls
- history-aware block editing, drag/reorder, duplication, deletion, page settings and reusable component insertion
- existing Media Library image workflow now includes upload inside the picker

The preflight is advisory; server-side document validation and permission checks remain authoritative.

## Media Library / DAM

Media Library now acts as the reusable asset source for the wider product.

- progress-aware XHR multipart uploads
- up to 20 files per Media Library request
- per-file success/failure reporting so one bad file does not discard successful uploads
- storage-health and PHP/application upload-limit diagnostics
- drag/drop uploader and direct picker upload
- server-detected MIME storage metadata
- storage-directory creation before file writes
- existing security inspection, MIME validation, executable rejection, malware/quarantine contract and checksum dedupe remain active
- folders, tags, metadata, alt text, captions, usage counts, trash/restore/purge and table/grid views remain available

### Upload troubleshooting

`GET /api/v1/media/capabilities` reports non-secret application/PHP limits and whether private local media storage is writable. Oversized API requests are returned as JSON `413` instead of an HTML error page. The fresh-package runtime guard also recreates required Laravel storage directories before normal requests.

For Laragon, confirm `upload_max_filesize` and `post_max_size` are at least as large as `WORKINTEL_MEDIA_MAX_FILE_MB`, then restart the PHP/web service after changing php.ini.

## Shared file source chooser

`MediaFileField` is the common workflow control for selecting a reusable Media Library asset or uploading from the device. It is wired into:

- Platform logo/favicon and CSV import
- Settings logo/favicon
- Task attachments
- Expense receipts
- HRIS employee documents
- Chat attachments

Profile photo keeps its specialized cropper but already exposes both Upload and Media Library choices. Media Library itself retains its direct DAM uploader.

For workflows whose legacy endpoint requires a browser `File`, a chosen Media Library asset is securely downloaded through the authorized media endpoint and materialized as a `File`; this avoids creating parallel attachment APIs.

## Unified collection presentation

- `TableWrap` is the shared semantic table surface.
- `DataGrid` is the shared advanced table contract for search/filter/sort/pagination/column visibility/selection.
- `ViewModeToggle` is the shared grid/table switcher used by People, Projects and Media Library.
- page-level raw `<table>` markup is blocked by the Block O source smoke.
- shared table headers, row states, focus treatment, borders, toolbar/footer shells and responsive collection controls are styled in the UI toolkit.

## Release gates

The release and clean-install scripts execute `php tools/studio-unified-ux-smoke.php`. Frontend contracts live in `tests/frontend/studio-unified-ux.test.mjs` and guard the Studio, media-source and common collection contracts.
