# Hallazgos QA — corte 2026-10-08

Fecha: 2026-10-08  
HEAD Web/API: `32450ffc19380e1bea0ddc132c553835bdf9df1c`  
HEAD Android: `7ea2916`

## F-001 — Lint Android bloqueaba la validación estática

- **Prioridad:** P1 de calidad de build.
- **Síntoma:** `lintDebug` reportaba 4 errores: lectura de dispositivos Bluetooth sin comprobación de `BLUETOOTH_CONNECT`, uso no declarado de `ImageProxy.image` y contenido de `AnimatedContent` sin consumir el estado.
- **Corrección aplicada:** comprobación explícita del permiso antes de leer impresoras vinculadas; opt-in AndroidX para CameraX; el contenido animado ahora usa la ruta como clave de composición.
- **Regresión:** `:app:compileDebugKotlin`, `:app:lintDebug` y `:app:testDebugUnitTest`.
- **Estado:** `FIXED_PASS` — lint PASS; 94/94 unit tests PASS.
- **Archivos:** `C:\Users\waila\AndroidStudioProjects\micatalogowebApp\app\src\main\java\com\example\bspos\presentation\printer\BluetoothPrinterViewModel.kt`, `...\presentation\pos\PosScreen.kt`, `...\presentation\main\BSPOSMainScreen.kt`.

## F-002 — Permisos de operaciones móviles requieren prueba por rol

- **Prioridad:** P1 de autorización.
- **Observación:** `/api/v1/shops/{shop}/mobile-operations` no declara un middleware `menu:*` en la ruta. La autorización real ocurre en `MobileOperationService`: cambios de producto/inventario exigen la policy `update` de la tienda y las devoluciones exigen el menú `returns`.
- **Riesgo residual:** la matriz completa verbo × actor × operación todavía no está cerrada; ya existe cobertura directa para owner/seller/manager/accountant y ambas bases QA, además de los endpoints administrativos globales.
- **Estado:** `FIXED_PASS` para owner/seller/manager/accountant en las operaciones críticas y `FIXED_PASS` para el bloqueo explícito del admin global en la mutación móvil de una tienda; decidir si el admin debe operar una tienda por el canal móvil o solo por `/admin`.
- **Evidencia:** `tests/Feature/MobileOperationTest.php`, `SellerNavigationPermissionsTest.php`, `ApiAuthenticationTest.php` y `RouteProtectionInvariantTest.php`: 479 tests y 4.939 assertions PASS en SQLite y MariaDB QA; vendedor bloqueado para producto/inventario, manager autorizado para reposición, accountant bloqueado sin mutación, devolución autorizada por menú, admin global aislado en `/api/v1/admin/shops`, admin rechazado en `mobile-operations`, guard de rutas mutantes sin frontera de autorización y conversiones de cotización/pedido sin pérdida de centavos.
- **Acción pendiente:** ampliar casos ACL a cada verbo/ruta sensible, `product_archive`, `adjustment`, `open_bottle`, recepción/importación/cobro/cierre y obtener decisión de producto para cualquier operación móvil del admin.

## F-003 — Conversión a float en límites de entrada financiera

- **Prioridad:** P1 de precisión.
- **Observación:** algunos controladores reciben decimales validados y los convierten a `float` antes de llamar a servicios que persisten centavos (`PosSaleController`, `InventoryService`, `MobileOperationService`). `Money::toCents` y los tests actuales reducen el riesgo, pero el estándar del plan exige conservar exactitud en toda mutación.
- **Corrección aplicada:** los límites de reposición, ventas POS, descuentos e impuestos conservan `string|int|float` hasta normalizar con `Money::toCents`; FIFO y reglas automáticas operan con enteros de centavos. Se eliminaron conversiones prematuras a `float` en controladores y servicios de compras/inventario.
- **Regresión:** `FifoPricingTest`: costo `249.99`, lote de 2 unidades, venta FIFO y carrito con descuentos/impuestos de centavos exactos.
- **Resultado:** suite SQLite **479 passed, 4.939 assertions**; suite MariaDB QA aislada **479 passed, 4.939 assertions**.
- **Estado:** `FIXED_PASS`; los reportes/presentación continúan usando decimales únicamente fuera de la frontera de mutación.

## F-004 — Entorno QA no aislado para MariaDB, colas, R2, WhatsApp y hardware

- **Prioridad:** P1 de release.
- **Estado:** `BLOCKED_ENV` para colas, R2, WhatsApp y hardware externos; MariaDB QA local ya está disponible.
- **Impacto:** no permite aprobar todavía recepción/importación/cobro/cierre concurrentes, offline/replay interplataforma, archivos reales, impresión, cámara ni recuperación de actualización en dispositivo.
- **Regla:** no se utilizará VPS/producción para fabricar fixtures ni para validar escrituras.

## F-005 — Release física 1.0.75 aún no validada en Samsung

- **Prioridad:** P1 de verificación Android.
- **Estado:** `NOT_RUN` para la release 1.0.75.
- **Evidencia:** el emulador tiene debug `versionName 1.0.75` / `versionCode 76` para la batería instrumentada. La release firmada no se instaló ni se validó en el Samsung. Una ejecución automática de `connectedDebugAndroidTest` detectó ambos dispositivos y fue detenida antes de ejecutar la batería; la verificación posterior del Samsung debe resolver el estado de su paquete antes de cualquier instalación. No se hizo `pm clear` ni se tocaron datos de negocio.

## F-006 — Artefacto release stale en el checkout Android

- **Prioridad:** P1 de integridad de release.
- **Síntoma:** el primer ensamblado incremental dejó una APK cuyo binario no coincidía con el metadata de salida; el metadata declaraba 1.0.75/76, pero la APK física reportaba una versión anterior.
- **Corrección aplicada:** compilación limpia del directorio de build y verificación independiente con `aapt2`/`apksigner` mediante el flujo de firma existente.
- **Resultado:** `com.bsolutions.micatalogo`, 1.0.75/versionCode 76, `debuggable=false`, certificado SHA-256 `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f` y SHA de APK local firmada actual `e32a55c4a04922f42eaa53baba57b353e2ec7af324ef7fb32e4a2b68298c4791`.
- **Estado:** `FIXED_PASS` para el artefacto local; queda pendiente comprobar instalación/update en dispositivos sin desinstalar ni borrar datos.

## F-007 — Harness de pruebas bloqueaba MariaDB aunque la base QA esté aislada

- **Prioridad:** P1 de cobertura.
- **Preparación:** se creó la base local aislada `micatalogo_qa_20261008` y se aplicaron todas las migraciones sin tocar `micatalogo` ni el VPS.
- **Síntoma inicial:** la suite completa contra MariaDB terminaba con 442 fallos y 9 pases porque `tests/TestCase.php` rechazaba todo lo que no fuera SQLite en memoria.
- **Corrección aplicada:** el arnés acepta MariaDB solo con `APP_ENV=testing`, `QA_ALLOW_MYSQL=1` y una base cuyo nombre comience por `micatalogo_qa_`; se añadió configuración QA separada, se corrigieron aserciones de tipos devueltas por `SUM()` y se concedieron al usuario de aplicación privilegios únicamente sobre `micatalogo_qa_20261008`.
- **Resultado:** suite completa en MariaDB QA: **479 passed, 4.939 assertions, 44.48 s**; suite SQLite terminó con **479 passed, 4.939 assertions, 30.08 s**.
- **Estado:** `FIXED_PASS` para cobertura funcional sobre MariaDB aislada. Queda pendiente estrés multi-proceso y servicios externos.

## F-008 — Pruebas instrumentadas Android desalineadas con el contrato vigente

