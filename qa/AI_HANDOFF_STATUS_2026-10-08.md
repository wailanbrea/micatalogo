# Traspaso QA de MiCatalogo — estado para otra IA

Fecha del corte: 2026-10-08. Este documento es una fotografía operativa del checkout
actual y no reemplaza `AGENTS.md` ni las skills obligatorias.

## Estado ejecutivo

El release gate continúa **NOT_READY**. El baseline local Web/API y Android está
verde en los escenarios ejecutados, y la revocación remota de sesión ya quedó
corregida y probada localmente. Todavía hay bloqueos reales: la release no puede
instalarse sobre el Samsung porque conserva una APK debug con otro certificado, los
cambios locales aún no están publicados en VPS ni en una nueva APK, y permanecen
casos de negocio, visuales, hardware, offline físico y cobertura positiva completa
sin cerrar.

No se hizo publicación, push, instalación ni desinstalación en el Samsung durante
este corte. No se escribieron datos de producción.

## Repositorios y estado Git

### Backend/Web/API

- Ruta: `C:\xampp\php\www\MiCatalogo`
- Rama: `master`, con commits locales de QA aún no publicados frente a `origin/master`
  (el conteo debe verificarse antes de cualquier push).
- Al cierre de este documento el checkout tiene commits locales adelantados de
  `origin/master`; confirmar el conteo justo antes de cualquier push con
  `git rev-list --left-right --count origin/master...HEAD` porque la documentación
  de este archivo también modifica el conteo.
- Última corrección funcional local: `734b169 fix: revoke API token on mobile logout`.
- Los commits posteriores a `734b169` son documentación y evidencia QA; no deben
  confundirse con un despliegue publicado. El SHA exacto de `HEAD` debe obtenerse
  con `git log -1 --oneline` porque este documento también evoluciona.
- Cambios rastreados: limpios; quedan únicamente los no rastreados intencionales.
- No tocar ni borrar los no rastreados existentes: `.github/`, `.playwright-cli/`, `output/`.

### Android

- Ruta real activa: `C:\Users\waila\AndroidStudioProjects\micatalogowebApp`.
- La skill histórica menciona `BSPOS-MiCatalogo`; en esta máquina el checkout vigente
  es `micatalogowebApp`. No crear ni cambiar de proyecto sin comprobar Git.
- Rama: `main`, **4 commits adelantados de `origin/main`**.
- Último commit local: `e50e5ff test: cover finance unavailable states`.
- Cambios funcionales clave: `7ea2916` updater pendiente persistente, `c4aea97`
  bloqueo del updater de producción para builds debug, `7c32766` prueba de PDF,
  `5d57421` cierre de sesión con revocación remota y `de03913` regresión de replay
  después de ACK antes de marcar `SENT`.
- Cambios rastreados: limpios. Conservar el no rastreado `.github/`.

No usar `git reset --hard`, `git checkout --`, limpieza masiva ni borrar los
directorios no rastreados. Antes de cualquier push revisar primero el diff y los
commits locales; no hacer push sin autorización explícita para esa publicación.

## Release Android publicado y firma

- Paquete: `com.bsolutions.micatalogo`.
- Release pública actual: `1.0.76`, `versionCode 77`.
- Manifiesto: `https://micatalogo.bsolutions.dev/api/v1/app-updates/android`.
- APK pública: `https://micatalogo.bsolutions.dev/downloads/bspos-1.0.76-release.apk`.
- SHA-256 APK: `24846233f98bfd099976d0965142b8aabca71a378659b74b5a8b14aa27334f6b`.
- Tamaño: `40,387,781` bytes.
- Certificado oficial SHA-256: `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f`.
- APK local verificada: `C:\Users\waila\AndroidStudioProjects\micatalogowebApp\app\build\outputs\apk\release\app-release.apk`.
- Keystore existente: `C:\Users\waila\.android\micatalogo-release-20261004-v2.jks`.
  Nunca imprimir, copiar, subir o regenerar credenciales o contraseñas.
