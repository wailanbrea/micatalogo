# Verificación Web y Android — 15/10/2026

## Resultado

Las comprobaciones ejecutadas pasaron. Esto no certifica todos los flujos de producción ni todos los dispositivos. Se distinguen código local, producción y variante QA.

## Versiones y cambios

- Web local y VPS: HEAD `48cbd4a`. El VPS estaba limpio; el checkout local contiene modificaciones de navegación, permisos, operaciones Web/API y sus pruebas todavía sin commit.
- Android activo: `C:/Users/waila/AndroidStudioProjects/micatalogowebApp`, HEAD `ad9ecd7`, con cambios locales pendientes de navegación, permisos, sincronización y presentación del POS.
- La carpeta histórica `BSPOS-MiCatalogo` no es un checkout Git y declara 1.0.27/28; no se usó como fuente actual.
- Publicación Android: 1.0.111, versionCode 112, paquete `com.bsolutions.micatalogo`.
- Emulador: paquete principal 1.0.27/28; variante separada `com.bsolutions.micatalogo.offlinecheck.syncqa` 1.0.111-offlinecheck/112.
- Ningún teléfono físico apareció conectado en ADB.

## Ejecución nueva

| Comprobación | Resultado |
| --- | --- |
| Pest completo local, SQLite en memoria forzado por phpunit.xml | 663 PASS, 7.597 aserciones; 70,07 s |
| Selección navegación/permisos/POS/clientes/idempotencia | 120 PASS, 1.495 aserciones |
| Android testDebugUnitTest, ejecución forzada de la tarea | 147 PASS, 0 fallos, 0 errores, 0 omitidas; 44 XML |
| Compilación assembleDebug aislada | Correcta; tareas de compilación al día |
| Instrumentadas sobre variante QA ya instalada | 9 PASS, 0 omitidas; 7,19 s |
| Release descargada desde URL pública | Firma válida, no debuggable, paquete/versión/tamaño/hash correctos |
| HTTP login, landing, tienda BSolutions | 200 en las tres páginas |
| git diff --check web | Sin errores |

Instrumentadas ejecutadas: CollectionAuthorizationTest (2), FinancePermissionTabsTest (2), PurchaseAuthorizationTest (5). Comprueban revocación de permisos, prohibición de operaciones sin acceso, descarte de respuestas antiguas al cambiar tienda, recarga de suplidores y pestañas financieras. Usan fixtures aislados y Room en memoria; no son ventas reales en producción.

## Identidad de la APK publicada

- URL: https://micatalogo.bsolutions.dev/downloads/bspos-1.0.111-release.apk
- SHA-256 APK: `7edcfa5e06d2de5ac68659aba074eabc6bfb91e76e8ca3284f16d865b5031138`.
- SHA-256 certificado: `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f`.
- Tamaño: 41.212.236 bytes; SDK mínimo 26.
- El manifiesto público y el archivo descargado coinciden.

## Hallazgos y límites

1. **Pendiente de entrega:** los cambios de navegación y autorización probados están locales; no forman parte de una nueva versión web/APK publicada. No se hizo commit, push ni despliegue durante esta revisión.
2. **Emulador desactualizado en el paquete principal:** probar 1.0.27 no verifica la release 1.0.111. Las nueve instrumentadas corresponden al paquete QA separado, no a la APK release descargada.
3. **Rendimiento web:** TTFB observado login 3,72 s, inicio 2,03 s y tienda pública 5,23 s. Son muestras puntuales, no percentiles; sigue habiendo lentitud.
4. **Sistema del emulador:** el buffer de crash consultado contiene abortos repetidos del servicio Android UWB. No prueba un fallo de MiCatalogo y tampoco permite certificar ausencia de crashes de la app.
5. **Cobertura pendiente documentada:** `docs/navigation/QA_REPORT.md` y `F4_RESULTS.md` aún no aprueban todos los gates F4–F6 ni todos los journeys UI, offline, multitienda y accesibilidad. Los PASS de esta revisión no cierran esos pendientes automáticamente.
6. **Interfaz de producción:** la comprobación web de esta revisión fue HTTP y pruebas automatizadas locales. No se completó una nueva sesión autenticada de navegador recorriendo todos los menús ni una prueba release en Samsung.