- **Prioridad:** P1 de regresión Android.
- **Síntomas iniciales:** las pruebas de migración no incluían los pasos 20→25; el outbox esperaba guardar una respuesta que el contrato actual no persiste; las pruebas de POS/caja usaban semántica y comportamiento locales anteriores.
- **Corrección aplicada:** se completó la cadena de migraciones hasta la versión 25 en los fixtures; se verificó el estado/payload del outbox en lugar de un campo no garantizado; se alinearon las pruebas de venta con la caja remota y la prueba de scroll con el índice de la grilla.
- **Regresión:** `compileDebugAndroidTestKotlin`, `assembleDebugAndroidTest` y ejecución completa en emulador.
- **Resultado inicial:** `OK (108 tests)` con un caso de importación omitido por falta de fixture.
- **Corrección adicional:** se levantó un Laravel QA local sobre MariaDB aislada, se usó `adb reverse` solo hacia `127.0.0.1:8893`, y se corrigió el modal para mostrar hoja/fila y conservar los encabezados originales al mapear columnas.
- **Regresión final:** `OK (112 tests)` en el emulador aislado (`109` ejecutados PASS y `3` omitidos por assumption cuando no se levantan fixtures live); incluye replay de pagos 401/426/429/500, bloqueos recuperables 403/409/422, no-replay de venta pendiente tras revocación de permisos y conservación de tienda original en el outbox.
- **E2E live aislado posterior:** `InventoryImportLiveIntegrationTest` `OK (1 test)` con SQLite temporal, servidor `127.0.0.1:8893` y `adb reverse`; preview de XLSX con encabezados en fila 8, mapeo visual, confirmación de 2 productos y repetición idempotente PASS.
- **E2E live POS aislado posterior:** `PosSaleLiveIntegrationTest` `OK (2 tests)` con SQLite temporal, servidor `127.0.0.1:8893` y `adb reverse`; dos ventas de contado y una venta a crédito se crearon offline en orden, cliente y abono por transferencia se sincronizaron, y una venta de decant redujo la botella fuente de 100 a 90 ml y el decant de 10 a 9 unidades. Las cuatro operaciones recibieron ACK remoto y el replay posterior no duplicó facturas.
- **Estado:** `FIXED_PASS`; no se utilizó producción.

## F-009 — Concurrencia de mutaciones de inventario y recepción

- **Prioridad:** P1 de integridad de inventario y contabilidad.
- **Preparación:** `qa/concurrency_probe.php` creó un fixture efímero en `micatalogo_qa_20261008` y lanzó dos procesos PHP independientes contra el mismo producto con una sola unidad disponible.
- **Resultado:** `1` venta aceptada, `1` rechazo `409` por stock insuficiente, stock final `0`, exactamente `1` movimiento de venta y `1` factura.
- **Limpieza:** la base QA fue reconstruida con sus migraciones después de los probes; no se conservaron fixtures financieros.
- **Estado:** `FIXED_PASS` para venta/stock, recepción, cobro de cliente, movimiento de caja, importación y cierre diario concurrentes.

## F-011 — Replay financiero concurrente podía terminar en error de base de datos

- **Prioridad:** P1 de integridad financiera y operación offline.
- **Síntoma reproducido:** con ocho procesos y el mismo UUID, un cobro terminaba en `DeadlockException` y un movimiento de caja en `UniqueConstraintViolationException`, aunque el resultado final quedara único.
- **Corrección aplicada:** cobros bloquean la tienda antes de consultar la idempotencia y movimientos de caja consultan el UUID después de bloquear la sesión; la carrera residual entre sesiones convierte la clave única en replay válido.
- **Regresión:** `qa/financial_concurrency_probe.php` — 8/8 cobros exitosos con 1 entrada, 1 abono y saldo RD$50; 8/8 movimientos exitosos con 1 movimiento de RD$50; recepción concurrente 1/6 y un solo lote; importación 8/8 con un solo producto; cierre 8/8 con un solo cierre.
- **Estado:** `FIXED_PASS` en MariaDB QA.

## F-012 — Factory de productos no respetaba la unicidad real bajo MariaDB

- **Prioridad:** P1 de confiabilidad del baseline QA.
- **Síntoma:** MariaDB falló una prueba por colisión aleatoria de `products(shop_id, slug)`; SQLite no la reprodujo en esa corrida.
- **Corrección aplicada:** `ProductFactory` usa slugs únicos para respetar la restricción real de producción.
- **Regresión:** suites completas SQLite y MariaDB: 479/479 PASS y 4.939 assertions en cada motor.
- **Estado:** `FIXED_PASS`; es una corrección del arnés de fixtures, no una mutación de datos comerciales.

## F-013 — Cierre diario concurrente podía chocar con la unicidad tienda/fecha

- **Prioridad:** P1 de integridad financiera y resiliencia de reintentos.
- **Síntoma reproducido:** ocho procesos cerrando la misma tienda y fecha produjeron seis respuestas válidas y dos `UniqueConstraintViolationException` (`1062`), aunque la tabla terminara con un solo cierre.
- **Causa raíz:** `firstOrNew` se ejecutaba sin serializar la tienda; además, la comparación exacta de fecha podía no encontrar un valor almacenado como datetime en SQLite.
- **Corrección aplicada:** `DailyCloseService` bloquea la tienda dentro de la transacción antes de recalcular y buscar el cierre por `whereDate`, conservando el conflicto explícito si el arqueo repetido es distinto.
- **Regresión:** replay secuencial/conflicto en `FinancialIdempotencyHardenedTest` (9/9, 31 assertions); probe MariaDB `daily_close` con 8/8 respuestas válidas y un solo cierre.
- **Estado:** `FIXED_PASS` en SQLite y MariaDB QA aislada.

## F-014 — La matriz de actores necesitaba reiniciar el guard entre requests

- **Síntoma:** una matriz secuencial de requests podía reutilizar la identidad autenticada previa durante la misma prueba, debilitando la evidencia de aislamiento por actor.
- **Causa:** el guard de Sanctum quedaba resuelto en memoria entre assertions del test.
- **Corrección:** `RouteActorTenantMatrixTest` llama a `auth()->forgetGuards()` antes de cada request con token y verifica owner/manager/seller/accountant/admin/anónimo entre dos tiendas.
- **Regresión:** matriz de alto riesgo **148 assertions PASS** en SQLite y MariaDB QA; las 59 mutaciones web de tienda ahora se ejecutan contra un propietario de otra tienda y las 10 mutaciones administrativas globales se verifican contra usuario normal/admin, además de comprobarse la autorización declarada. `OperationalRolePositiveMatrixTest` añade 17 assertions positivas para owner, manager, vendedor con `day_close` delegado y contador solo lectura, incluida la comisión registrada del vendedor. `OperationalTeamJourneyTest` añade 42 assertions sobre equipo, permisos de menú, comisión, límites del contador y bypass API. La suite completa posterior quedó en 479/479 PASS y 4.939 assertions por motor.
- **Estado:** `FIXED_PASS` para la matriz cubierta; cobertura verbo × ruta completa permanece abierta.

## F-015 — Benchmark de catálogo de 1.500 productos

- **Prioridad:** P2 de rendimiento y experiencia de navegación.
- **Cobertura:** lectura autenticada de catálogo con 1.500 productos e inventario eager-loaded, repetida cinco veces en el entorno local aislado.
- **Resultado:** cinco respuestas `200`, 1.500 productos por respuesta, medición más reciente p50 343.59 ms/p95 350.94 ms en SQLite y p50 332.64 ms/p95 355.36 ms en MariaDB QA, máximo 17 consultas por ejecución y pico de memoria 148/148 MB.
- **Estado:** `PASS` como benchmark local acotado; no sustituye medición con red, dispositivo móvil, caché ni carga concurrente real.

## F-016 — Arranque Android sin conectividad

- **Prioridad:** P1 de resiliencia operativa.
- **Prueba:** se activó modo avión únicamente en `emulator-5554`, se inició `com.bsolutions.micatalogo` y se observó el proceso durante el smoke; después se restauró la conectividad.
- **Resultado:** proceso activo, sin firma `FATAL EXCEPTION`/`AndroidRuntime`; `airplane_mode` volvió a `0`. No se borraron datos ni se usó el dispositivo físico.
- **Estado:** `PASS` acotado; no cierra el E2E de venta offline, reconexión y ACK único.