- Archivo privado de configuración de firma: `C:\Users\waila\AndroidStudioProjects\BSPOS-MiCatalogo\FIRMA_RELEASE_PRIVADA.txt`.
  Está fuera del checkout Android activo `micatalogowebApp`; por eso el diagnóstico
  por defecto puede devolver `PRIVATE_FILE_MISSING`. Pasando `-PrivateFile` de forma
  explícita, el diagnóstico actual devolvió `signing_ready`, abrió la keystore, validó
  la clave privada y restauró el entorno sin imprimir secretos.

La release local pasó verificación independiente: paquete correcto, código 77,
`debuggable=false`, firma oficial y SHA coincidente. La release 1.0.76 publicada no
contiene todavía los cambios locales del updater persistente (`7ea2916`) ni la prueba
QA de PDF; eso exige una nueva release con `versionCode` mayor antes de publicar.

## Samsung físico: bloqueo reproducido

Destino: serial
`adb-R3GL40GB6CE-io558r._adb-tls-connect._tcp`, modelo `SM-S948U1`.

Estado leído sin mutar:

- Instalado: `com.bsolutions.micatalogo`, versión `1.0.27`, `versionCode 28`.
- Flags: `DEBUGGABLE`, `TEST_ONLY`.
- Certificado instalado: `Android Debug`, SHA-256
  `e266fd48b028dc66dc4e530c0bc8af06b9be820545600c6c4b19c81b4b1ff775`.
- Release objetivo: `1.0.76`, código 77, certificado oficial diferente.
- El verificador devolvió `blocked_signature_mismatch`.
- La APK release no está corrupta; Android rechaza la actualización porque las firmas
  no coinciden. Subir el `versionCode` o usar `adb install -r` no resuelve un cambio de
  certificado.
- No se ejecutó `adb uninstall`, `pm clear`, instalación física ni limpieza de datos.

Verificador de solo lectura:

```powershell
& 'C:\xampp\php\www\MiCatalogo\qa\verify_android_release_target.ps1' `
  -Serial 'adb-R3GL40GB6CE-io558r._adb-tls-connect._tcp' `
  -ApkPath 'C:\Users\waila\AndroidStudioProjects\micatalogowebApp\app\build\outputs\apk\release\app-release.apk'
```

Diagnóstico de firma local (solo lectura; no compila ni publica):

```powershell
& 'C:\Users\waila\.config\opencode\skills\micatalogo-android-release\scripts\release.ps1' `
  -Mode Diagnose `
  -ProjectRoot 'C:\Users\waila\AndroidStudioProjects\micatalogowebApp' `
  -PrivateFile 'C:\Users\waila\AndroidStudioProjects\BSPOS-MiCatalogo\FIRMA_RELEASE_PRIVADA.txt'
