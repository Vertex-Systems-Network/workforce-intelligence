# Website & Portal Builder — Block H

Block H gives eligible WorkIntel workspaces a versioned public website without introducing a second media, permission, localization or billing subsystem.

## Architecture

A workspace owns one `website_sites` record. Pages are localized records with immutable `website_page_versions`; saving creates a revision while publishing selects a specific current revision for public delivery. Draft edits therefore do not overwrite the live website.

Website Studio uses dnd-kit for ordered page sections. GridStack remains reserved for dashboard-style resizable grids. The editor and public site share `WebsiteRenderer`, so section behavior does not drift between preview and production.

## Page and section catalog

Page types include Home, About, Contact, Services, Portfolio, Buy, Sell, Careers, Blog and Custom. Supported sections include hero, rich text, media, gallery, feature/stat/service/team/portfolio/testimonial/pricing grids, FAQ, lead form, CTA, columns, divider, spacer and custom content.

## Media and forms

Website media references existing Media Library asset IDs. Publishing registers media usage and makes only referenced public assets publicly readable. The persisted page schema never stores private storage paths or temporary editor URLs.

Lead forms are reusable and workspace scoped. Declared fields are validated server-side, submissions are rate limited, IP/user-agent fingerprints are salted hashes, and the submission payload is encrypted at rest. Website leads use the shared server DataGrid contract in the authenticated studio.

## Public delivery and SEO

Published sites are available at `/site/{workspace-slug}` and can be assigned to a verified workspace domain with purpose `website`. Laravel resolves public website context before React boots and emits server-visible title, description, canonical and OpenGraph metadata for published pages. The React app then renders the full responsive site and updates client-side metadata during navigation.

## Localization and RTL

Pages carry their own language. Public output sets `lang`/`dir`, uses RTL for Urdu and Arabic, and filters navigation to the active page language. Website Studio itself participates in the WorkIntel localization/navigation catalog.

## Safety boundaries

- page versions are immutable;
- preview forms are disabled and cannot create leads;
- editor preview links cannot navigate;
- published rich text is sanitized;
- arbitrary script/data protocols are rejected;
- custom domains must already be verified/active;
- Website Studio is module, plan and permission gated;
- automation/webhook events are emitted for page publication and new public leads.