## F-019 — Venta POS offline con reinicio y ACK idempotente

- **Prioridad:** P1 de resiliencia operativa.
- **Cobertura:** dos ventas de contado y una venta a crédito ordenadas, cliente local/remoto, abono por transferencia, inventario local válido, outbox durable, cierre/reapertura de Room, sincronización contra Laravel QA aislado y replay posterior.
- **Resultado:** `PASS` live en `PosSaleLiveIntegrationTest`: `3 sent`, `0 blocked`, pago pendiente `0`; los estados locales pasaron de `PENDING` a `SENT`, el servidor registró 3 facturas/3 uploads/3 movimientos en el flujo contado/crédito, 1 cliente con saldo final RD$0.00 y la repetición no creó registros adicionales.
- **Límite:** no sustituye interrupción física de red, conflicto/401/429 durante una venta ni E2E Web↔Android amplio.
- **Estado:** `FIXED_PASS` para este camino; escenarios OFF adicionales permanecen abiertos.

## F-020 — FIN-001 integrado de venta mixta, cartera, gasto y cierre

- **Prioridad:** P1 de conciliación financiera.
- **Cobertura:** una venta de 3 unidades a RD$250 con RD$400 cash, RD$150 tarjeta y RD$200 crédito; costo FIFO RD$300; abono cash RD$120; gasto devengado RD$90 con RD$40 pagados; cierre diario y arqueo.
- **Resultado:** `FinancialEndToEndTest` pasó en SQLite y MariaDB QA con 30 assertions: ventas netas RD$750, COGS RD$300, ganancia bruta RD$450, gasto operativo RD$90, ganancia operativa RD$360, cartera RD$80, efectivo esperado RD$480, pendiente de gasto RD$50 y diferencia de cierre RD$0.00.
- **Nota:** el `net_cash_flow` global queda en RD$630 porque incluye también la entrada de tarjeta RD$150; el cierre físico usa exclusivamente efectivo y queda en RD$480.
- **Estado:** `FIXED_PASS`; no se escribieron datos de producción.

## F-022 — E2E Android de venta de decant y consumo de ml compartidos

- **Prioridad:** P1 de inventario y operación offline.
- **Cobertura:** `PosSaleLiveIntegrationTest` creó en Room una botella fuente de 100 ml y una presentación de 10 ml, realizó la venta del decant sin conexión, reinició/sincronizó el outbox y consultó el catálogo remoto del fixture.
- **Resultado:** `PASS` en el emulador `emulator-5554`; la venta pasó de `PENDING` a `SENT`, no quedó bloqueada, el backend confirmó 90 ml disponibles en la botella, 9 unidades de decant y 4 facturas totales del fixture sin duplicados.
- **Límite:** no cubre todavía devoluciones de decant, interrupción física de red ni recuperación de una actualización Android interrumpida.
- **Estado:** `FIXED_PASS` para venta y sincronización de decant; los escenarios offline adicionales permanecen abiertos.

## F-021 — Matriz positiva mínima por rol operativo

- **Prioridad:** P1 de autorización y operación.
- **Cobertura:** `OperationalRolePositiveMatrixTest` usa un shop QA aislado con owner, manager, vendedor y contador; el owner abre caja, manager y vendedor realizan ventas POS autorizadas, el contador consulta finanzas/cierre en modo lectura y el owner ejecuta el cierre diario con el efectivo esperado.
- **Resultado:** la matriz positiva pasa en SQLite y MariaDB QA; dos facturas creadas, stock reducido de 4 a 2 unidades, comisión del vendedor registrada en RD$5.00, consulta de cierre permitida al contador, mutación de cierre del contador rechazada con 403 y cierre del owner con diferencia RD$0.00. La misma prueba se ejecuta como parte de las suites completas.
- **Límite:** cubre una jornada positiva mínima, no la matriz exhaustiva verbo × ruta ni recursos anidados/archivos.
- **Estado:** `FIXED_PASS` para este camino; R-012 permanece abierto por cobertura pendiente.

## F-017 — Mobile operations validaba el payload antes del tenant

- **Prioridad:** P1 de autorización y aislamiento multi-tenant.
- **Síntoma reproducido:** un dueño de otra tienda podía enviar un `mobile-operations` vacío y recibir `422` por el campo `type`, antes de que el servicio comprobara el acceso a la tienda.
- **Corrección aplicada:** `MobileOperationController` comprueba primero `canSellAtShop`; el acceso externo termina en `404` antes de la validación específica y antes de cualquier mutación.
- **Regresión:** `RouteActorTenantMatrixTest` cubre las 35 mutaciones JSON reales de una tienda ajena más la actualización administrativa, ejecuta las 59 mutaciones web de tienda contra un propietario de otra tienda y valida las 10 mutaciones administrativas globales contra usuario normal/admin; `RouteProtectionInvariantTest` verifica además la autorización declarada. La matriz de alto riesgo queda en 148 assertions PASS en SQLite y MariaDB QA al sumar el modelo de módulos móvil. `MobileOperationTest` conserva manager autorizado, contador/admin bloqueados y operaciones financieras/de inventario sin regresión.
- **Estado:** `FIXED_PASS` para las 35 rutas JSON de tienda, la actualización administrativa, las 59 mutaciones web de tienda y las 10 mutaciones administrativas globales cubiertas en sus fronteras; quedan pendientes las acciones positivas por cada rol y la matriz exhaustiva de recursos anidados/archivos.

## F-018 — El modelo de módulos móvil no verificaba el tenant antes de leer

- **Prioridad:** P1 de autorización y aislamiento multi-tenant.
- **Síntoma reproducido:** el endpoint API `/api/v1/shops/{shop}/features/{feature}` evaluaba la visibilidad del módulo sin comprobar primero que el actor pudiera operar la tienda indicada. Un propietario de otra tienda podía consultar un módulo de un tenant ajeno si conocía su identificador público.
- **Corrección aplicada:** `SellerFeatureController::api` valida primero admin, vendedor/propietario autorizado o contador activo y responde `404` antes de evaluar la función.
- **Regresión:** `RouteActorTenantMatrixTest` añadió `ACL-017`; la prueba usa dos propietarios y confirma que el acceso cruzado no alcanza el módulo ni genera lecturas de negocio.
- **Estado:** `FIXED_PASS` en SQLite y MariaDB QA; la matriz verbo × ruta completa permanece abierta para los casos no ejecutados.

## F-023 — Replay de cobro devolvía un timestamp distinto en MariaDB

- **Prioridad:** P1 de idempotencia financiera y sincronización offline.
- **Síntoma reproducido:** el segundo request con el mismo `client_transaction_uuid` devolvía los mismos importes y saldos, pero `server_timestamp` podía diferir por la precisión de `created_at` entre la respuesta inicial y la lectura posterior de MariaDB. El contrato `assertExactJson` fallaba aunque no se duplicara el cobro.
- **Causa raíz:** la respuesta original usaba `now()` y el replay usaba el timestamp persistido de la entrada contable; MariaDB normaliza la precisión temporal al guardar.
- **Corrección aplicada:** `PaymentService::recordCustomerDebtPayment` usa el `created_at` persistido de la entrada tanto para la respuesta inicial como para el replay, conservando el fallback del reloj solo si faltara el valor.
- **Regresión:** `ApiCustomerAccountTest` pasa 5/5 con 32 assertions en SQLite y MariaDB QA; las suites completas posteriores quedaron en 479/479 PASS y 4.939 assertions por motor.
- **Estado:** `FIXED_PASS`; no se escribieron datos de producción.

## F-010 — El runner conectado podía seleccionar el dispositivo físico

- **Prioridad:** P1 de seguridad del proceso QA.
- **Síntoma:** `connectedDebugAndroidTest` enumeró el Samsung y el emulador porque ambos estaban conectados. La orden fue detenida inmediatamente y las corridas posteriores se hicieron con `adb -s emulator-5554`; no se ejecutará otra batería global mientras haya más de un destino ambiguo.
- **Corrección preventiva:** se agregó `qa/run_android_emulator_tests.ps1`. Exige un serial explícito, comprueba `ro.kernel.qemu=1` y rechaza cualquier dispositivo físico antes de iniciar tests.
- **Estado:** `FIXED_PASS` para el mecanismo de ejecución segura; la reinstalación o validación del paquete release en el Samsung queda `REQUIERE_DECISION` y no debe hacerse automáticamente.

