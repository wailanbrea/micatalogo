# Project State

## Snapshot

- Date: 2026-10-05
- Phase: Fase Financiera 1 completada en código local para Web/API/Android; pendientes la publicación de los últimos cambios Web/API y APK.
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
- Cierre definitivo y blindaje de la Fase Financiera 1:
  - Manejador central determinista de dinero `App\Support\Money` (cero float en lógica contable y persistencia).
  - Estado de Resultados (P&L) auditado: ventas brutas, deducción de ITBIS de ingresos netos, descuentos en línea y globales, devoluciones con restitución de costo FIFO, ganancia bruta, gastos operativos devengados en el período, comisiones y ganancia operativa.
  - Cobertura de costos ponderada (`revenue_cost_coverage`) y semáforo de margen contra reglas objetivo (`ProductPriceRule`).
  - Flujo de Efectivo real: reconciliación estricta de cobros de facturas, abonos a créditos, pagos a gastos y movimientos de caja sin doble contabilización.
  - Amortización FIFO de cobros de clientes a facturas impagas (`PaymentService::recordCustomerDebtPayment`), vinculando `invoice_payments.customer_account_entry_id` y actualizando automáticamente el estado a `paid` o `partial`.
  - Reconciliación matemática perfecta entre cartera de clientes y facturas ($\text{total\_receivable} = \text{invoices\_total} + \text{unallocated\_receivables}$, $\text{reconciliation\_difference} = 0$).
  - Abonos parciales a gastos (`ExpensePayment`) con seguimiento de saldos pendientes y estado `paid`/`partial`/`pending`.
  - Arqueo de caja (`CashRegisterSession`) aislado por usuario con índice único en BD e idempotencia estricta contra arqueos concurrentes dispares (409 Conflict).
  - Aging de cuentas por cobrar clasificado factura por factura utilizando `due_date` e idempotencia con hash SHA-256 en endpoints críticos.
  - Autorización protegida: panel de negocio protegido por `can:viewFinance,shop` (vendedores ordinarios 403), y gastos protegidos por `menu:expenses` y plan Pro.
- Android `1.0.15` (version code `16`; release local firmada, aún no publicada):
  - Los abonos sincronizan método de pago y referencia; transferencia ya no se registra por omisión como efectivo.
  - Caja financiera en línea conectada a la API: consulta, apertura, movimientos manuales, arqueo y cierre. UUID estable por reintento; no reemplaza la caja local offline del POS.
  - Gastos Android incluyen UUID de idempotencia y lo conservan al reintentar la misma operación.
  - La API entrega facturas pendientes de la tienda autorizada con cliente, fecha de referencia, días, tramo y saldo abierto; Finanzas los presenta junto con las cuatro cifras de conciliación. El desglose se oculta a vendedores sin permiso financiero.
  - La versión `1.0.15` quedó instalada in-place en el Galaxy conectado. No se publicó APK ni se modificó VPS/manifiesto en esta tarea.

## Verified

- `php vendor/bin/pest`: 310 tests, 1,515 assertions (100% passing, ejecutados con SQLite en memoria).
- Tests de integridad financiera dedicados:
  - `CustomerPaymentAllocationTest` (6/6 passing)
  - `FinancialIdempotencyHardenedTest` (6/6 passing)
  - `FinancialIntegrityTest` (17/17 passing)
  - `FinancialModuleTest` (6/6 passing)
  - `MoneyTest` (8/8 passing)
  - `ApiPosSaleTest` (10/10 passing)
  - `SellerCommissionTest` (5/5 passing)
- Android `gradlew testDebugUnitTest`: passing, incluida la cobertura de método/referencia de cobros e idempotencia de gastos.
- `npm run build`: passing.
- Production migrations, cache rebuild, backup comparison, and smoke test: passing.
- Production mobile inventory import routes deployed and route cache rebuilt.
- Public Android endpoint checked on 2026-10-05: version `1.0.14`, code `15`; local release `1.0.15`, code `16`, signed with the existing certificate and verified non-debuggable.
- `php -l` en `BusinessDashboardService.php` y `FinanceReportController.php`: sin errores.
- On-device smoke: Android `1.0.15` actualizado in-place (first-install timestamp intacto) y abierto en Inicio sin crash. Finanzas/Caja no se navegaron: otra app tomó el primer plano durante el intento; no se registró ninguna operación financiera real.
- R2 is not configured locally; media uses the local public fallback.

## Pending

- Publicar el cambio local de detalle de Aging de la API y Android `1.0.15` cuando se solicite; no se modificó VPS ni manifiesto en esta tarea.
- Completar navegación manual en el teléfono por Finanzas/Caja cuando esté disponible sin otra app en primer plano; no crear gastos, movimientos ni sesiones de caja en producción durante ese smoke test.
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
## Alineación financiera Web/API/Android — 2026-10-05

- Contrato oficial y fixtures: docs/API_CONTRACT.md y docs/api-contracts, replicados y probados por serialización en Android.
- Caja conserva aliases web y Android <=1.0.22; nombres canónicos plurales, deuda, egresos, conteo y cierre esperado reales. Campos monetarios críticos no inventan ceros.
- Impuestos informativos fuera de utilidad; descuentos, devoluciones y cobertura por ingresos. Costos y utilidades masked permanecen nullable.
- Gastos permiten pago inicial parcial y abonos; ACK refresca saldo. Cobros conservan allocations, saldo autoritativo y efecto en caja.
- Permisos finance/cash/expenses independientes en API y Android. Finance requiere delegación explícita para vendedores; null heredado no concede acceso nuevo.
- Android Room 21: recibos nullable en payment_sync_outbox y operation_outbox; migración no destructiva. Payload/UUID estable, estados PENDING/SENT/BLOCKED y orden existente respecto a ventas.
- Apertura/cierre remoto requieren conexión; cierre espera colas de la tienda vacías. Caja local POS permanece separada y explícita, no representa una sesión del servidor.
- Updater conserva el mínimo real: opcional antes de iniciar; obligatorio por mínimo o proceso ya iniciado persistente. Mínimo publicado se conserva en 23.
- Pruebas backend: 319 casos / 1589 assertions en SQLite :memory:. Matriz nueva: caja 5000+10000+2000-1500-500=15000; gasto 10000/pagado4000 y abono3000; deuda3000/cobro1500/FIFO/saldo1500; permisos e idempotencia. Wholesale continúa validando snapshot y price_conflict.
- No hay migraciones backend ni cambios de datos de producción en esta entrega.
