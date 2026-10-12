# Sincronización inmediata — implementación y evidencia QA

## Resultado verificado

Última ejecución local del **12 de octubre de 2026**:

- Backend completo: **574 PASS**, 6,355 assertions, SQLite `:memory:`, entorno `testing`.
- Android: compilación Kotlin y **113 pruebas unitarias PASS**.
- Instrumentación: **173 casos enumerados y proceso PASS** (`test-result-exit-code.txt = 0`). Se ejecutaron 166 flujos con aserciones; 7 casos quedaron en `AssumptionViolatedException` intencional porque requieren fixtures aislados o una sesión/API live. No hubo fallos inesperados.
- Los 7 casos condicionados fueron: importación live aislada, venta/decant live aislada, venta offline live aislada y tres recorridos UI que requieren sesión API viva.
- El backend incluye ahora pruebas específicas para operaciones administrativas durables y chat de soporte: replay, permisos, aislamiento por tienda y creación/edición de equipo.
- La APK release publicada: **1.0.105 / versionCode 106**, `com.bsolutions.micatalogo`, firma existente verificada y `debuggable=false`.
- Producción quedó actualizada con las dos migraciones aditivas y el manifiesto Android. No se ejecutaron seeders, purgas ni operaciones de datos de negocio.

El PASS anterior corresponde a la batería automatizada declarada. No significa que se hayan recorrido manualmente todas las combinaciones de la matriz de menús del plan ni que todos los endpoints históricos hayan sido migrados al nuevo sobre durable. La certificación universal de la sección 8 continúa pendiente de la ampliación de cobertura y de la auditoría de esos endpoints.

## Cambios implementados

### Arnés completo

- Fixtures con `ShopScope` explícito, consultas DAO con tenant y lecturas de flujos posteriores a su emisión inicial de limpieza.
- Migraciones históricas recorren la cadena completa; los datos ambiguos se verifican mediante lecturas históricas y permanecen fuera de operaciones.
- Dobles de API actualizados con `eligibleReturns`, callbacks Decants actuales y helper tenant en los perfiles que lo necesitan.
- Perfiles `all`, `local`, `tenant`, `ui`, `live`; respectivamente 173, 89, 25, 78 y 40 tests declarados en esta ejecución. Los perfiles se superponen en los tests tenant compartidos, por lo que sus cantidades no se suman.
- Manifest de métodos esperados incorporado en los assets del APK de pruebas. El informe registra clases/métodos faltantes, resultados inesperados y assumptions.
- `IsolatedQaRunner` rechaza paquete normal, API no local y dispositivo físico antes de ejecutar los casos.

### Catálogo, rutas y motivos

- Migración **aditiva** `2026_10_12_000001_create_shop_sync_records_table.php`, sin reemplazar categorías, productos ni datos existentes.
- `category_upsert`, `unit_upsert`, `adjustment_reason_upsert` generan outbox dentro de la transacción local.
- Categorías web se reconocen mediante la identidad UUID determinística Android; las nuevas conservan su UUID y las referencias de productos.
- Unidades personalizadas conservan la referencia `unit_uuid` del producto.
- Lectura del catálogo incorpora `store_records` y `route_stock`; preserva proyecciones pendientes incluso si el ACK llega durante la descarga.
- `route_upsert`, asignar/desasignar/reordenar clientes, crear/cerrar cargas: validación tenant y replay idempotente.
- Una carga es una **asignación de inventario existente**: no fabrica unidades. El almacén no vende unidades asignadas a rutas abiertas. Venta de ruta consume su asignación; cierre de carga libera su remanente al almacén.
- Contrato actual de cargas: productos físicos por unidad, excluye combos y presentaciones medidas en ml. Cantidad y costo capturados del documento se conservan históricamente.
- No se reasigna automáticamente un registro histórico `legacy_ambiguous` durante read-back.

### Operaciones y estado visible

- `DurableShopMutation`: UUID e instantánea persistidos antes de HTTP; conservar identidad al reintentar después de cerrar/reabrir o perder el ACK.
- Sobres implementados: caja, movimientos, gastos/pagos, cierre diario, cotizaciones/conversión, pedidos, compras/recepción, suplidores, importación y socios/movimientos.
- Los sobres reutilizan los controladores originales: validaciones, permisos, límites, FIFO y reglas de caja siguen siendo la autoridad. Una respuesta rechazada revierte el registro de ACK del sobre.
- Pedidos mantienen su contrato original de **ID numérico**; cotizaciones, compras y socios utilizan sus identificadores públicos correspondientes.
- Cobros directos pasan por `payment_sync_outbox`; un intento pendiente del mismo cliente no se convierte en una segunda operación inadvertida. El ACK valida UUID, cliente, importe e ID de pago y conserva el comprobante.
- Devoluciones de facturas remotas usan outbox; incluyen cálculo exacto de descuento/impuesto por porción, recarga y bloqueo de doble envío.
- `ShopReadModelEvents` invalida módulos después del read-back exitoso. Finanzas rechaza respuestas tardías de otra tienda/período; inventario refresca valoración e importación usa exclusivamente la tienda activa.
- WorkManager usa `APPEND_OR_REPLACE`: una mutación que llega al final de otro worker no pierde su despertar.
- Banner compartido: verificando, sincronizado, pendiente, reintentando y requiere revisión; reintento conserva la instantánea/UUID.
- Ventas muestra el read model remoto y separa las operaciones locales pendientes para evitar duplicar ventas confirmadas. Exportación CSV consulta todo el período filtrado, no solamente la página de 100 facturas.

### Soporte, perfil y administración de tienda