## F-024 — Jornada E2E-03 compra, cotización, crédito, cobro, gasto y cierre

- **Prioridad:** P1 de operación financiera e inventario.
- **Cobertura:** `OperationalJourneyTest` ejecuta en una sola tienda QA la recepción de 2 unidades a costo RD$100, una cotización convertida a venta por RD$250, una segunda venta a crédito, un abono de RD$100, un gasto pagado de RD$30, consulta de resumen financiero y cierre diario.
- **Resultado:** 43 assertions PASS en SQLite y MariaDB QA. Se verificaron 2 facturas, 1 lote FIFO, stock 2→0, cartera final RD$150, ventas netas RD$500, COGS RD$200, ganancia bruta RD$300, efectivo esperado RD$320 y diferencia RD$0.00. La base QA quedó en cero para usuarios, tiendas, productos, facturas, cierres, sesiones, movimientos, clientes, compras y cotizaciones.
- **Estado:** `FIXED_PASS` para la jornada aislada; no sustituye el recorrido visual completo, E2E Web↔Android ni las rutas externas pendientes. No se escribió en producción.

## F-025 — Jornada E2E-01 de vitrina pública y pedido WhatsApp

- **Prioridad:** P1 de comercio público, caja e idempotencia.
- **Cobertura:** `OperationalPublicOrderJourneyTest` registra un propietario QA, aplica el preset de negocio, publica un producto con inventario, visita la vitrina y el detalle público, crea un pedido con enlace firmado y URL de WhatsApp, lo confirma desde el panel, genera factura pagada, descuenta stock y cierra caja.
- **Resultado:** 27 assertions PASS en SQLite y MariaDB QA. Se verificaron stock 3→1, dos unidades vendidas, caja esperada RD$600 y diferencia RD$0.00. La confirmación repetida conservó una sola factura y no volvió a descontar inventario.
- **Estado:** `FIXED_PASS` para la jornada Web/API aislada; no sustituye el recorrido de Android, WhatsApp real ni hardware. No se escribió en producción.

## F-026 — Contador podía mutar el cierre diario por API

- **Prioridad:** P1 de autorización financiera.
- **Síntoma reproducido:** un usuario con rol `accountant` podía enviar `POST /api/v1/shops/{shop}/finance/day-close` porque el controlador usaba la misma condición de lectura financiera para consultar y guardar el cierre.
- **Causa raíz:** no había una frontera separada entre `GET` de consulta y `POST` de mutación; `isActiveShopAccountant` satisfacía ambas rutas.
- **Corrección aplicada:** `FinanceReportController` separa autorización de lectura y escritura. El contador conserva `GET`, mientras que el `POST` exige owner/manager o vendedor con `day_close` delegado explícitamente.
- **Regresión:** `OperationalRolePositiveMatrixTest` y `FinancialApiContractTest` pasan en SQLite y MariaDB QA; el contador recibe 403 al mutar, el owner cierra con diferencia cero y las funciones financieras existentes no regresan.
- **Estado:** `FIXED_PASS`; no se escribió en producción.

## F-027 — POS no debía dejar datos parciales ante un fallo posterior a la factura

- **Prioridad:** P1 de integridad financiera e inventario.
- **Cobertura:** se inyectó un fallo en `PaymentService::processInvoicePayments` después de que el POS había creado el upload, la factura y los movimientos de inventario.
- **Resultado:** `OperationalSaleSafetyTest` pasó 1/1 con 9 assertions en SQLite y MariaDB QA; la transacción revirtió upload, factura, movimiento, caja y stock quedó sin cambios.
- **Estado:** `FIXED_PASS`; no se escribió en producción.

## F-028 — Jornada E2E de devolución de decant y restauración de ml

- **Prioridad:** P1 de inventario y devoluciones.
- **Cobertura:** `OperationalDecantReturnJourneyTest` vende 2 decants desde una botella fuente, verifica el consumo de ml y luego devuelve una unidad por `mobile-operations` con reintegro.
- **Resultado:** 16 assertions PASS en SQLite y MariaDB QA; se restauran 5 ml, una unidad de decant y el costo FIFO capturado. El replay conserva una sola devolución.
- **Estado:** `FIXED_PASS`; no se escribió en producción.

## F-029 — Jornada E2E de equipo, comisión y contador solo lectura

- **Prioridad:** P1 de roles, equipo y finanzas.
- **Cobertura:** `OperationalTeamJourneyTest` crea y asigna un vendedor desde el owner, actualiza permisos de menús, verifica el acceso del manager al equipo, realiza una venta POS como vendedor y consulta finanzas como contador.
- **Resultado:** 22 assertions PASS en SQLite y MariaDB QA; la comisión del vendedor quedó en RD$20.00, el stock se redujo, el contador pudo leer finanzas y el vendedor recibió 403 al mutar finanzas, cierre diario o administración de vendedores.
- **Estado:** `FIXED_PASS`; no se escribió en producción.

## F-030 — Bypass API de menús delegados y contador de solo lectura

- **Prioridad:** P1 de autorización y aislamiento de funciones.
- **Cobertura:** `OperationalTeamJourneyTest` prueba requests directos de un vendedor limitado hacia cotizaciones, pedidos, compras, reportes, decants, caja, gastos, importación, operaciones de producto y administración de vendedores; también prueba lectura y mutación del contador.
- **Resultado:** 20 assertions PASS en SQLite y MariaDB QA; el vendedor recibe rechazo en cada función no asignada, el contador puede leer resumen/cierre pero recibe 403 al abrir caja, registrar gastos, cerrar el día o administrar vendedores, y no se alteran producto, stock ni facturas.
- **Estado:** `FIXED_PASS`; no se escribió en producción.

## F-031 — Matriz de tipos de negocio y planes

- **Prioridad:** P1 de capacidades y presentación contextual.
- **Cobertura:** `BusinessPresentationTest` recorrió cada tipo definido en `config/business-types.php` —23 tipos actualmente— bajo Gratis, Básico y Pro.
- **Resultado:** 1.906 assertions PASS en SQLite y MariaDB QA; etiquetas, categorías, campos, capacidades, inventario, crédito, mayorista y presentación se mantienen coherentes; decants queda habilitado únicamente para perfumería Pro. El documento maestro hablaba de 21 tipos, por lo que se registró el número vivo de configuración.
- **Estado:** `FIXED_PASS` para la matriz declarativa y el caso Pro multi-tienda; la validación visual Web↔Android permanece pendiente.

## F-032 — Venta offline bloqueada tras revocación de permisos no debe reintentarse

- **Prioridad:** P1 de autorización y resiliencia offline.
- **Cobertura:** `CatalogSyncIntegrationTest.permissionRevocationBlocksQueuedSaleAndDoesNotReplay` sincroniza el catálogo, encola una venta, recibe 403 por permiso revocado y después repite la sincronización con 200.
- **Resultado:** la operación queda `BLOCKED`, no se marca como enviada y no vuelve a transmitirse cuando la respuesta posterior es 200; la corrida dirigida pasó en `emulator-5554` y quedó incluida en la batería completa `OK (112 tests; 109 PASS y 3 omitidos por assumption)`.
- **Estado:** `FIXED_PASS`; no se usó producción.

## F-033 — El outbox conserva la tienda original al cambiar la tienda activa

- **Prioridad:** P1 de aislamiento multi-tienda y resiliencia offline.
- **Cobertura:** `CatalogSyncIntegrationTest.pendingSalesStayBoundToTheirOriginalShopWhenTheActiveShopChanges` sincroniza primero `shop-a` y `shop-b`, encola una venta para cada una, cambia el contexto activo y ejecuta `syncDueSales`.
- **Resultado:** 15/15 assertions PASS en la prueba dirigida y batería Android completa `OK (112 tests; 109 PASS y 3 omitidos por assumption)`; ambas ventas se envían a su `shop_id` original y quedan `SENT`, sin cruzar catálogos.
- **Estado:** `FIXED_PASS`; no se usó producción.

