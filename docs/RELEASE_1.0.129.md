# MiCatalogo 1.0.129 — Devoluciones a crédito, anulaciones seguras y recordatorios de cobro

Publicado el 02/11/2026. Android versionCode 130, paquete com.bsolutions.micatalogo.

## Novedades y Comportamiento

1. **Devolución de ventas a crédito sin caja registradora abierta**:
   - Una venta a crédito ahora puede devolverse total o parcialmente sin exigir que la sesión de caja registradora esté abierta, reduciendo directamente el saldo deudor del cliente (`CustomerEntity.balance`) e incrementando el inventario disponible.
   - Si la venta tuvo pagos en dinero en efectivo, el flujo exige la sesión de caja para emitir el egreso correspondiente.

2. **Anulación auditada de ventas (`sale_cancel`)**:
   - Permite anular ventas activas hechas por error cuando no tienen cobros en efectivo registrados (`paidAmount == 0`).
   - Se restituye el inventario vendido de forma atómica.
   - Se anulan los asientos contables de deuda en `customer_account_entries`.
   - Disponible con diálogo de confirmación tanto en `DashboardScreen` como en `SalesHistoryScreen`.

3. **Recordatorios de deuda por WhatsApp**:
   - Acceso directo a la acción de recordar deuda desde la lista de clientes con saldo pendiente.
   - Plantilla formateada con saludo, nombre del negocio, balance adeudado y opción de envío directo vía WhatsApp o copia al portapapeles.

## Verificación Criptográfica y Despliegue

- **APK de Producción**: `https://micatalogo.bsolutions.dev/downloads/bspos-1.0.129-release.apk`
- **Tamaño**: `84,020,032` bytes. MinSdk 26. `debuggable = false`.
- **SHA-256 APK**: `f092edcd2e9bc7b34c4ef07a7944207d82ff364d0fd0a9f71afbbeb32e807dbe`
- **Certificado Oficial SHA-256**: `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f`
- **Base de Datos**: Respaldo previo generado y verificado en `storage/app/private/database-backups/20261102_212913_pre-release-1-0-129.sql` (74 tablas, 3,431 filas, 1,211,717 bytes).
- **Migración aplicada en producción**: `2026_11_02_000000_allow_multiple_customer_account_invoice_entries` (DONE en 317ms).
- **Cachés de producción**: `config`, `routes` y `views` purgadas y recompiladas.
- **Página de Descargas**: Verificada en `https://micatalogo.bsolutions.dev/descargas` respondiendo HTTP 200 con la versión 1.0.129 y enlace directo al código 130.