```

Para resolverlo en el futuro se necesita autorización explícita para desinstalar
solo ese paquete del Samsung e instalar la release oficial. La desinstalación borra
datos locales de la app (sesión, Room/cache y outbox local), pero no elimina datos
guardados en el servidor. Antes de hacerlo hay que comprobar que no exista outbox
local pendiente y registrar el resultado. No instalar debug en el teléfono físico.

## Emulador y QA Android

Emulador permitido: `emulator-5554`. El Samsung nunca debe seleccionarse para la
batería debug. El runner seguro exige `ro.kernel.qemu=1`:

`C:\xampp\php\www\MiCatalogo\qa\run_android_emulator_tests.ps1`

Resultados actuales:

- `testDebugUnitTest`: **95/95 PASS**.
- Suite instrumentada normal: **117 casos**, **114 PASS**, **3 assumptions** por
  fixtures live no suministrados en la batería completa.
- Variante `offlinecheck`: **116 casos**, **113 PASS + 3 assumptions**, sin fallos.
- Suite instrumentada `com.bsolutions.micatalogo.offlinecheck`: mismo resultado.
- `InvoicePdfGeneratorTest`: **2/2 PASS**; PDF legible por `FileProvider` y carrito
  largo paginado en más de una página.
- E2E live importador: **1/1 PASS** en `offlinecheck`, SQLite temporal, servidor
  `127.0.0.1:8893` y `adb reverse`.
- E2E live POS: **2/2 PASS** en `offlinecheck`; contado/crédito, cliente/abono,
  decant con consumo de ml, ACK y replay sin duplicados.
- El servidor fixture y el reverse `8893` fueron detenidos y retirados al terminar.
- Permanece un reverse preexistente `host-15 tcp:8000 tcp:8000`; no retirarlo sin
  identificar qué proceso lo usa.

### Revalidación Android después de este traspaso

- `:app:testDebugUnitTest :app:lintDebug` terminó **BUILD SUCCESSFUL**; el XML de la
  batería JVM contabiliza **95 tests, 0 failures, 0 errors, 0 skipped** y lint no
  reportó errores bloqueantes.
- Se recompiló e instaló únicamente `app-debug-androidTest.apk` en `emulator-5554`.
  `CatalogSyncIntegrationTest` terminó **16/16 PASS**.
- `FinancialContractTest` terminó correctamente como prueba JVM con
  `:app:testDebugUnitTest --tests ...FinancialContractTest`: **5/5 PASS**. Un intento
  de invocarlo con el runner instrumentado falló solo por clasificación de test
  (`ClassNotFoundException`); se corrigió el procedimiento y no se modificó código ni
  datos de negocio.
- `FinanceContentTest` terminó **2/2 PASS** en `emulator-5554` después de limpiar y
  reconstruir la variante debug: Resumen y Flujo de efectivo muestran el estado de
  datos no disponibles cuando el DTO falta y no fabrican `RD$ 0.00`. Se ejecutó con
  `:app:connectedDebugAndroidTest` y el filtro de instrumentation; no se tocó el
  Samsung ni se escribieron datos de negocio.
- El Samsung no fue seleccionado por ninguna de estas pruebas.

Comandos de prueba seguros:

```powershell
& 'C:\xampp\php\www\MiCatalogo\qa\run_android_emulator_tests.ps1' `
  -Serial 'emulator-5554' `
  -TestPackage 'com.bsolutions.micatalogo.offlinecheck.test'
