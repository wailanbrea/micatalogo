# Project State

## Snapshot

- Date: 2026-10-04
- Commit: `2da3505` plus the advanced storefront and plan-limit work in the current working tree.
- Phase: plan-limit and account-plan release deployed and production smoke-verified; Pro supports 3 active shops.
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
- Commercial plans with product, image, user, seller, feature, and additional-seat limits.
- Administrative shop members, seller/user quota enforcement, and Android quota presentation.
- Android inventory import flow with CSV/XLSX preview, quota validation, confirmation, and catalog refresh.
- Mobile inventory import API endpoints:
  - `POST /api/v1/shops/{shop}/inventory-import/preview`
  - `POST /api/v1/shops/{shop}/inventory-import`
  - `POST /api/v1/shops/{shop}/inventory-import/{session}/confirm` (dedicated session confirmation)
- Fortalecimiento e integridad transaccional de importación de inventario:
  - Parser numérico y monetario sin float, compatible con formatos dominicanos y latinos (`2500,50`, `RD$ 2,500.50`).
  - Detección de duplicados internos (en el archivo) y existentes (en la tienda) sin cruce multi-tenant.
  - Sesiones server-side seguras (`InventoryImportSession`) con expiración de 2 horas e idempotencia.
  - Estrategias explícitas (`skip`, `update`, `create`) con protección contra colisiones ilegales de código de barras.
  - Consumo de cuotas de plan exclusivo para productos nuevos (`create`), sin cobrar updates/skips, asegurado con `Shop::lockForUpdate()`.
  - Manejo controlado de categorías faltantes y preservación de SKU/barcode con ceros iniciales.
- Cierre y blindaje de la Fase Financiera 1:
  - Manejador central determinista de dinero `App\Support\Money` (cero float en lógica contable y persistencia).
  - Estado de Resultados (P&L) auditado: ventas brutas, deducción de ITBIS de ingresos netos, descuentos en línea y globales, devoluciones con restitución de costo FIFO, ganancia bruta, gastos operativos devengados en el período, comisiones y ganancia operativa.
  - Cobertura de costos ponderada (`revenue_cost_coverage`) y semáforo de margen contra reglas objetivo (`ProductPriceRule`).
  - Flujo de Efectivo real: reconciliación estricta de cobros de facturas, abonos a créditos, pagos a gastos y movimientos de caja.
  - Abonos parciales a gastos (`ExpensePayment`) con seguimiento de saldos pendientes y estado `paid`/`partial`/`pending`.
  - Arqueo de caja (`CashRegisterSession`) aislado por usuario con índice único en BD para prevenir aperturas concurrentes.
  - Aging de cuentas por cobrar clasificado factura por factura utilizando `due_date` e idempotencia con hash SHA-256 en endpoints críticos.
- Android `1.0.13` release with version code `14` published and verified; minimum supported version remains `11`.

## Verified

- `php vendor/bin/pest`: 297 tests, 1,434 assertions (100% passing).
- Tests de integridad financiera dedicados: `FinancialIntegrityTest` (17/17 passing), `MoneyTest` (8/8 passing).
- Android `gradlew testDebugUnitTest`: passing.
- `npm run build`: passing.
- Production migrations, cache rebuild, backup comparison, and smoke test: passing.
- Production mobile inventory import routes deployed and route cache rebuilt.
- Production Android manifest and download verified against the signed `1.0.13` artifact.
- R2 is not configured locally; media uses the local public fallback.

## Pending

- Configure the production R2 bucket, restricted API token, custom media domain, and health check.
- Confirm the production queue worker and scheduler remain healthy after the release.

## Latest Production Release

- Target: `bsolutions-vps`, `C:\\xampp\\htdocs\\micatalogo`.
- Backup: `C:\\xampp\\backups\\micatalogo\\2026-10-04-mobile-auth-import`.
- Backup SHA-256: `aca71b47528d93cd74cd5faa486c0772ebd2263ad2712172bc3e89ceb19f8e20`.
- Before/after identifiers matched for users, shops, products, orders, and invoices.
- Pending migrations applied without destructive operations; all migrations are now marked `Ran`.

## Security Notes

- `.env`, Turnstile secrets, R2 credentials, and production database passwords are never committed.
- `catalog:create-owner` requires an explicit 12-character password in non-interactive mode
  and never prints it.
