# Project TODO

## Phase 0

- [x] Review project requirements and visual references.
- [x] Inventory the local PHP, Composer, Node, Apache, and database environment.
- [x] Identify runtime incompatibilities and coexistence risks.
- [x] Check for an existing Laravel 13 installer or reusable Laravel 13 project.
- [x] Create the mandatory project documentation baseline.
- [x] Approve Laravel 12 with the local PHP 8.2 runtime.
- [x] Provide a current local Composer executable without changing shared tooling.
- [x] Approve MariaDB 11.4 for local development.
- [x] Define the local database and least-privilege application credentials.

## Phase 1

- [x] Initialize Laravel 12.
- [x] Install Livewire 4, Tailwind 4, Vite, and Pest 3.
- [x] Configure Spanish locale, Dominican timezone, DOP, and catalog limits.
- [x] Apply the initial database migrations and verify assets, tests, and dependency audit.

## Next

- [x] Install Fortify and enable registration, password reset, and email verification.
- [x] Add active/suspended account status and block suspended authentication.
- [x] Add authentication views, a verified seller-panel route, and baseline tests.
- [x] Configure and verify server-side Turnstile for login and registration.
- [x] Add independent registration and password-reset rate limits.

## Next

- [x] Implement Phase 3 domain entities, enums, migrations, factories, and constraints.
- [x] Apply domain migrations and verify model factories, ULIDs, relationships, and indexes.
- [x] Implement Phase 4 seller/admin authorization policies, ownership scopes, and tests.
- [x] Implement Phase 5 seller shop onboarding and CRUD.
- [x] Implement Phase 6 global and internal category management.
- [x] Eliminate duplicate CTA button on home page and keep single clean CTA in header.
- [x] Implement WebP product images support in catalog grid and detail view.
- [x] Add Software global category and official BSolutions.dev shop catalog with 4 software products.

## Phase 7 — Seller Product Management

- [x] Product creation and edit forms in seller panel.
- [x] Product availability status toggle (available / unavailable / out of stock).
- [x] Price in DOP, currency validation and formatting.
- [x] Internal and global category assignment for products.
- [x] Free product quota limit enforcement per shop (100 products max).
- [x] Soft-delete and restore flows for products.
- [x] Product ownership authorization and deterministic slug collision tests.

## Phase 8 — Cloudflare R2 Storage

- [x] Configure R2 credentials and Laravel Filesystem disk for media via `league/flysystem-aws-s3-v3`.
- [x] Setup custom domain endpoint for media CDN delivery and prevent `r2.dev` in production.
- [x] Ensure private temporary storage isolation before upload (`storage/app/private/temp`).
- [x] Add storage disk connectivity check and health tests with graceful fallback for local development.

## Phase 9 — Image Processing Pipeline

- [x] Install and configure Intervention Image (`intervention/image-laravel`).
- [x] Implement image validation (MIME, max size, max pixels).
- [x] Create queued job for background image processing (`ProcessProductImageJob`).
- [x] Implement auto-orient and EXIF metadata stripping.
- [x] Generate main WebP (max 1600x1600) and thumbnail WebP (480x480).
- [x] Compute sha256 checksum and upload derivatives to R2 / media disk.
- [x] Update `ProductImage` state (`processing` -> `ready` / `failed`).
- [x] Clean up temporary files after persistent upload.
- [x] Integrate product image upload and gallery management in seller form with 3-image quota limit.

## Phase 10 & 11 — Public Storefront & Bulk Upload

- [x] Dedicated public shop storefront (`/tienda/{shop:slug}`).
- [x] Shop details header: logo, name, description, WhatsApp & Instagram actions.
- [x] Shop internal category tabs and product filtering (`?categoria={slug}`).
- [x] Product density grid with availability badges and price formatting.
- [x] Interactive product image gallery for multi-image products.
- [x] WhatsApp direct inquiry message with product name and clean link.
- [x] Bulk upload selection and preview interface (`/panel/tiendas/{shop}/subida-masiva`) with quota enforcement.

## Phase 12 — Search Service (CatalogSearchService)

- [x] Implement `CatalogSearchService` for unified search across products, shops, and categories.
- [x] Support exact, prefix, and partial matching with priority scoring (`CASE WHEN`).
- [x] Add search bar support on home and catalog views with clean controller query and URL filter synchronization.
- [x] Paginated search results with empty-state suggestions and category pills navigation.

## Phase 13 & 14 — Metrics, WhatsApp Conversion & Moderation

- [x] Daily metric event recording (`ShopDailyMetric` and `ProductDailyMetric` atomic increment).
- [x] Safe WhatsApp click tracking redirect (`/r/wa/tienda/{shop:slug}` and `/r/wa/tienda/{shop:slug}/producto/{product:slug}`).
- [x] Public reporting form with Turnstile protection (`Report` model) for shops and products.
- [x] Independent rate limiting on public reports (5 reports per 15 minutes per IP).

## Phase 15 — Admin Moderation Queue & System Overview

- [x] Admin dashboard metrics overview (active/suspended shops, products count, total storage estimation).
- [x] Admin moderation queue view (`/admin/reportes`): list reports, filter by status, review target, resolve report.
- [x] Seller suspension and product suspension actions with audit trail and toggle shortcuts.
- [x] Comprehensive test coverage in `tests/Feature/AdminModerationTest.php`.

## Phase 16 — Production Hardening & VPS Readiness

- [x] Implement discrete `AdSlot` Blade component (`resources/views/components/ad-slot.blade.php`) respecting `ADS_ENABLED=false`.
- [x] Complete OpenGraph, Twitter card, Canonical, and dynamic Robots meta tags in layout.
- [x] Dynamic XML Sitemap generator (`/sitemap.xml`) with active categories, shops, and products.
- [x] Updated `robots.txt` disallowing panel, admin, auth, search queries, and WhatsApp tracking redirects.
- [x] HTTP security headers middleware (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `HSTS`).
- [x] Styled custom error views matching branding: 403 Forbidden, 404 Not Found, 500 Internal Error, 503 Maintenance.
- [x] Scheduled daily maintenance tasks in `routes/console.php` (temporary upload pruning and 30-day soft-deleted cleanup).
- [x] Full test coverage in `tests/Feature/ProductionHardeningTest.php` (81 passing tests total).