```

Los fixtures live correctos son `scripts/testing/inventory-import-e2e.php` y
`scripts/testing/pos-sale-e2e.php`. Solo aceptan SQLite temporal, `APP_ENV=testing`,
servidor local y reverse ADB; nunca apuntarlos a VPS o producción. Sus tokens son
sintéticos y no deben copiarse a reportes.

## Backend/Web/API y producción

Evidencia QA más reciente:

- Suite completa: **495 tests / 5.073 assertions PASS** en SQLite y MariaDB QA
  aislada.
- Benchmark de catálogo de 1.500 productos: máximo 17 consultas; p50/p95 más
  reciente SQLite `334.61/345.77 ms`, MariaDB QA `323.90/339.09 ms`, memoria
  `148/148 MB`. Sigue siendo benchmark local, no latencia real de red/dispositivo.
- Matriz dirigida RBAC/tenant: **24 tests / 369 assertions PASS** en ambos motores.
- MariaDB QA: `61 Ran, 0 Pending`; tablas operativas quedaron en cero después de
  las corridas.
- Producción/VPS, última verificación documentada: usuarios 14, tiendas 12,
  productos 756, pedidos 2, facturas 22; identificadores conservados y 0
  migraciones pendientes.
- Backup lógico existente previo al despliegue:
  `C:\xampp\htdocs\_backups\micatalogo-pre-deploy-20261008-134507.sql`,
  SHA-256 `a63aa6d6aa2bdfbf9e96277b54bfa45f4ea36653b0286ba2641b204a82b73b3b`.
  No crear otro backup solo para una prueba local; antes de tocar VPS leer
  `C:\Users\waila\.codex\skills\bsolutions-infra\SKILL.md`.

No se ejecutaron seeds, truncates, `migrate:fresh`, `migrate:refresh`, `migrate:reset`
ni purgas sobre producción. No hay migraciones pendientes conocidas.

### Revalidación del baseline después de este traspaso

- SQLite: `vendor\\bin\\pest --configuration phpunit.xml` terminó **495 tests / 5.073
  assertions PASS** en 50.11 s.
- MariaDB QA: `APP_ENV=testing`, `QA_ALLOW_MYSQL=1`, base
  `micatalogo_qa_20261008` y `qa\\phpunit.mysql.xml` terminaron **495 tests / 5.073
  assertions PASS** en 48.57 s.
- `release-readiness.php` en esa base devolvió usuarios, tiendas, productos, pedidos e
  invoices en `0`; no creó datos ni ejecutó migraciones.
- `migrate:status --env=testing` devolvió todas las migraciones visibles como
  `[1] Ran` y ninguna como `Pending`.
- El benchmark de catálogo de esta corrida quedó en 17 consultas, p50/p95 de
  271.92/283.56 ms en MariaDB QA y 148 MB de pico de memoria. Es medición local y no
  sustituye latencia de red o carga de producción.

## Cambios y defectos cubiertos

La cobertura y correcciones registradas incluyen, entre otros:

- precisión monetaria en centavos, FIFO/COGS, utilidad, caja, cartera y dashboards;
- idempotencia/replay de ventas, cobros, caja, importación y pedidos;
- rollback POS después de crear factura/inventario;
- protección RBAC/tenant para owner, manager, vendedor, contador, admin y anónimo;
- ocultación de costos/márgenes para vendedores sin permiso financiero;
- jornadas de compra, cotización, venta contado/crédito, abono, gasto, cierre,
  pedidos públicos, comisiones, multi-tienda y devoluciones de decants;
- importador adaptativo con fila de encabezados, mapping, duplicados e idempotencia;
- POS/Terminal, cotizaciones, cierre diario, actualización pendiente y PDF local;
- variante offlinecheck separada para no confundir debug QA con la app release.

Los detalles y límites están en:

- [QA_RELEASE_REPORT.md](C:\xampp\php\www\MiCatalogo\qa\QA_RELEASE_REPORT.md)
- [TEST_EXECUTION.md](C:\xampp\php\www\MiCatalogo\qa\TEST_EXECUTION.md)
- [FINDINGS.md](C:\xampp\php\www\MiCatalogo\qa\FINDINGS.md)
- [TRACEABILITY.md](C:\xampp\php\www\MiCatalogo\qa\TRACEABILITY.md)
- [RISK_REGISTER.md](C:\xampp\php\www\MiCatalogo\qa\RISK_REGISTER.md)
- [SCREEN_COVERAGE.md](C:\xampp\php\www\MiCatalogo\qa\SCREEN_COVERAGE.md)

## Pendientes que mantienen NOT_READY

1. **Samsung/release física:** autorizar transición debug → release, comprobar outbox
   local, desinstalar solo el paquete, instalar la APK oficial, verificar versión,
   firma, arranque, login y recuperación. Probar después el updater físico.
2. **Nueva APK release:** integrar `7ea2916` y `7c32766`, incrementar versión/código,
   compilar con la keystore oficial, ejecutar verificación y publicar solo con
   autorización. Actualizar el manifiesto al mismo artefacto.
3. **Actualizador:** probar en dispositivo real cancelación, pérdida de red,
   reanudación y reapertura obligatoria; la release actual todavía no contiene los
   arreglos locales.
4. **Cobertura positiva completa:** completar verbo × ruta, recursos anidados,
   archivos, roles y capacidades; no extrapolar desde las pruebas de frontera.
5. **Finanzas:** resolver F-036 (método/movimiento contable de reembolso de una venta
   pagada) y F-038 (conversión/prorrateo de costo aterrizado internacional), luego
   añadir regresiones de FIFO, margen, caja, replay y cierre.
6. **Offline físico:** caída real de red, kill/reconnect, reinicio de proceso,
   actualización interrumpida y más escenarios de replay/devolución.
7. **Visual/UI:** recorrido exhaustivo de Web y Android por pantalla/rol/tamaño,
   estados carga-vacío-error, rotación, scroll, modales, Puntto parity y responsive.
8. **Hardware/externos:** compartir PDF/WhatsApp, permisos modernos, cámara/ML Kit,
   Bluetooth/impresión 58/80 mm, R2/colas y servicios externos.
9. **Calidad CI:** `vendor/bin/pint --test` aún reporta deuda de formato en 37
   archivos; no reformatear masivamente cambios ajenos sin revisar el diff.
10. **Documentación/branches:** decidir qué commits locales se integran y hacer push
    solamente después de revisar los siete commits backend y uno Android adelantados.

## Verificación adicional de cierre de sesión y Samsung (2026-10-08)

Se repitió el verificador de instalación en modo estrictamente de solo lectura con los
dos dispositivos ADB conectados. El resultado volvió a ser `blocked_signature_mismatch`:

- Samsung `SM-S948U1`: paquete instalado `1.0.27`, código 28, certificado debug
  `e266fd48...1ff775`.
- APK release local: `1.0.76`, código 77, certificado oficial
  `5a5670de...578d490f`.
- `install_requested: false`; no se desinstaló, no se limpió almacenamiento y no se
  modificó ningún dispositivo.

Conclusión: no es un problema de versión, tamaño ni corrupción de la APK. Android
rechaza la actualización porque la instalación existente fue firmada con otra clave.
La transición segura requiere verificar primero el outbox local y, con autorización
explícita, desinstalar únicamente `com.bsolutions.micatalogo` del Samsung e instalar
la release oficial. Esa operación borra los datos locales de la app, no los datos del
servidor.

La inspección inicial del flujo de cierre de sesión Android encontró que
`SettingsViewModel.logout()` solo limpiaba `MiCatalogoConnectionRepository.clearConnection()`;
sin una ruta remota el token Sanctum podía seguir válido en el servidor.

### Corrección aplicada después de ese hallazgo

Se implementó la revocación explícita del token actual:

- `POST /api/v1/auth/logout` elimina el token Sanctum usado por la solicitud y responde
  `204 No Content`.
- Android llama esa ruta al cerrar sesión y siempre limpia el almacenamiento local,
  incluso si la red falla o el token ya fue invalidado (`401`).
- La prueba backend confirma que el mismo token ya no puede acceder a `/api/v1/me`.
- Regresión backend: `ApiAuthenticationTest`, **17/17 tests y 79 assertions PASS**.
- Regresión Android: `testDebugUnitTest` y `compileDebugAndroidTestKotlin`, **BUILD
  SUCCESSFUL**.
- `lintDebug` Android terminó **BUILD SUCCESSFUL**; conserva únicamente los warnings
  no bloqueantes ya conocidos.

Esta corrección está únicamente en los checkouts locales; todavía no se ha desplegado ni
publicado en VPS o en la APK pública.

## Trabajo completado en este corte

- Se inventariaron y documentaron las funciones, rutas, roles, tiendas, módulos
  financieros, importador, POS/Terminal, cotizaciones, decants, pedidos, cierres,
  actualizador y navegación Android/Web en los reportes de `qa/`.
- Se ejecutó el baseline completo del backend con SQLite y MariaDB QA aislada:
  **495 tests, 5.073 assertions PASS** en cada motor; migraciones QA: **61 ejecutadas,
  0 pendientes**; las tablas operativas de QA quedaron en cero.
- Se reforzó autenticación con una corrida dirigida: `AuthenticationTest` +
  `ApiAuthenticationTest`, **29 tests y 127 assertions PASS** en SQLite y MariaDB QA;
  incluye login Web/API, cuentas suspendidas/inactivas/no verificadas, límites,
  actualización de perfil, logout y revocación de token. Caducidad real, dos dispositivos
  y reinstalación siguen fuera de evidencia.
- Se ejecutó el contrato Android de caja local/remota: `CompleteSaleUseCaseTest` +
  `RecordPaymentUseCaseTest`, **9/9 PASS instrumentados** en `emulator-5554`; se comprobó
  que la venta remota no crea caja Room duplicada, que el cobro efectivo exige sesión
  local, y que crédito, sobrepago y rollback mantienen saldos e inventario coherentes.
  La caída física de red, la sincronización real y las etiquetas UI siguen pendientes.
- Se reejecutó la regresión financiera aislada en SQLite en memoria: **57 tests / 303
  assertions PASS**, cubriendo FIFO por lotes, decants, reglas automáticas de precio,
  ventas mixtas, crédito, abonos FIFO, caja, gastos, cierres, idempotencia y E2E-03.
  No se escribieron datos persistentes ni se tocó MariaDB/VPS.
- Se reejecutó la matriz de permisos y tenant en SQLite en memoria: **46 tests / 551
  assertions PASS**, incluyendo owner, manager, vendedor, contador, admin global,
  aislamiento entre tiendas, rutas mutantes, menús, redacción de costos/FIFO y módulos
  operativos. No se escribieron datos persistentes.
- Se reejecutó el bloque catálogo/importación/POS/pedidos en SQLite en memoria: **85
  tests / 558 assertions PASS**, incluyendo importación por sesión, prevención de
  duplicados, imágenes/Excel/PDF, lotes y stock, decants, mayoreo, replay idempotente,
  pedido público, factura/caja y rollback atómico. No sustituye hardware, WhatsApp real
  ni E2E Web↔Android.
- Se añadió y ejecutó la regresión `CatalogSyncIntegrationTest` para el caso ACK→interrupción
  antes de marcar `SENT`: **16/16 PASS** en `emulator-5554`; la venta queda en `RETRY` y
  el siguiente sync la confirma con la misma UUID. Es una simulación determinista, no
  sustituye matar el proceso físico ni probar una red intermitente en una release.
- `FinancialContractTest` terminó **5/5 PASS**; los campos financieros obligatorios
  ausentes fallan con error de contrato en vez de producir saldos cero falsos. La UI de
  error y los reportes sin red todavía requieren ejecución en dispositivo.
- Se inspeccionó `FinanceScreen`/`FinanceViewModel`: el error se publica por
  `Snackbar`/`UiErrorBus`; la pantalla conserva un `summary` anterior si una recarga
  falla después de haber cargado datos. `FinanceContentTest` cubre ahora directamente
  los estados nulos de Resumen y Flujo de efectivo (**2/2 PASS**), pero no cubre aún
  la recarga fallida con datos previos, el estado offline global ni reportes sin red;
  esas partes siguen **NOT_RUN**, no PASS.
- Se ejecutó `npm run build` con Vite sin errores; el inventario actual conserva
  **216/216 rutas**, sin duplicados de método+URI, y el endpoint `api/v1/auth/logout`
  quedó confirmado con autenticación Sanctum. La revisión de `storage/{path}` verificó
  que el PUT público de Laravel exige firma relativa válida; no se hicieron uploads.
- El chequeo local `release-readiness.php` terminó en modo lectura: usuarios, tiendas,
  productos, pedidos e invoices en el entorno QA quedaron en cero; no se ejecutaron
  migraciones ni escrituras contra producción.
- Se verificaron las invariantes de tenant/RBAC con la matriz dirigida y se cubrieron
  jornadas positivas mínimas de owner, manager, vendedor, contador y equipos.
- Se validaron importación adaptativa, ventas contado/crédito, abonos, decants,
  replay/idempotencia, rollback de POS, cierre, cotizaciones, PDF y actualización
  pendiente sin usar producción.
- Se recompiló Android para QA, se ejecutó la batería instrumentada segura únicamente
  en `emulator-5554` y terminó sin fallos: variante normal **117/117 finalizados**,
  **114 PASS + 3 assumptions**; variante `offlinecheck` **116/116 finalizados**,
  **113 PASS + 3 assumptions**.
- Se ejecutaron `testDebugUnitTest` (**95/95 PASS**), compilación de pruebas Android,
  `lintDebug` y `git diff --check`; los warnings de lint restantes son no bloqueantes.
- Se verificó la firma de la release local: paquete correcto, `versionCode 77`,
  `debuggable=false`, certificado oficial y SHA consistente.
- Se reprodujo el bloqueo del Samsung de forma no destructiva: la instalación local
  es debug con otro certificado, por lo que Android rechaza la actualización release.
- Se implementó y probó la revocación remota de sesión: `POST /api/v1/auth/logout`
  elimina el token actual y Android limpia siempre su sesión local aun con error de red
  o token ya revocado.
- La regresión Android añadida para ACK→interrupción se ejecutó de nuevo después de
  instalar únicamente el APK de pruebas en el emulador: `CatalogSyncIntegrationTest`,
  **16/16 PASS**. La aserción acepta el incremento interno de reintentos que ocurre
  durante el manejo de la excepción; no cambia la garantía de UUID/replay.

## Trabajo que aún falta

Lo siguiente es el backlog real, en orden operativo. No debe marcarse como terminado
por inferencia a partir de tests parciales:

1. Publicar, con autorización explícita, los commits locales backend y Android; antes
   revisar los diffs, el conteo exacto frente a los remotos y mantener el
   backup/controles de producción establecidos. Este corte no publicó nada.
2. Crear una nueva release Android con `versionCode` mayor que 77 que incluya el
   updater persistente, el bloqueo de debug contra producción, PDF y logout; verificar
   firma, SHA, manifiesto y URL antes de publicarla.
3. Resolver el Samsung: comprobar que no haya outbox local pendiente, obtener
   autorización específica, desinstalar solo `com.bsolutions.micatalogo` e instalar la
   release oficial. Después validar login, datos del servidor y updater; no instalar
   debug en el teléfono.
4. Completar la matriz positiva verbo × ruta, incluyendo recursos anidados, archivos,
   capacidades y cada rol, además de las fronteras ya probadas.
5. Decidir F-036 (método/movimiento contable del reembolso pagado) y F-038
   (conversión/prorrateo del costo aterrizado internacional), implementar regresiones
   y volver a validar FIFO, margen, caja, replay y cierre.
6. Ejecutar pruebas offline físicas: caída real de red, kill/restart, reconnect,
   interrupción de actualización y replay/devoluciones en un dispositivo release.
7. Completar auditoría visual/UI Web y Android por pantalla, rol y tamaño: carga,
   vacío, error, rotación, scroll, modales, responsive y paridad con Puntto.
8. Validar integraciones externas y hardware: compartir PDF/WhatsApp, cámara/ML Kit,
   Bluetooth/impresión 58/80 mm, R2/colas y permisos modernos.
9. Reducir la deuda de formato global (`vendor/bin/pint --test` aún reporta 37
   archivos), sin reformatear masivamente cambios ajenos sin revisar el diff.
10. Ampliar las pruebas Compose para Finanzas y estados globales de error/offline,
    incluida la decisión explícita sobre si se debe mostrar el último resumen o un
    estado de datos no disponibles cuando falla una recarga; ya existe cobertura
    directa 2/2 PASS para DTOs nulos.
11. Solo después de lo anterior emitir `READY`; mientras exista cualquiera de estos
    bloqueos el estado correcto sigue siendo `NOT_READY`.

## Secuencia recomendada para la siguiente IA

1. Leer `AGENTS.md`, `bsolutions-infra/SKILL.md` y
   `micatalogo-android-release/SKILL.md`; leer este documento y los reportes QA.
2. Ejecutar `git status --short --branch` en ambos repositorios y preservar todos
   los no rastreados; no hacer reset ni limpiar.
3. Repetir primero el verificador Samsung en modo lectura. Si el usuario autoriza la
   transición, revisar outbox local, documentar estado, desinstalar solo el paquete e
   instalar la release oficial; nunca ejecutar eso automáticamente.
4. Antes de publicar una nueva APK, obtener el `version_code` público, incrementar
   versión, ejecutar tests, firmar con la keystore oficial y verificar paquete,
   certificado, SHA, tamaño, `debuggable=false` y manifiesto.
5. Reejecutar baseline backend en SQLite/MariaDB QA y la batería Android solo con
   `emulator-5554`; usar los fixtures live locales para importador/POS.
6. Atacar los pendientes P1 del gate en el orden: decisión financiera F-036/F-038,
   matriz positiva, actualización física, offline físico y servicios/hardware.
7. Solo declarar `READY` cuando cada requisito tenga evidencia directa. Si faltan
   autorización, hardware o decisión de negocio, dejar `NOT_READY` y documentar el
   bloqueo; no disfrazarlo como PASS.

## Commits locales de este corte

- Backend: `734b169` (`fix: revoke API token on mobile logout`) y documentación QA
  posterior. El checkout está adelantado frente a `origin/master`; revisar la lista
  completa con `git log` y el `HEAD` actual antes de elegir qué publicar.
- Android: `7ea2916` (updater pendiente persistente), `c4aea97` (debug fuera del
  updater de producción), `7c32766` (PDF), `5d57421` (logout con revocación),
  `de03913` (replay ACK/interrupción) y `e50e5ff` (estados financieros sin datos).
  El checkout está 4 commits adelantado frente a `origin/main`.
- La documentación de la reejecución live anterior permanece en el historial; no se
  hizo push de estos commits.
- Ninguna clave, contraseña, token real ni archivo privado forma parte de este traspaso.
