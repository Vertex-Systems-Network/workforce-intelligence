# Data Lifecycle + Media Library

Block D adds a recoverable data lifecycle and reusable workspace Media Library without changing immutable financial, payroll, payment or audit-ledger rules.

## Archive versus Trash

Archive remains a business status. Clients and projects can still be archived without deleting their records. Trash is a separate recoverable lifecycle used only when dependency rules permit removal from normal queries.

Supported Trash Center resources are clients, projects, tasks, media assets and media folders. Restore re-validates parent relationships and plan limits. Permanent deletion requires the stronger `trash.purge` permission and is rejected while protected dependencies remain.

Client records with projects, invoices, payments, portal accounts or reports cannot be trashed. Projects with tasks, tracked time or expenses cannot be trashed. Tasks with tracked time or subtasks cannot be trashed. Media assets with active usages cannot be trashed or purged.

Payroll ledgers, billing transactions, audit/security records and other immutable business evidence never enter Trash Center.

## Media Library

Media Library stores files privately by default and exposes physical storage paths only inside the server. It provides folders and nested folders, tags, search, media categories, list/grid modes, metadata, usage counts, private downloads, recoverable Trash and permanent purge.

Uploads calculate SHA-256 checksums and reuse an existing active workspace asset when the same binary content is uploaded again. Executable/high-risk extensions are rejected by the media service.

## Media usage

`media_usages` records links between an asset and the domain object using it. In-use media cannot be trashed. Block D initially uses this mechanism for user profile photos and exposes it for Document Studio, Website Studio and other future media consumers.

## Profile photos

My Account supports upload + local canvas crop or choosing an existing authorized Media Library image. Selected avatar assets are made public only through an unguessable UUID media route; the underlying disk/path remains private. Removing the avatar releases its usage and returns an otherwise-unused asset to private visibility.

## Loading and empty states

The app now has reusable table, board, media-library, profile and form skeletons. Lazy routes choose a destination-shaped skeleton rather than displaying one generic page layout for every module.

## Verification

- Source smoke: `php tools/data-lifecycle-media-smoke.php`
- Unit contract: `php artisan test --filter=DataLifecycleMediaContractTest`
- Feature flow: `php artisan test --filter=DataLifecycleMediaFlowTest`
- Runtime doctor: `php artisan workintel:block-d-doctor`
- Existing DB release check: `verify-release.cmd`
- Disposable zero install: `verify-clean-install.cmd`
