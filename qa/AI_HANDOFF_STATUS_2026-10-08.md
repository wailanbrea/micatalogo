# Traspaso QA de MiCatalogo — estado para otra IA

Fecha del corte: 2026-10-08. Este documento es una fotografía operativa del checkout
actual y no reemplaza `AGENTS.md` ni las skills obligatorias.

## Estado ejecutivo

El release gate continúa **NOT_READY**. El baseline local Web/API y Android está
verde en los escenarios ejecutados, pero todavía hay bloqueos reales: la release no
puede instalarse sobre el Samsung porque conserva una APK debug con otro certificado,
la corrección del updater aún no está publicada en una nueva APK, y permanecen casos
de negocio, visuales, hardware, offline físico y cobertura positiva completa sin
cerrar.

No se hizo publicación, push, instalación ni desinstalación en el Samsung durante
este corte. No se escribieron datos de producción.

## Repositorios y estado Git

### Backend/Web/API

- Ruta: `C:\xampp\php\www\MiCatalogo`
- Rama: `master`, **7 commits adelantados de `origin/master`**.
- Último commit local: `8e64f63 docs: record isolated live Android QA rerun`.
- Commits QA recientes: `6866079`, `7185933`, `5a6df74`, `d22aa60`, `09e7f0f`, `e3d6085`.
- Cambios rastreados: limpios después del commit.
- No tocar ni borrar los no rastreados existentes: `.github/`, `.playwright-cli/`, `output/`.

### Android

- Ruta real activa: `C:\Users\waila\AndroidStudioProjects\micatalogowebApp`.
- La skill histórica menciona `BSPOS-MiCatalogo`; en esta máquina el checkout vigente
  es `micatalogowebApp`. No crear ni cambiar de proyecto sin comprobar Git.
- Rama: `main`, **1 commit adelantado de `origin/main`**.
- Último commit local: `7c32766 test: verify invoice PDF generation and pagination`.
- Cambios funcionales clave: `7ea2916` updater pendiente persistente y `c4aea97`
  bloqueo del updater de producción para builds debug.
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
- Suite instrumentada normal: **116 casos**, **113 PASS**, **3 assumptions** por
  fixtures live no suministrados en la batería completa.
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

- Suite completa: **494 tests / 5.069 assertions PASS** en SQLite y MariaDB QA
  aislada.
- Benchmark de catálogo de 1.500 productos: máximo 17 consultas; p50/p95 más
  reciente SQLite `343.59/350.94 ms`, MariaDB QA `332.64/355.36 ms`, memoria
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

## Commit que contiene este traspaso

Este archivo se añadirá junto con la corrección de métricas obsoletas en los reportes
QA. La documentación de la reejecución live anterior está en el commit local
`8e64f63`; el siguiente commit de documentación debe revisarse antes de cualquier
push. Ninguna clave, contraseña, token real ni archivo privado forma parte de este
traspaso.
