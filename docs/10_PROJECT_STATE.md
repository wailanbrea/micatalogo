# Project State

## Snapshot

- Date: 2026-10-02
- Commit: `fbfc7da` plus the advanced storefront work in the current release.
- Phase: release validation and VPS deployment.
- Local runtime: Laravel 12.69.2, PHP 8.2.33, MariaDB 11.4.12.

## Completed

- Auth, email verification, Turnstile, rate limits, roles, policies, and tenant isolation.
- Single-Seller Storefront Isolation: scoped route model binding (`Route::scopeBindings()`),
  store-isolated search, dedicated shop footer, URL tampering prevention returning 404,
  and seller acquisition landing page on `/`.
- Seller shops, categories, products, bulk upload, WebP processing, local media fallback,
  optional R2 integration, storefronts, scoped search, inventory control, WhatsApp tracking, and metrics.
- Reports, moderation queue, administrator dashboard, user management, SEO, ads, security
  headers, error pages, scheduled maintenance, logos, and QR marketing tools.
- Migration `add_discovery_enabled_to_shops_table` executed and `Shop` model configured.
- Public orders with WhatsApp order numbers, dynamic product attributes and public filters.
- CSV/XLSX inventory import with a validation preview before persistence.

## Verified

- `php artisan test`: 137 tests, 540 assertions (100% passing).
- `php .\vendor\bin\pint --test`: passing.
- `npm run build`: passing.
- R2 is not configured locally; media uses the local public fallback.

## Pending

- Configure the production R2 bucket, restricted API token, custom media domain, and health check.
- Create the initial Git commit after reviewing all untracked files.
- Run the production release checklist with the `bsolutions-infra` procedure: backup
  verification, environment validation, build, migrations, queue worker, scheduler,
  smoke tests, and rollback verification.

## Security Notes

- `.env`, Turnstile secrets, R2 credentials, and production database passwords are never committed.
- `catalog:create-owner` requires an explicit 12-character password in non-interactive mode
  and never prints it.