- Las mutaciones de cuenta `profile_update`, `support_create`, `support_message` y `support_close` se encolan en el outbox durable de cuenta y se reintentan con el mismo UUID.
- Las mutaciones de tienda `shop_settings_update`, `seller_menu_permissions_update`, `shop_menu_visibility_update`, `seller_create`, `authorization_approve` y `authorization_reject` se encolan en el outbox de la tienda y reutilizan los controladores existentes para conservar validaciones y permisos.
- El backend mantiene el límite de tenant: un propietario de otra tienda no puede reproducir una operación administrativa sobre una tienda ajena.
- `support_read` quedó disponible en el endpoint durable de cuenta; las lecturas normales del chat siguen siendo GET idempotentes y la marcación de lectura conserva el control de privacidad existente.

## Casos live ejecutados

1. Importación de workbook con encabezados en fila 8: previsualizar, confirmar sesión y replay.
2. Venta offline de decant: consume fuente ml, sobrevive cola local y confirma una vez.
3. Tres ventas offline: reinicio de base de pruebas, sincronización idempotente y cobro.
4. Clientes compactos: deuda de pedido y dos abonos visibles, aislamiento A/B.
5. Pedidos: entrada y regreso con filtro conservado, sin falso estado vacío.
6. Resumen: venta RD$600 / ganancia RD$360 en A, B sin ventas, períodos y cambio de tienda.
7. Activity real: login, crear cliente, abrir caja, pedido a crédito, cobrar, cambiar tiendas, vaciar carrito y cerrar caja con RD$600.

Cada escenario utiliza un propietario sintético distinto. Los fixtures se conservan; no hay endpoint de reset o borrado. Autofill se desactiva temporalmente en los casos UI y se restaura al finalizar.

## Entorno y reproducción

- Emulador: `emulator-5554`, Pixel 10 Pro XL, Android 17.
- Paquete QA: `com.bsolutions.micatalogo.offlinecheck.syncqa`.
- API: `http://127.0.0.1:18767/`.
- SQLite: `C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-sync-20261012.sqlite`.
- Identidad efectiva verificada por CLI y por `/__qa/identity` antes de generar fixtures.
- Inicializador: `qa/sync_fixture.php init`, únicamente `testing + sqlite :memory:` y destino nuevo; se niega a sobrescribir.
- Router local: `php -S 127.0.0.1:18767 -t public qa/sync_router.php`. Las rutas QA no pertenecen a `routes/api.php`.
- En Android: `scripts/qa-sync.ps1 -Profile all -Live`. Verifica paquete, versión, certificado instalado y candidato, tipo de dispositivo, conexión QA y manifest de tests; un skip o assumption no pasa el gate.
- JSON completo de resultados: `/sdcard/Android/data/com.bsolutions.micatalogo.offlinecheck.syncqa/files/qa-results.json`.
- Copia de evidencia local: `C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-sync-173-results-20261012.json`.

El SDK principal carecía de archivos del runtime del emulador. Se utilizó un runtime oficial temporal 37.2.12, verificado contra el checksum del repositorio Android, conservando SDK/AVD y datos anteriores.

## APK firmada de revisión

- Ruta local: `C:/Users/waila/AndroidStudioProjects/micatalogowebApp/app/build/outputs/apk/release/app-release.apk`.
- URL pública: `https://micatalogo.bsolutions.dev/downloads/bspos-1.0.105-release.apk`.
- Certificado SHA-256: `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f`.
- SHA-256 APK: `1d84fe63d585dcfc65a9b51bc3d780fa6ed94ef589dca800be14f8cf9f6d56cb`.
- Tamaño: 41,163,084 bytes. Firma verificada, paquete correcto, minSdk 26, `debuggable=false`.
- Compilada con el helper de release y las pruebas unitarias del mismo código. La descarga pública fue verificada byte a byte.

## Despliegue backend y protección de datos

- Respaldo lógico verificado antes del cambio: `C:/xampp/htdocs/micatalogo/.deploy-backups/pre-deploy-20261012-1900/database.sql`.
- SHA-256 del respaldo: `5809bf8bdb009a66d75f4fa7d86dc83e28ebb1122077d95b7e2881043e72bab2`.
- Migraciones aplicadas: `2026_10_12_000001_create_shop_sync_records_table` y `2026_10_12_000002_create_account_mobile_operations_table`.
- Verificación posterior: 14 usuarios, 12 tiendas, 767 productos, 4 pedidos y 33 facturas; identificadores idénticos antes/después.
- `release-readiness.php` y `smoke-production.php` pasaron; el endpoint de actualización publica `version_code=106`, hash y tamaño correctos.

## Decisiones locales y pendientes de certificación universal

- Preferencias de dispositivo (impresora/Bluetooth, biometría y presentación local) siguen siendo configuración de dispositivo.
- Las rutas activas de Compras/Suplidores usan los módulos API; repositorios Room antiguos de documentos/suplidores continúan como infraestructura histórica/fixtures y no deben considerarse un contrato remoto por tener tests locales verdes.
- Soporte, perfil, configuración de tienda, visibilidad de menús, equipo/vendedores y autorizaciones ya usan sobres durables en Android. Se conservan endpoints directos de lectura y las cargas multipart de logo; la edición de tiendas administrativas globales y atributos todavía no se migró a sobre durable.
- Falta ampliar la matriz Android live de todos los menús y combinaciones de errores, incluyendo los endpoints directos restantes; el informe automático no debe presentarse como certificación universal.
- La nueva migración y el backend deben desplegarse de forma coordinada y expresamente autorizada antes de distribuir la APK de revisión a usuarios.
