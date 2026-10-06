# Estado actual de MiCatalogo

Corte: 2026-10-05. Este archivo describe el estado vigente; el historial queda en Git.

## Entregas y publicación

- Web/API: Laravel 12 y PHP 8.2; repositorio micatalogo.
- Android: repositorio micatalogowebApp, paquete com.bsolutions.micatalogo, Room 21.
- Última versión pública comprobada: 1.0.23 (24), mínimo compatible 23.
- Nueva versión local: 1.0.24 (25), importador adaptativo implementado y APK de release preparada.
- Esta tarea no modifica el VPS ni el manifiesto público. No hay migraciones backend,
  seeders, borrados ni alteraciones de usuarios/tiendas/productos existentes en producción.
- Release financiera anterior: FINANCIAL_ALIGNMENT_RELEASE_1.0.23.md.

## Capacidades vigentes

- Auth/verificación/Turnstile, roles, permisos independientes, planes y límites Web/API/Android.
- Tiendas múltiples según plan, miembros/vendedores y catálogo estrictamente aislado por tienda.
- Vitrina, pedidos WhatsApp, categorías, atributos, búsqueda, imágenes y métricas/QR.
- Ventas contado/crédito/mayoristas, snapshots y conflictos de precios, inventario FIFO,
  devoluciones, cuentas por cobrar/abonos y caja local POS separada de caja remota.
- P&L y flujo de efectivo separados; impuestos informativos, costos masked nullable,
  descuentos, devoluciones, cobertura de costos, aging y comisiones.
- Gastos con pago parcial y abonos; ACK de gastos/cobros conserva saldos y allocations.
- UUID estable y colas financieras Room; ACK antes de SENT, dependencias con ventas.
- Caja remota exige conexión y no cierra con operaciones pendientes de la tienda.
- Updater conserva mínimo real y bloqueo persistente cuando comenzó la instalación.

## Importador adaptativo terminado en código local

- XLSX/XLS/CSV/TXT; hoja real y encabezados detectados hasta fila 50, con selección manual.
- NFKC/BOM/aliases español-inglés y clasificación EAN/UPC frente a SKU.
- Confianza, razones, encabezados originales, ejemplos y columnas ignoradas revisables.
- Totales de inventario no se usan como precio/costo unitario; atributos desconocidos solo opt-in.
- Dinero decimal exacto y stock entero sin redondeo; códigos string y ceros recuperables.
- Archivo temporal privado por usuario/tienda, dos horas, remapping sin volver a subir.
- Confirmación server-side por sesión, lock de tienda y sesión, idempotencia y cupo solo de altas.
- Revalidación de colisiones EAN bajo lock antes del cupo, incluso con previews obsoletos.
- Android ya no reenvía las filas; muestra el mapping/diagnóstico y sincroniza catálogo al confirmar.
- Límites de carga/descompresión/celdas y lectura de fórmulas cacheadas sin ejecución.
- PhpSpreadsheet 5.10.0; Composer audit sin vulnerabilidades conocidas.
- Arquitectura, límites y reproducción: INVENTORY_IMPORT_ARCHITECTURE.md.

## Verificación de esta entrega

- Backend: 343 pruebas / 1745 aserciones, SQLite en memoria.
- Importador: 23 pruebas adaptativas + 16 regresiones existentes.
- Android: 70 pruebas unitarias, incluida compatibilidad y confirmación sin filas.
- Android: 96 pruebas instrumentadas pasando en la suite completa final.
- E2E en emulador: XLSX fila 8 → Retrofit → Laravel real aislado → mapping Compose
  → confirmación por sesión → replay sin duplicación.
- Benchmark de 1500 productos: aproximadamente 0.37–1.21 s según carga simultánea,
  4 consultas; delta de memoria PHP retenida 2 MB y pico de suite 100–112 MB.
  No es una medición aislada del RSS del proceso ni un benchmark de confirmación.
- npm run build y formateo PHP verificados.
- Teléfono físico no detectado; no se afirma instalación ni prueba en él.
- Firma, hash y matriz QA completos: INVENTORY_IMPORT_RELEASE_1.0.24.md.

## Límites y trabajo externo

La publicación de 1.0.24 es independiente de completar esta implementación local.
No admite ODS/PDF/imágenes/Word ni reconstruye dígitos que Excel ya perdió.
R2 es opcional y no está configurado localmente; el fallback de imágenes permanece.
El caché temporal requiere un driver persistente entre peticiones en la instalación destino.
