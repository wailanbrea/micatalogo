# MiCatalogo 1.0.128 — anulación auditada de abonos

Publicado el 30/10/2026. Android versionCode 129, paquete com.bsolutions.micatalogo.

## Comportamiento

- Propietario o encargado activo con permiso de Cobros puede anular un abono de
  cliente completo, con motivo obligatorio de hasta 255 caracteres y conexión.
- Se conserva el asiento y los importes originales. Un asiento compensatorio
  único enlaza motivo, usuario, fecha y abono. Se restituyen deuda y asignaciones
  de facturas, sin tocar ventas ni inventario. Reintentos conservan identidad;
  otra solicitud no puede anular el mismo abono dos veces.
- Los pagos de facturas anulados se marcan y dejan de participar en sumas,
  antigüedad y cobranzas. Los reportes rectifican el período original del cobro.
- Caja recibe una contrapartida en su sesión original, incluso si estaba cerrada.
  No se sobrescribe el arqueo guardado; el resumen expone valores originales y
  diferencia recalculada. Un cierre diario existente recibe un ajuste auditado.
- La restauración de deuda admite cliente inactivo y límite actual inferior.
  Si una devolución o anulación posterior impide restituir exactamente el importe
  a las facturas, se rechaza con 409 y se requiere revisar el reembolso; ninguna
  parte de la operación se aplica.
- Web: Clientes > Historial y anulación de abonos. También enlazado desde Crédito
  para quienes tienen permiso de Cobros. Historial paginado con recibo y auditoría.
- Android: Cobros > historial > recibo > Anular abono. Motivo y confirmación,
  estado Anulado y totales que excluyen abonos anulados. Los abonos locales sin
  confirmación requieren sincronización antes de acceder a las acciones remotas.
- Android consulta también recibos de otros dispositivos. Comprueba los IDs de
  todos sus abonos confirmados, incluidos los antiguos, para aplicar anulaciones
  de la web una sola vez a ventas, caja y saldos locales, preservando los recibos.

## Contrato y persistencia

- GET /api/v1/shops/{shop}/customer-payments, cursor before_id y filtro opcional
  payment_ids[] (máximo 50). Requiere customers:read y acceso a Cobros.
- POST /api/v1/shops/{shop}/customers/{customer}/payments/{paymentId}/reverse:
  client_transaction_uuid y reason. Requiere customers:write, Cobros y rol
  propietario/encargado. Devuelve recibo actualizado y cliente. Reintento idéntico
  devuelve resultado; UUID conflictivo o anulación duplicada responde 409.
- Migración aditiva e idempotente: reverses_entry_id único, payment_method y
  reversed_at en customer_account_entries; reversed_at en invoice_payments.
  Se ejecutó solo esta migración por ruta. Sin seeders, purgas o restablecimientos.
- Android conserva esquema Room 27 y los datos existentes. La confirmación local
  REVERSED de payment_sync_outbox guarda el motivo; correcciones y marcador se
  aplican en una transacción. No hay limpieza de datos para actualizar.
- No revertir a código que ignore anulaciones una vez utilizadas. Conservar los
  campos y el historial de auditoría; la migración impide eliminar esa evidencia.

## Verificación

- Backend completo: 728 pruebas, 8,065 verificaciones, sin fallos. Tras el último
  ajuste de visibilidad del enlace: otras 38 pruebas, 453 verificaciones, sin fallos.
- Android: 169 pruebas unitarias sin fallos; 22 pruebas en emulador sin fallos,
  incluidas conciliación local, duplicados, aislamiento por tienda, caja cerrada,
  rollback por inconsistencia, motivo obligatorio, controles ocupados y recibo anulado.
- Capturas de confirmación y recibo revisadas visualmente. Emulador con aplicación
  debug aislada com.bsolutions.micatalogo.offlinecheck. Sin instalación ni borrado
  de datos de un teléfono físico.
- Se usó un emulador 37.2.13 aislado y verificado mediante checksum oficial porque
  el SDK existente carecía de componentes gráficos. No se reemplazó el SDK original.
- Servidor: 17 archivos de backend/vistas verificados por SHA-256; compilación Vite,
  cachés de rutas/vistas/configuración actualizadas y assets anteriores conservados.
- Descarga pública verificada en navegador: 1.0.128, notas y enlace al código 129.
  APK pública descargada y verificada otra vez con apksigner y metadatos. La APK
  anterior continúa respondiendo HTTP 200.

## Publicación y protección de datos

- APK: https://micatalogo.bsolutions.dev/downloads/bspos-1.0.128-release.apk
- Tamaño: 84,003,648 bytes. MinSdk 26. No debuggable.
- SHA-256 APK: 455a220e06030065e9d7656f221417885b8d83e85511e1609756fe08bd8f5c04
- Certificado conservado: 5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f
- minimum_supported_version_code permanece en 23.
- SQL privado previo: 74 tablas, 1,072,554 bytes, SHA-256
  b877ec74d3abcf900d37d8a3c66ff902f5ab22218452b45135df96a1404f1944.
  Archivo: storage/app/private/database-backups/20261030_165336_pre-payment-reversal-1-0-128.sql.
- Copia local privada verificada, además de respaldos de los archivos desplegados,
  manifiesto anterior y assets. Estado anterior privado SHA-256:
  7c8596a2d1b8b26c9a5c860bd2975bb61e29f191fa8124015f2adb098c8809f2.
- Identificadores y conteos previos/posteriores conservados: 14 usuarios, 12 tiendas,
  576 productos y 1 pedido. Se respetaron cambios existentes del servidor mediante
  comparación de archivos base y respaldo; no se sobrescribieron cambios ajenos.
- Ningún abono real fue anulado durante las pruebas. Los casos financieros se
  ejecutaron únicamente sobre SQLite de pruebas o Room en memoria.