## F-034 — Multi-tienda Pro conserva la presentación vertical y el aislamiento de catálogo

- **Prioridad:** P1 de capacidades, planes y tenant boundary.
- **Cobertura:** `BusinessPresentationTest` crea un owner Pro con una tienda de perfumería y otra de ropa, verifica el límite de 3 tiendas, lista ambas desde `/api/v1/shops` y consulta cada catálogo.
- **Resultado:** 12 assertions adicionales PASS en SQLite y MariaDB QA; cada tienda conserva su arquetipo y producto, el catálogo no filtra datos de la otra y las capacidades siguen siendo las de su vertical.
- **Estado:** `FIXED_PASS`; no se usó producción.

## F-035 — Jornada multi-tienda completa mantiene vendedor, stock y factura en su tenant

- **Prioridad:** P1 de aislamiento de tenant y operación comercial.
- **Cobertura:** `OperationalMultiShopJourneyTest` crea un owner Pro con perfumería y ropa, asigna un vendedor solo a perfumería, consulta tiendas/catálogos y ejecuta venta en ambos contextos.
- **Resultado:** 16 assertions PASS en SQLite y MariaDB QA; el vendedor ve y vende únicamente en Tienda A, el acceso y POS de Tienda B devuelven 404, el stock de B permanece intacto y solo A obtiene factura.
- **Estado:** `FIXED_PASS`; no se usó producción.

## F-036 — La devolución de una venta pagada no tiene política de reembolso financiero

- **Prioridad:** P1 de conciliación financiera; `REQUIERE_DECISION`.
- **Reproducción:** `MobileOperationService::refund()` acepta una devolución parcial o total de una venta POS pagada y registra `invoice_returns`/`invoice_return_items`; restaura stock y costo FIFO, y el dashboard descuenta ventas netas, impuestos y COGS. El payload solo contiene producto, cantidad, importe y `restock`; no contiene método de reembolso ni cuenta destino.
- **Resultado observado:** no se crea `invoice_payment` negativo, entrada de cartera ni `cash_movement` de salida. Una devolución de una venta cash puede dejar la caja física y el flujo de efectivo con el ingreso original, aunque el P&L y el inventario ya reflejen la devolución.
- **Por qué no se corrige unilateralmente:** el producto debe escoger entre (A) reembolsar por el mismo método de pago y registrar un movimiento financiero negativo idempotente, o (B) registrar devolución como crédito/ajuste pendiente, con decisión posterior y sin sacar efectivo automáticamente. No existe hoy un campo que permita distinguir cash, tarjeta, transferencia, crédito de tienda o devolución sin restock.
- **Regresión pendiente:** cuando se elija la política, añadir prueba total/parcial, replay, concurrencia y cierre diario; hasta entonces E2E-08 no puede declararse PASS financiero.
- **Estado:** `REQUIERE_DECISION`; no se escribió en producción ni se modificó el código para inventar un reembolso.

## F-037 — Confirmación concurrente de pedido público conserva una sola transacción

- **Prioridad:** P1 de comercio, inventario y caja.
- **Cobertura:** `qa/order_confirmation_concurrency_probe.php` lanzó dos procesos independientes contra el mismo pedido de vitrina en `micatalogo_qa_20261008`, con una caja remota abierta y una unidad disponible.
- **Resultado:** ambos procesos recibieron el mismo número de factura; se creó exactamente una factura, un movimiento de inventario, un movimiento de caja por RD$300.00 y el stock terminó en cero. El pedido quedó `confirmed` con `invoice_id` único.
- **Estado:** `FIXED_PASS` para confirmación concurrente Web/API en el fixture aislado; WhatsApp real y E2E Web↔Android siguen bloqueados por entorno.

## F-038 — Compra internacional conserva logística, pero no calcula costo aterrizado

- **Prioridad:** P1 de inventario, compras y rentabilidad; `REQUIERE_DECISION`.
- **Reproducción:** `qa/international_purchase_audit.php` creó en QA un contenedor USD con tasa 60, flete 120, aduana 80 y 10 unidades a 10.00 por unidad.
- **Resultado observado:** el documento quedó con subtotal/total 100.00 y el lote con costo unitario 10.00 (1.000 centavos); moneda, tasa, flete y aduana se conservaron como campos informativos, sin conversión ni prorrateo en el costo del lote.
- **Impacto:** si el usuario espera costo en moneda base o costo aterrizado, el FIFO, margen y precio automático pueden quedar subestimados. El comportamiento actual solo es correcto si `unit_cost` ya llega convertido y con logística distribuida externamente.
- **Decisión requerida:** (A) exigir que `unit_cost` sea costo final en moneda base y etiquetar la logística como informativa, o (B) convertir con `exchange_rate` y prorratear flete/aduana por una regla explícita (valor, cantidad, peso u otra), persistiendo el costo resultante por lote.
- **Estado:** `REQUIERE_DECISION`; no se inventó una conversión ni se tocó producción.

## F-039 — El POS web bloqueaba el cobro al contado por un campo oculto inválido

- **Prioridad:** P1 de operación comercial y UI web.
- **Reproducción:** en el POS web QA se agregó un producto al carrito, se abrió `Cobrar venta` con `Contado` y se intentó confirmar. El navegador devolvió `An invalid form control with name='' is not focusable` y mantuvo abierto el modal.
- **Causa:** `mixed_credit_amount` tenía `min="0.01"`, valor inicial `0` y seguía participando en la validación HTML aunque el bloque de pago mixto estuviera oculto.
- **Corrección:** el control queda `disabled` mientras `paymentKind !== 'mixed'`; al seleccionar pago mixto vuelve a participar en la validación y conserva su límite máximo.
- **Regresión:** `SellerPosTest` conserva la aserción de la condición de deshabilitado. Smoke Playwright QA repitió agregar producto → abrir cobro → confirmar: factura `FAC-20261008-ZHODFOUD`, stock `12 → 11`, modal cerrado, mensaje de éxito y consola sin errores.
- **Estado:** `FIXED_PASS`; solo se usó la base aislada `micatalogo_qa_20261008`.

## F-040 — Dos formularios de tienda generaban un error Alpine por selectores de horario mal escapados

- **Prioridad:** P1 de navegación y configuración.
- **Reproducción:** el recorrido real del navegador abrió 53 destinos del panel. `Configuración de tienda` y `Crear tienda` respondían 200, pero Livewire/Alpine registraba `SyntaxError: Invalid or unexpected token` al compilar la expresión de copia de horario.
- **Causa:** el atributo HTML `x-data`/`x-on:click` contenía comillas dobles escapadas con barra invertida dentro de un atributo delimitado por comillas dobles; el navegador cortaba la expresión antes de que Alpine la evaluara.
- **Corrección:** los selectores de horario usan valores CSS sin comillas internas (`[data-hours-open=monday]` y su variante dinámica), tanto en el formulario de edición como en la vista de tienda.
- **Regresión:** `SellerShopTest` verifica ambas vistas. En Playwright, el botón `Copiar el lunes de lunes a sábado` propagó `08:00–17:00` a martes y sábado; el segundo recorrido real de 53 destinos terminó con HTTP 200 y cero errores de consola o de página.
- **Estado:** `FIXED_PASS`; solo se usó la base aislada `micatalogo_qa_20261008`.

## Conclusión del corte

## F-041 — Variante offlinecheck rechazaba su propio applicationId en el smoke de contexto

