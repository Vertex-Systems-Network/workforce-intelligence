# Commerce V2 — Seller Platform and Workspace Client Payments

Commerce V2 separates two financial relationships that must never share credentials or administrative boundaries.

## 1. WorkIntel Seller Platform

`/seller` is the global platform-operator surface. It is intentionally outside the tenant sidebar and remains protected by the backend `platform.operator` middleware. Seller operators can review platform customers, subscriptions, revenue, WorkIntel subscription payment providers, coupons, tax rules, refunds and dunning.

The plan editor now exposes a capability matrix. Boolean features and numeric limits are stored in `plan_entitlements`. Routine `PlanCatalog::sync()` creates missing catalog data but does not overwrite seller-edited pricing or entitlement values. Passing `sync(true)` is an explicit restore-to-product-default operation.

## 2. Workspace Client Payments

`Client Payments` is a tenant feature under the Clients area. These gateways belong to the workspace and are used only when that workspace invoices its own clients. They are not WorkIntel platform subscription credentials.

Supported workspace gateway adapters:

- Manual payment
- Bank transfer
- Stripe hosted Checkout
- PayPal Orders checkout
- Custom hosted HTTPS checkout

Gateway credentials use Laravel's encrypted array cast and are hidden from API serialization. First-time remote configuration uses a save → connection test → enable lifecycle: credentials/settings are persisted first, the provider is tested, and activation/default status is granted only after a successful test. A failed test keeps the provider disabled without discarding the saved configuration. Custom outbound checkout/status URLs must pass the public-HTTPS commerce URL guard.

## Client Portal Pay Now

A sent, partial or overdue invoice with a positive outstanding amount can expose only the gateways enabled by the workspace and allowed by the invoice. Hosted checkout redirects return to `/portal/{workspace-slug}` with an internal checkout ID; the authenticated portal reconciles the provider state before showing payment as completed.

Manual and bank-transfer checkouts expose instructions but cannot be self-settled by the client. An authorized workspace user verifies the payment and records the reference. Provider transaction references are protected against reuse across different invoices.

## Recurring invoices

Workspace users with `client_invoices.recurring_manage` can define weekly, monthly, quarterly or yearly invoice schedules. Schedules reuse `ClientInvoiceService`, can auto-send generated invoices, and can restrict the Pay Now gateways available on generated invoices.

Scheduled commands:

- `workintel:generate-client-invoices`
- `workintel:reconcile-client-payments`

Deployment doctor:

- `php artisan workintel:commerce-v2-doctor`

## Security boundaries

- Seller APIs require global platform-operator authorization.
- Client-commerce APIs require workspace context, permissions and plan entitlements.
- Client portal payment APIs require the client portal token and the `feature.client_payments` entitlement.
- A portal account can only access invoices/checkouts belonging to its own client record.
- Workspace gateway credentials never appear in client or workspace API payloads.
- Platform provider credentials and workspace client-payment credentials are stored in different tables and models.
