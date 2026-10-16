# QA integrado Web ↔ Android — 2026-10-16

## Alcance y aislamiento

- Ramas verificadas: `work/navigation-closure-20261015` en Web/API y Android.
- Web/API HEAD al comenzar: `23099b5406e7a2950d292365473abf594d32e42b`.
- Android HEAD al comenzar: `03d18b46ddfb41428e55e4f6e8dcf40e16083128`.
- Laravel QA: `APP_ENV=testing`, driver `sqlite`, loopback únicamente. API en `127.0.0.1:18767`; navegador Web en `127.0.0.1:18769`.
- Ambos servidores usan exactamente `C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-sync-20261012.sqlite`. `/__qa/identity` respondió con el entorno, driver y archivo esperados antes de crear fixtures y antes de la prueba Web.
- Emulador: `emulator-5554`; paquete de pruebas `com.bsolutions.micatalogo.offlinecheck.syncqa`, Android `1.0.117-offlinecheck` / `versionCode 118`, firma debug aislada. No se instaló ni publicó release.
- Los escenarios crearon registros sintéticos con prefijo/identidad QA. No se tocaron VPS, producción, cuentas comerciales ni datos reales. Los fixtures se conservan en el archivo QA; no hay endpoint de purga y no se borró nada.

## Ejecuciones verificadas

| Verificación | Resultado real | Evidencia |
| --- | --- | --- |
| Compilación Android QA + `testDebugUnitTest` | PASS; 152 tests, 0 failures, 0 errors, 0 skipped | Salida Gradle y 45 XML en `app/build/test-results/testDebugUnitTest` |
| Android live, jornadas existentes seleccionadas | PASS; 7/7 ejecutadas, 0 failures, 0 skips, 0 assumptions | `EmulatorJourneyTest` (4), `PosSaleLiveIntegrationTest` (2), `InventoryImportLiveIntegrationTest` (1); `QA_SUMMARY` del runner |
| Venta Web seguida de lectura Android sobre el mismo tenant | PASS; 1/1 | Web creó la factura `FAC-20261016-V0MKQOVF` por RD$300.00, contado. En la vista Web el stock bajó de 20 a 19; la app Android, iniciando sesión como el mismo owner sintético, volvió a descargar el catálogo y mostró `En stock (19)`. Android no realizó una mutación adicional. |
| Web local: login, listado de tiendas, catálogo y POS | PASS en el recorrido realizado | El catálogo mostró `QA Producto A`, precio RD$300.00 y stock 20 antes de cobrar; la confirmación mostró la factura y stock 19. No hubo errores de consola tras corregir la configuración local del servidor. |
| Identidad de API QA | PASS, repetida antes de fixtures y lectura cruzada | `{ environment: testing, connection: sqlite, driver: sqlite, database: ...micatalogo-sync-20261012.sqlite }` |

Las cuatro jornadas Android UI cubren deuda/movimientos, entrada a Pedidos, resumen por tienda y cambio de tienda/cliente/pedido. Las integraciones adicionales cubren venta offline idempotente, consumo de ml de Decants y carga XLSX con confirmación/replay. No equivalen por sí solas a certificación completa de todos los módulos.

## Defecto de harness reproducido y corregido

El primer intento de las jornadas UI expiró antes del login porque el test buscaba `Iniciar sesion`, sin tilde, pero la interfaz real presenta `Iniciar sesión`. Se corrigieron las ocho referencias de texto en `EmulatorJourneyTest.kt` y se reejecutaron los tres grupos UI; las cuatro jornadas finalizaron PASS. No era un defecto del login del producto.

En el primer arranque Web apareció un 404 de `livewire.min.js`: el servidor temporal se lanzó con `APP_DEBUG=false`, pero la caché de rutas existente define `livewire.js`. La petición a `livewire.js` devolvió HTTP 200 y a `livewire.min.js` 404. Se alineó únicamente el proceso local Web a `APP_DEBUG=true` (sin limpiar o cambiar cachés del repositorio); recarga posterior: 0 errores y 0 warnings de consola. El proceso permanece ligado a `127.0.0.1`; no se trasladó esta opción a producción.

## Cambios de esta ejecución

- Android: texto del login corregido en `app/src/androidTest/java/com/example/bspos/presentation/emulatorqa/EmulatorJourneyTest.kt`.
- Android: nueva prueba `SharedWebQaReadbackTest.kt`, que verifica identidad SQLite, entra con credenciales de fixture suministradas por argumentos de instrumentación, lee el producto y coteja stock sincronizado, sin vender ni escribir registros comerciales.
- Web/API: ningún archivo de aplicación modificado en este corte; el informe documenta la configuración QA local y las evidencias cruzadas.

## Pendientes — no certificados

- Completar los ocho flujos E2E del alcance maestro íntegramente entre UI Web y Android. Este corte prueba jornadas live Android existentes y una operación Web→Android; FIFO de compra, devolución, servicios, combos y ciclo completo de permisos no quedaron todos ejecutados de extremo a extremo entre clientes.
- Ejecutar/cerrar las 48 verificaciones QA y los 70 casos NAV originales, registrando cada ID individualmente; los resultados de una suite no se extrapolan a casos no ejecutados.
- Categorías/unidades con roles limitados, conflictos de sync, accesibilidad, actualización de productos históricos y paridad editable de `brand`/`barcode`; `model` continúa fuera del contrato compartido.
- Revisar decisiones de negocio que siguen marcadas `REQUIERE_DECISION` en `qa/TRACEABILITY.md`.
- No hay aprobación de producción, publicación, merge ni despliegue. El estado general continúa siendo **NO-GO**.