- **Prioridad:** P2 de infraestructura de pruebas; no era un defecto funcional de la aplicación.
- **Síntoma reproducido:** la variante aislada `com.bsolutions.micatalogo.offlinecheck` ejecutaba la batería completa, pero `ExampleInstrumentedTest` esperaba literalmente `com.bsolutions.micatalogo` y marcaba un falso fallo.
- **Corrección aplicada:** el test acepta únicamente `com.bsolutions.micatalogo` o `com.bsolutions.micatalogo.offlinecheck`, manteniendo el rechazo de cualquier ID inesperado.
- **Regresión:** ambas variantes fueron compiladas, instaladas con `adb -s emulator-5554` y terminaron `OK (112 tests; 109 PASS y 3 omitidos por assumption)`.
- **Estado:** `FIXED_PASS`; no se tocó el Samsung, no se limpiaron datos y no se publicó APK.

## F-042 — Asignación de cobros podía convertir devoluciones decimales a float

- **Prioridad:** P1 de precisión financiera.
- **Síntoma:** `PaymentService::recordCustomerDebtPayment()` convertía la suma decimal de `invoice_returns.total` a `float` antes de transformarla a centavos. La frontera contable debe conservar el decimal de la base de datos como texto.
- **Corrección aplicada:** la suma de devoluciones ahora llega directamente a `Money::toCents()` sin pasar por `float`.
- **Regresión:** `FinancialIntegrityTest` crea una devolución de `RD$0.01` sobre una factura de `RD$10.01` y verifica en SQLite y MariaDB QA que el cobro de `RD$10.00` asigna exactamente 1.000 centavos, deja saldo de factura `RD$0.00` y no altera el saldo final del cliente.
- **Estado:** `FIXED_PASS`; no se escribieron datos de producción.

## F-043 — El contador quedaba rotulado como vendedor y no podía abrir Finanzas en la Web

- **Prioridad:** P1 de roles y navegación.
- **Síntoma reproducido:** la cuenta con membresía activa `accountant` veía la etiqueta `Vendedor` y el resumen de ventas del vendedor. El enlace de ganancias respondía 403 porque la ruta estaba agrupada bajo `menu:sales`, aunque el rol tenía `finance` y `reports`.
- **Causa raíz:** la presentación del rol usaba `no administrador = Vendedor`; además, la ruta de negocio compartía el grupo de middleware POS/ventas.
- **Corrección aplicada:** la ruta de negocio ahora exige `can:viewFinance` y `menu:finance` en un grupo independiente; sidebar/header muestran `Contador`; el resumen del contador es de lectura financiera y agrega las ventas de la tienda sin conceder POS ni mutaciones. Además, el módulo de administración de contadores ya no aparece para un contador y las rutas web/API de ese módulo quedan bloqueadas para ese rol; solo el propietario/administrador de tienda puede otorgar o revocar accesos.
- **Regresión:** `SellerNavigationPermissionsTest` pasa con 12 tests y 114 assertions en SQLite y MariaDB QA. Playwright confirmó Contador → Finanzas/Ganancias/Reportes, negocio HTTP 200, y vendedor directo a negocio HTTP 403; las pruebas web/API verifican 403 para `accountant` y 200 para `reports`.
- **Estado:** `FIXED_PASS`; fixture sintético QA eliminado y la base quedó en cero; no se escribió en producción.

## F-044 — Android mostraba acciones de mutación financiera al contador

- **Prioridad:** P1 de roles y permisos en Android.
- **Síntoma reproducido:** aunque el backend ya rechazaba con 403 las mutaciones de gastos y caja para una membresía `accountant`, `FinanceScreen` mostraba el FAB para registrar gastos y los formularios de abrir caja, registrar movimientos y cerrar caja.
- **Causa raíz:** la pantalla no recibía ni aplicaba las capacidades `menu:expenses` y `menu:cash` del usuario conectado; la protección existía en la API, pero la interfaz no reflejaba el modo de solo lectura.
- **Corrección aplicada:** `FinanceScreen` recibe `canMutateExpenses` y `canMutateCash`; el host Android los deriva de `canSeeMenu(...)`. Para el contador se conservan los datos financieros, pero se ocultan el FAB, los formularios y las acciones de caja, mostrando una explicación de solo lectura.
- **Regresión:** `CashContractScreenTest` dirigido en `emulator-5554` pasó 2/2 pruebas: balance esperado y ausencia de `Abrir caja`, `Guardar movimiento` y `Cerrar caja` en modo solo lectura. La batería principal completa posterior terminó `OK (113 tests; 110 PASS y 3 omitidos por assumption)`; la variante offlinecheck conserva su última ejecución completa de 112 casos.
- **Estado:** `FIXED_PASS` para la regresión Android dirigida y la batería debug principal; falta generar una release firmada con un `versionCode` superior al publicado y validar visualmente cada rol en Android/Web antes del release gate.

## F-045 — Pint reporta deuda de formato preexistente en el checkout

- **Prioridad:** P2 de higiene de CI; no se observó cambio funcional ni mutación de datos.
- **Evidencia:** `vendor/bin/pint --test` reportó 37 archivos PHP con fixers pendientes, incluyendo controladores, servicios, modelos, rutas, tests y scripts QA. El build Vite sí terminó correctamente.
- **Decisión de ejecución:** no se ejecutó un reformateo masivo porque el árbol contiene cambios locales de otras tareas y la regla de trabajo exige conservarlos. El workflow CI queda preparado para verificar únicamente los PHP modificados en cada push/PR, pero una publicación de este árbol debe resolver o aceptar explícitamente esa deuda.
- **Estado:** `NOT_RUN` como corrección; no bloquea por sí sola el gate funcional P1, pero mantiene el gate de calidad CI incompleto.

## F-046 — Los endpoints de cotizaciones permitían convertir estados no vigentes

- **Prioridad:** P1 de inventario y operación comercial.
- **Síntoma reproducido:** la interfaz calculaba `can_convert=false` para cotizaciones vencidas o canceladas, pero una petición directa Web/API no repetía esa regla; podía crear la venta y descontar inventario.
- **Corrección aplicada:** `CommercialQuote::isExpired()` centraliza la condición; `SellerCommerceController` y `Api\\V1\\QuoteController` rechazan cotizaciones canceladas o vencidas antes de abrir la transacción de inventario/caja.
- **Regresión:** `SellerCommerceWorkspaceTest` pasó **12/12 tests y 113 assertions** en SQLite y MariaDB QA; prueba vencida/cancelada por ambos canales, stock sin cambios y el detalle Web sin botón de conversión para una cotización cancelada.
- **Estado:** `FIXED_PASS` para la regla de conversión y su presentación Web. `QUOTE-003` permanece sin PASS total hasta completar la validación de PDF/compartir y el recorrido completo de estados.

## F-047 — `unit_cost` usaba división de punto flotante en movimientos

- **Prioridad:** P1 de precisión financiera.
- **Síntoma reproducido:** ventas, combos y devoluciones calculaban el snapshot `unit_cost` con división PHP de punto flotante (`total_cost_cents / (100 * quantity)`), aunque `total_cost_cents` permanecía entero.
- **Corrección aplicada:** `Money::perUnitDecimal()` divide y redondea en centavos enteros; ventas, combos y devoluciones lo usan sin convertir el costo a `float`. `total_cost_cents` continúa siendo la fuente exacta para FIFO y P&L.
- **Regresión:** `FifoPricingTest` + `FinancialIntegrityTest` pasaron **31 tests y 104 assertions** en SQLite y MariaDB QA; además se probaron divisiones no exactas de centavos sin artefactos binarios.
- **Estado:** `FIXED_PASS` para esta frontera de mutación; el caso global `NFR-003` sigue pendiente de la auditoría completa de todas las salidas y entradas financieras.

## F-048 — El impuesto automático del POS calculaba centavos con punto flotante

