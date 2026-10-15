# Baseline del cierre — F0

## Repositorios comprobados

- Web: `C:/xampp/php/www/MiCatalogo`, `master...origin/master`, HEAD `f831131` (release Android1.0.113). Working tree limpio, sin diff ni archivos sin seguimiento al iniciar.
- Android: `C:/Users/waila/AndroidStudioProjects/micatalogowebApp`, `main...origin/main`, HEAD `4fe66cf` (Collections/Decants1.0.113). Working tree limpio al iniciar.
- Commits de navegación existentes `9740a38`/`afd4da4` y certificación histórica `fcb15ce`/`5e3839a` confirmados en los logs. No se rehacen.
- No apareció `LOCAL_AI_WORKFLOW.md` en ambos árboles. Android AGENTS.md exige debug aislado en emulador, instalación compatible con `-r` y preservación de Room.

## Mapeo de taxonomía observado en fuente

1. Web: `shop_categories.id` entero y `shop_id`; `ShopCategory` no tiene soft delete ni scope. El controller actual elimina físicamente. Policy de modificación sólo owner (o override admin), y middleware de categorías requiere `products`/policies generales.
2. Sincronización: `ShopSyncRecord(shop_id,kind,record_uuid,payload)` es la fuente de unidades (`kind=unit`) y metadata móvil de categoría (`kind=category`, `payload.server_id=shop_categories.id`). Incluye actividad/borrado lógico y creación original. No hay tabla Web de unidades independiente.
3. UUID legacy: `ShopRecordSyncService.localId(shop,kind,id)` reproduce UUID v3/MD5 de `micatalogo:<kind>:<shop.public_id>:<id>`; Android usa UUID.nameUUIDFromBytes. Una categoría Web sin record explícito se reconoce por esa identidad determinista.
4. Categoría móvil creada desde Android conserva `record_uuid` y se enlaza al nuevo entero mediante `payload.server_id`; snapshots publican `client_record_uuid`. No debe regenerarse ese UUID.
5. Producto→unidad vive en `ShopSyncRecord(kind=product_unit,record_uuid=product.public_id,payload.unit_uuid)`; unidades built-in usan UUID determinista de `unit|bottle|ml|decant|service`.
6. `ShopRecordSyncService` conserva fechas de creación y estado inactivo/borrado; el flujo actual no tiene revisión esperada para conflictos de ediciones Web/Android. `MobileOperationService` sí verifica hash del mismo UUID y devuelve409 ante otro payload.
7. Room: `CategoryEntity`/`UnitOfMeasureEntity` incluyen TenantMetadata; DAOs filtran `remote_shop_id` y `tenancy_status='assigned'`. El lookup con NULL está reservado a reconciliación. Ese aislamiento es real y se conserva.
8. Android Settings muestra dos tabs pero sus mutaciones no presentan estado de pending/blocked/ACK ni alcance de taxonomía. CategoryUseCases y UnitOfMeasureUseCases delegan a los repositorios y RemoteMutationRecorder existentes.

## Autorización y UI observadas

- La entrada categories usa `products`; SellerNavigationService la filtra por esa clave. El servicio de snapshots filtra productos por tipo, pero devuelve todas las categorías de la tienda y records de unidades sin scope.
- El envelope de categoría/unidad pasa primero por Gate update(shop), después por `products`, antes de deduplicar. Un actor services-only queda bloqueado sin concederle autoridad física.
- El formulario Web sólo lista/crea/elimina categorías; no muestra Unidades, edición ni desactivación coherentes con Android. Breadcrumb enlaza Productos incluso si el actor no tiene ese permiso.
- Scope no existe hoy; abrir todo el CRUD con OR entre Products/Services podría modificar categorías físicas. La estrategia se documentará en F1 antes de cambiar código.

## Entorno y evidencia

- `emulator-5554` conectado, `sdk_gphone16k_x86_64`; instalado originalmente debug1.0.112/113. Se respaldaron y verificaron Room27 y ese APK antes de instalar debug1.0.113/114 con el mismo certificado y `install -r`.
- QA18768 se inició con el router local protegido y la base SQLite dedicada existente tras verificar identidad testing/sqlite/ruta exacta. El error ChildProcess.kill del arnés no impidió el arranque; se confirmó HTTP y log del servidor.
- Históricos: QA_REPORT.md mantiene 70 NAV y7 E2E sin cierre integral. El agregado 666 backend/147 JVM/194 instrumentadas no se presenta como ejecución nueva.

## Gate F0 — evidencia nueva comprobada

- `closure_f0_repro.php`: 1 PASS/11 aserciones, SQLite en memoria. Services-only recibe403 por GET/POST/PUT/DELETE de categorías y category_upsert/unit_upsert. Los hashes de categorías, sync records, productos, inventario, lotes, facturas y operaciones quedan idénticos. Evidencia `evidence/closure-f0-reproduction.json`.
- UI Web real: seis actores × desktop1440/móvil390, 12 PNG y DOM/manifest en `closure-f0-web-navigation.json`. Mismos enlaces desktop/móvil; única diferencia respecto a API Android es Mis tiendas para los actores con ese selector Web. Services-only tiene4 enlaces, sin categories; physical-only5; actor sin módulos3.
- UI Web de categorías: HTTP200 owner, heading Categorías internas de la tienda, cero tabs y ninguna tab Unidades; captura/DOM `closure-f0-web-category-content.*`.
- Android actual debug1.0.113/114: captura por roles en teléfono y tablet, ambas 1/1 PASS sin skips/assumptions. `closure-f0-android-phone/` y `closure-f0-android-tablet/` contienen PNG/semántica de los seis actores solicitados. Se reutilizó el test de captura existente, sin modificar código; aliases sintéticos `sales`→physical y `reports`→none durante las capturas, permisos originales restaurados y comprobados por API. Overrides de pantalla restaurados.
- Suite backend actual repetida: **666 PASS/7.614 aserciones**, 76,02s, XML `closure-f0-backend.xml`. JVM actual repetida: **147 PASS**,44 XML. Build debug/androidTest PASS. Perfil all declara201 métodos; aquí sólo se ejecutó el método de baseline visual en los dos formatos, no los201.
- SQL y Room tienen copias privadas verificadas por integrity_check y comparación exacta de todas las filas; paths/hashes en `closure-f0-initial-data.json` y `closure-f0-after-menus-data.json`. Backups y APK permanecen fuera de Git.
- Comparación SQL: sólo autenticación (tokens, last_login_at/updated_at) y updated_at de los dos permisos temporales. No cambian productos, categorías, stock, FIFO, facturas, caja, clientes/saldos ni sync records.
- Room conserva todas las filas, UUID, inventario, movimientos, ventas, pagos y outboxes con hashes idénticos. Hay3 operaciones y1 abono históricos, todos SENT, no se purgan. Cambian metadatos de caché de3 productos (`updated_at/deleted_at`,1 is_active) y la fecha de1 unidad por refresh/cambio de actor; se registran, no se ocultan ni se restaura una DB sobre el trabajo posterior. Comparación en `closure-f0-data-comparison.json`.
- `closure-f0-index.json` valida resultados y agrega hashes de la evidencia existente al generarse. La captura específica de categorías se añadió después y conserva su archivo propio.
- Confirmado por git status: sólo documentación/artefactos nuevos de auditoría modificados; ningún archivo de aplicación alterado durante F0. Gate F0 completado para avanzar a decisión F1; no aprueba NAV/E2E.
