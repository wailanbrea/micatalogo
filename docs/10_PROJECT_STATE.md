# Project State

## Snapshot

- Date: 2026-09-14
- Commit: initial commit pending; remote `origin` is empty.
- Phase: deployment preflight.
- Local runtime: Laravel 12.69.2, PHP 8.2.33, MariaDB 11.4.12.

## Completed

- Auth, email verification, Turnstile, rate limits, roles, policies, and tenant isolation.
- Seller shops, categories, products, bulk upload, WebP processing, local media fallback,
  optional R2 integration, storefronts, search, shipping filters, WhatsApp tracking, and metrics.
- Reports, moderation queue, administrator dashboard, user management, SEO, ads, security
  headers, error pages, scheduled maintenance, logos, and QR marketing tools.
- Demo BSolutions.dev software catalog and homepage media are present.
- Product image selection only renders ready images and avoids eager-load N+1 queries.

## Verified

- `php artisan test`: 108 tests, 417 assertions.
- `php .\vendor\bin\pint --test`: passing.
- `npm run build`: passing.
- R2 is not configured locally; media uses the local public fallback.

## Pending

- Configure the production R2 bucket, restricted API token, custom media domain, and health check.
- Create the initial Git commit after reviewing all untracked files.
- Perform VPS deployment only with explicit authorization and the `bsolutions-infra` procedure.
- Run the production release checklist: backup verification, environment validation, build,
  migrations, queue worker, scheduler, smoke tests, and rollback verification.

## Security Notes

- `.env`, Turnstile secrets, R2 credentials, and production database passwords are never committed.
- `catalog:create-owner` requires an explicit 12-character password in non-interactive mode
  and never prints it.