- **Prioridad:** P1 de precisión financiera.
- **Síntoma reproducido:** el POS calculaba el impuesto automático multiplicando importes en centavos por una tasa decimal de punto flotante. En importes pequeños o tasas con fracciones de centavo esto podía producir redondeos binarios antes de persistir la factura.
- **Corrección aplicada:** `Money` convierte la tasa a puntos base enteros y calcula el impuesto con aritmética entera half-up; `ShopOperationalSettingsService` normaliza la tasa sin pasar por `float`, y `PosSaleController` usa esa ruta para el impuesto automático. La persistencia de `purchase_document_items.unit_cost` también normaliza el costo decimal desde los centavos exactos.
- **Regresión:** `MoneyTest`, `BusinessRemediationTest` y `SellerCommerceWorkspaceTest`: **2 tests dirigidos y 9 assertions** para el caso de impuesto automático, más la frontera de costo de compra, pasan en SQLite y MariaDB QA. Con una tasa del 50% y un subtotal de RD$0.01, la factura conserva impuesto RD$0.01 y total RD$0.02; el costo de compra `125.5` queda persistido como `125.50`.
- **Estado:** `FIXED_PASS` para impuesto automático y normalización de costo en las mutaciones cubiertas. `NFR-003` global sigue `NOT_RUN` porque aún quedan conversiones de presentación/reportes y falta el inventario estático completo de entradas y salidas financieras.

## F-049 — La aprobación Web de precios comparaba snapshots como `float`

- **Prioridad:** P1 de precisión financiera y consistencia Web/API.
- **Síntoma reproducido:** el endpoint Web de aprobación comparaba `pending_price` y `expected_price` como `float`, mientras que la API ya comparaba centavos exactos. Esto dejaba dos contratos de aprobación con reglas numéricas distintas.
- **Corrección aplicada:** la aprobación Web usa `Money::toCents()` para verificar el snapshot pendiente antes de mutar el precio; la regla de negocio queda alineada con la API y el precio aplicado vuelve a normalizarse por centavos.
- **Regresión:** `BusinessRemediationTest` añade el caso de aprobación Web con snapshot decimal `200.10`; el subconjunto dirigido queda en **20 tests y 103 assertions PASS** en SQLite y MariaDB QA. La suite completa posterior queda en **488 tests y 5.001 assertions PASS** por motor.
- **Estado:** `FIXED_PASS` para la aprobación Web/API; `NFR-003` global sigue `NOT_RUN` por conversiones de presentación/reportes fuera de las mutaciones y por faltar el inventario estático exhaustivo.

## F-050 — El cierre diario podía reparsear un importe de presentación como `float`

- **Prioridad:** P1 de precisión financiera y cierre de caja.
- **Síntoma reproducido:** `DailyCloseService::calculate()` exponía valores monetarios como `float` y `close()` volvía a convertir `expected_cash` a centavos. El flujo normal pasaba, pero la mutación dependía de una representación de presentación en lugar de conservar la fuente entera.
- **Corrección aplicada:** se separó el cálculo interno en centavos (`calculateCents()`); el cierre usa directamente `expected_cash_cents` y los componentes enteros para persistir el arqueo. La respuesta pública conserva el formato numérico histórico para no romper clientes existentes.
- **Regresión:** `FinancialModuleTest` añade el caso de RD$0.29 y verifica 29 centavos de ventas, 29 centavos esperados, 29 contados y diferencia cero. La regresión financiera dirigida queda en **18 tests y 97 assertions PASS** en SQLite y MariaDB QA; la suite completa posterior, incluida la regresión de precio decimal exacto, queda en **490 tests y 5.011 assertions PASS** por motor.
- **Estado:** `FIXED_PASS` para la persistencia del cierre diario; `NFR-003` global sigue `NOT_RUN` por conversiones de presentación/reportes fuera de mutaciones y por faltar la auditoría estática exhaustiva.

## F-051 — Los precios efectivos del catálogo cruzaban mutaciones POS como `float`

- **Prioridad:** P1 de precisión financiera y consistencia Web/API/Android.
- **Síntoma reproducido:** POS, cotizaciones, pedidos públicos y ventas de combos obtenían el precio efectivo mediante `Product::currentPrice(): float` antes de pasarlo a `Money::toCents()`. La ruta podía introducir una frontera binaria innecesaria y no cumplía el contrato de centavos enteros para las mutaciones.
- **Corrección aplicada:** `Product` ahora expone `currentPriceDecimal()` y `currentPriceCents()` con comparación exacta de precios/ofertas; los flujos mutantes POS, cotización, pedido público y combo usan esas salidas. `currentPrice()` queda únicamente como adaptador de presentación para compatibilidad de clientes existentes.
- **Regresión:** `FifoPricingTest` añadió un precio de venta `RD$0.28` sobre precio base `RD$0.29` y verificó snapshot de movimiento, factura y total en centavos. El archivo dirigido quedó en **13 tests y 47 assertions PASS** en SQLite y MariaDB QA; las suites comerciales afectadas quedaron en **49 tests y 2.316 assertions PASS** por motor; la suite completa quedó en **490 tests y 5.011 assertions PASS** por motor.
- **Estado:** `FIXED_PASS` para las entradas mutantes cubiertas. `NFR-003` global permanece `NOT_RUN` hasta completar el inventario estático de todos los límites financieros; las conversiones restantes identificadas son salidas de presentación/reportes o validaciones de entrada y no se declaran automáticamente seguras por esta corrección.

## F-052 — El importador legacy validaba precios con `float` antes de persistir

- **Prioridad:** P1 de precisión en la frontera de importación.
- **Síntoma:** `InventoryImportService::persistSummary()` validaba `price` con `is_numeric` y `(float) < 0`. Esa ruta de compatibilidad podía recibir valores monetarios antes de su normalización canónica y exponía la frontera a redondeos binarios o a costos sin validar.
- **Corrección aplicada:** los campos `price` y `cost_price` ahora pasan por `parseMoney()` y `Money::toCents()`; se persisten como cadenas decimales canónicas y se rechazan valores inválidos o negativos antes de crear/actualizar el producto.
- **Regresión:** `AdaptiveInventoryImportTest` verifica una importación legacy con precio `0.29`, costo `0.10`, stock `3` y una sola creación. El subconjunto importación/hardening/FIFO pasó **55 tests y 328 assertions** en SQLite y MariaDB QA.
- **Estado:** `FIXED_PASS` para esta frontera. `NFR-003` global sigue `NOT_RUN` porque aún requiere cerrar el inventario estático exhaustivo de entradas/salidas financieras y distinguir formalmente cada conversión de presentación.

## F-053 — El catálogo API exponía el costo FIFO a vendedores sin permiso financiero

- **Prioridad:** P1 de autorización y privacidad financiera.
- **Síntoma:** `GET /api/v1/shops/{shop}/catalog` incluía `products[].inventory.cost_price` para cualquier vendedor con permiso de venta. La interfaz podía ocultar parte de la información, pero el JSON directo permitía recuperar el costo unitario/FIFO sin `finance`.
- **Causa raíz:** `CatalogController` serializaba el costo de inventario sin consultar el mapa de menús efectivo del actor.
- **Corrección aplicada:** el catálogo conserva el campo contractual, pero lo devuelve como `null` para vendedores sin acceso explícito a `finance`; owner, admin, manager/contador con capacidad financiera y actores con `finance` conservan el valor necesario para sus funciones autorizadas.
- **Regresión:** `SellerNavigationPermissionsTest` pasó **14 tests y 162 assertions** en SQLite y MariaDB QA. El vendedor limitado recibe `null` en el catálogo API, y las vistas Web de inventario/lotes y los módulos API/Web de salud de precios, precios automáticos y decants omiten costos, márgenes, FIFO valorado y exportaciones sensibles; el owner conserva el costo `RD$100.00`. Android compila y ejecuta la batería instrumentada con el mismo criterio: Inventario, catálogo, decants, salud de precios y precios automáticos muestran la operación permitida sin valores financieros ni controles de administración para un vendedor sin `finance`. La suite completa posterior quedó en **494 tests y 5.069 assertions PASS** por motor; la base QA quedó sin registros persistentes.
- **Estado:** `FIXED_PASS` para las superficies Web/API y Android cubiertas. PDF, caché Room después de revocación y la matriz exhaustiva verbo × ruta siguen `NOT_RUN`; no se eleva el gate por inferencia.

## F-054 — Recuperación de costo de decants calculada con punto flotante