## Conservación

No se borraron datos, no se ejecutaron migraciones ni seeders, no se registraron operaciones financieras en producción y no se publicaron versiones. Se preservaron los cambios locales existentes en ambos proyectos.

## Reejecución focalizada de QA — sincronización y modales (15/10/2026)

Esta ampliación sí ejecutó recorridos interactivos locales y pruebas actualizadas contra los cambios pendientes. No publica ni despliega.

| Capa / recorrido | Resultado |
| --- | --- |
| Backend completo | `php vendor/bin/pest`: **679 PASS, 7.710 assertions**, `phpunit.xml` fuerza `APP_ENV=testing`, SQLite `:memory:` y `DB_URL` vacío. |
| Backend — taxonomía, sync y tenants | **35 PASS, 354 assertions**: escritura Web/API, ACK y lectura posterior, replay idempotente, rechazo de payload distinto (409), revisión obsoleta, permisos revocados, aislamiento tenant y desactivar/reactivar preservando UUID y referencias. |
| Android — sincronización Room/API | `CatalogSyncIntegrationTest`: **17/17 PASS**; snapshot repetido sin duplicar movimientos, mutaciones pendientes preservadas, tienda incorrecta rechazada y tombstones no borran cambios locales pendientes. Base Room en memoria y API simulada. |
| Android — Decants y transición de modal | `DecantsWorkspaceTest`: **11/11 PASS**, cero skips. Incluye regresión de la secuencia Productos sin volumen → Configurar botella → cancelar, muestra el modal seleccionado y cancelar no guarda el volumen. |
| Android — sincronización real de prueba | `PosSaleLiveIntegrationTest#offlineQueuedDecantConsumesSourceMillilitersAndSyncsExactlyOnce`: **1/1 PASS** en `emulator-5554`, app QA aislada + Laravel QA local + SQLite `micatalogo-sync-20261012.sqlite`. Verifica que la venta en cola sincroniza una sola vez, la fuente baja de 100 a 90 ml y el stock de decants de 10 a 9. La fixture sintética queda retenida únicamente en ese archivo SQLite QA; no existe limpieza automática ni se ejecutó contra producción. |
| Web — terminal/cobro | Navegador Playwright en el servidor autenticado de QA local. Añadir producto y abrir Cobrar; cerrar por `×` y por Escape cierra el diálogo, conserva el carrito y no confirma ni registra venta. Luego se vació el carrito del navegador. |
| Web — responsive/errores | En viewport 390×844 no hay desbordamiento horizontal; la consola reporta 0 errores ni advertencias durante el recorrido. La vista Categorías y unidades y sus pestañas cargaron; no se enviaron formularios mutadores. |
| Entorno QA web | Identidad verificada antes de interactuar: `APP_ENV=testing`, driver SQLite y archivo `micatalogo-navigation-20261014.sqlite`. El router de sincronización se verificó contra su SQLite aislado, se apagó al finalizar y se retiró únicamente el reverse ADB temporal de 18767; el reverse previo de 18768 se conservó. |
| Android unitarias | `testDebugUnitTest` terminó `BUILD SUCCESSFUL`; Gradle informó las tareas al día. No se fuerza en este resultado una cifra de casos unitarios ejecutados en esta reejecución. |

Se añadió la prueba Compose de transición y cancelación en `app/src/androidTest/java/com/example/bspos/presentation/decants/DecantsWorkspaceTest.kt`.

### Límites que continúan abiertos

- El test integrado de venta/decant demuestra el ACK entre la app QA y la API Laravel aislada; no equivale a una prueba de mutación de una cuenta comercial ni valida todos los módulos en una sesión E2E.
- No se revisó cada modal de todos los menús. El cierre interactivo cubierto aquí es el cobro Web y la transición/cancelación del modal de botella en Android; los demás requieren una matriz de recorrido por módulo.
- Los cambios continúan locales y sin commit/push/despliegue; no se generó ni publicó APK. Producción no se tocó.