- **Prioridad:** P1 de exactitud financiera en reportes de inventario.
- **Causa:** `InventoryService::getCostRecoveryForBottles` agregaba `unit_price` con SQL decimal y convertía costo/recaudación a `float` antes de comparar y calcular la diferencia.
- **Corrección aplicada:** la recuperación ahora carga las ventas de decants y acumula `quantity × unit_price` con `Money::toCents`; el costo de la botella, diferencia, mensaje y porcentaje parten de centavos enteros. Solo la serialización final conserva el contrato numérico de la API/UI.
- **Regresión:** `InventoryLiteTest` terminó **20 tests y 100 assertions PASS** en SQLite y MariaDB QA con tres ventas de RD$0.10 contra una botella de costo RD$0.30; cobertura, diferencia y porcentaje quedan exactos.
- **Estado:** `FIXED_PASS` para esta superficie. `NFR-003` global sigue `NOT_RUN` hasta concluir la auditoría estática exhaustiva de todas las entradas y salidas financieras.

## F-055 — Los reportes financieros y el valor de inventario recalculaban dinero con `float`

- **Prioridad:** P1 de exactitud financiera en dashboard, cartera, inventario y rentabilidad.
- **Síntoma:** el dashboard convertía agregados de ventas, descuentos, impuestos, costos FIFO, cartera, gastos, flujo de caja y rentabilidad a decimales antes de operar; `ProductInventory`, `Invoice` y `Expense` también hacían divisiones o sumas monetarias en representación decimal. La respuesta final conserva números por compatibilidad, pero la ruta de cálculo podía depender del redondeo binario.
- **Corrección aplicada:** los agregados SQL de reportes ahora llegan en centavos enteros; aging, inventario a costo, COGS, utilidad, flujo de caja, ingreso/gasto por categoría, pagos de factura, saldos pendientes, valor de inventario, margen y rentabilidad se calculan en centavos. La conversión decimal está confinada a adaptadores de presentación (`moneyNumber`, serializadores y contratos históricos).
- **Regresión:** la suite completa terminó **494 tests y 5.069 assertions PASS** en SQLite y MariaDB QA; regresiones dirigidas de finanzas, cartera, inventario y roles también pasan. El benchmark MariaDB de esta corrida registró p50 **373.10 ms**, p95 **444.57 ms**, máximo 17 consultas y pico 148 MB.
- **Estado:** `FIXED_PASS` para las mutaciones y cálculos financieros cubiertos. `NFR-003` queda `PASS parcial`: permanecen conversiones de presentación, filtros numéricos de consulta y normalización de identificadores de Excel que no mutan dinero; la matriz verbo × ruta y las pruebas físicas/interplataforma siguen abiertas.

## F-056 — El Samsung conserva una APK debug incompatible con la release firmada

- **Prioridad:** P1 de actualización Android y QA de dispositivo físico.
- **Síntoma reproducido:** el Samsung `SM-S948U1` tiene `com.bsolutions.micatalogo` en versión `1.0.27`, `versionCode 28`; la APK release publicada es `1.0.76`, `versionCode 77`, pero Android no permite actualizar el paquete.
- **Causa raíz:** la instalación existente está firmada por `Android Debug`, certificado SHA-256 `e266fd48b028dc66dc4e530c0bc8af06b9be820545600c6c4b19c81b4b1ff775`; la release válida usa el certificado oficial SHA-256 `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f`.
- **Evidencia:** `release.ps1 -Mode VerifyApk` confirmó paquete, versión, firma no debug y APK SHA-256 `24846233f98bfd099976d0965142b8aabca71a378659b74b5a8b14aa27334f6b`; `apksigner --print-certs` sobre el `base.apk` extraído del Samsung confirmó el certificado debug. El actualizador sí descargó `cache/updates/bspos-77.apk`, exactamente 40.387.781 bytes y el mismo SHA de la release, pero no pudo instalarlo por el certificado. WorkManager no tiene `WorkSpec` pendientes y no se encontraron registros de outbox en la instalación inspeccionada. No se desinstaló ni modificó la aplicación del teléfono.
- **Resolución pendiente:** desinstalar la instalación debug y luego instalar la release, o autorizar un intento controlado de `adb uninstall -k` tras comprobar que no existe outbox local sin sincronizar. Android no puede resolver un cambio de certificado mediante `-r` ni aumentando el `versionCode`.
- **Prevención añadida:** `qa/verify_android_release_target.ps1` compara el certificado instalado antes de cualquier instalación, rechaza el emulador como destino físico y devuelve un estado JSON reproducible sin modificar el dispositivo.
- **Estado:** `BLOCKED_ENV` para la instalación física; publicación y verificación criptográfica de la release quedan `PASS`.

## F-057 — Las builds debug consultaban el updater de producción

- **Prioridad:** P1 preventiva de release Android.
- **Síntoma:** una instalación debug podía consultar el manifiesto público, descargar una APK release firmada y terminar en el instalador de Android, donde el certificado debug no podía actualizarse a la firma oficial.
- **Corrección aplicada:** `AppUpdatePolicy` y `AppUpdateViewModel` ahora omiten el updater de producción cuando `BuildConfig.DEBUG` es verdadero. El guard `qa/verify_android_release_target.ps1` sigue protegiendo cualquier instalación física autorizada.
- **Regresión:** Android commit `c4aea97` ejecutó `testDebugUnitTest`: **95 tests, 0 fallos, 0 errores**; `compileReleaseKotlin` terminó correctamente; la build debug se instaló únicamente en `emulator-5554`, inició sin crash, no mostró el diálogo de actualización y el paquete instrumentado del updater terminó **4/4**. Los casos verifican que debug no consulte releases de producción y que release sí pueda hacerlo.
- **Estado:** `FIXED_LOCAL_NOT_RELEASED`: la corrección está en GitHub, pero todavía no forma parte de una nueva APK publicada. La 1.0.76 vigente conserva el comportamiento anterior y no debe instalarse sobre debug.

## F-058 — El aviso de actualización se perdía al recrear la app sin red

- **Prioridad:** P1 preventiva de actualización Android.
- **Síntoma:** si la app detectaba una versión nueva y luego el proceso se recreaba, el manifiesto pendiente solo vivía en el `ViewModel`; una caída de red podía dejar únicamente el diálogo genérico de error y no reabrir el aviso bloqueante.
- **Corrección aplicada:** `AppUpdatePendingStore` guarda localmente únicamente los datos del manifiesto validado (versión, URL HTTPS, SHA-256, mínimo y notas). `AppUpdateViewModel` lo recupera ante errores de red/HTTP/respuesta inválida, lo conserva mientras la descarga o el instalador no terminan y lo limpia cuando la versión instalada ya lo supera o se descarta una actualización opcional.
- **Regresión:** Android `7ea2916`: `testDebugUnitTest` 95/95, `compileReleaseKotlin` exitoso, `PendingAppUpdateTest` 3/3 y batería instrumentada principal `OK (114 tests; 111 PASS y 3 omitidos por assumption)`. No se publicó APK ni se modificó el Samsung.
- **Estado:** `FIXED_LOCAL_NOT_RELEASED`; requiere una nueva APK release autorizada para llegar a dispositivos.

El baseline local es verde después de F-001, F-002, F-003, F-006, F-007, F-008, F-010, F-011, F-012, F-014, F-015, F-016, F-017, F-018, F-019, F-021, F-022, F-023, F-024, F-025, F-026, F-027, F-028, F-029, F-030, F-031, F-032, F-033, F-034, F-035, F-037, F-039, F-040, F-041, F-042, F-043, F-044, F-046, F-047, F-048, F-049, F-050, F-051, F-052, F-053, F-054 y F-055. F-045 queda como riesgo P2 de CI y F-056 como bloqueo de instalación física. El release gate permanece **NOT_READY** por F-004, F-005, F-036, F-038 y F-056, además de las pruebas de estrés, visuales, escenarios offline adicionales y servicios externos pendientes. No se hicieron escrituras en producción durante este corte.
