# Ejecución QA y evidencia

> **Corte anterior supersedido en el cierre 2026-10-16.** Los conteos de este documento son evidencia histórica del día/runner descrito y no certifican QA-001..048, E2E A–H ni NAV-001..070 en los SHA actuales. Consultar `docs/closure-real/FINAL_REPORT.md` y `ACCEPTANCE_MATRIX.csv` para el cierre actual.

Fecha de ejecución: 2026-10-16 (entorno del proceso; el contexto del workspace mostraba 2026-10-15). Alcance: QA local aislada. Ninguna operación comercial o base de datos productiva fue utilizada.

## Resultado real

| Prueba | Estado | Evidencia |
|---|---|---|
| Web `BusinessPresentationTest` | PASS | `8 passed`, `2,581 assertions`, SQLite en memoria. Incluye payload de tiendas/perfil, 23 tipos, contrato y render formulario. |
| Android unit tests | PASS | `:app:testDebugUnitTest --rerun-tasks`, `BUILD SUCCESSFUL`, 33 tareas ejecutadas. |
| Android `ProductFormTest` instrumentado | PASS | Pixel 10 Pro XL AVD; 3 tests, 0 failures/errors/skips. |
| Web navegador local perfil electrodomésticos | PASS | Login fixture, muestra “Nuevo electrodoméstico”, default unidad, sin modo botella/ml; alta y vuelta al listado. |
| Persistencia de fixture local | PASS | Lectura de vuelta confirmó `sale_unit=unit`, stock `5`, costo `40000`, UUID de unidad estable. |
| Compilación Android debug | PASS | Tarea instrumentada compiló/instaló el APK de prueba en el emulador. No release. |

La aplicación web de QA corrió en `127.0.0.1:18765` con `APP_ENV=testing` y SQLite temporal local, en `C:\Users\waila\AppData\Local\Temp\micatalogo-product-form-qa-20261016.sqlite`; fixture `product-form-qa@example.test`, perfil `appliance_store`, producto sintético `QA Refrigeradora Samsung`. No se guardaron capturas porque no se configuró el artefacto de screenshot; resultado y lectura de vuelta son la evidencia reproducible disponible.

El login cargó Turnstile externo y emitió errores/avisos de consola del proveedor; la sesión local y las pantallas de producto funcionaron. No se atribuye esa salida externa al formulario.

## Cobertura de aceptación

- Contrato, perfiles appliance/perfume/barber, falta de botella/ml para electrodomésticos, UUID de unidad, render de formularios y tipos registrados: cubiertos por feature/unit UI tests ejecutados.
- Flujo navegador real de creación: probado solo para appliance en tenant local sintético.
- Android: tests instrumentados focalizados de estado/formulario, no una transacción HTTP real Web↔Android.
- PF-001 parcialmente cubierto por fixture de tienda configurada; PF-002 creación local Web confirmada. PF-005..PF-030 no se presentarán como E2E completados.
- E2E-A..F (ventas, devoluciones, Decants con FIFO, roles restringidos, multi-tienda/offline y legacy cross-platform): BLOCKED/no ejecutados en esta tanda; requieren fixture de QA integrado con API, operaciones y baseline contable. No se usan datos productivos para completar esa cobertura.

## Comandos

```powershell
php vendor/bin/pest tests/Feature/BusinessPresentationTest.php
.\gradlew.bat :app:testDebugUnitTest --rerun-tasks
.\gradlew.bat :app:connectedDebugAndroidTest -PmiCatalogoQaProfile=ui -PmiCatalogoOfflineCheck=true -PmiCatalogoApiBaseUrl=http://127.0.0.1:18765/ "-Pandroid.testInstrumentationRunnerArguments.class=com.example.bspos.presentation.catalog.ProductFormTest"
```

En la primera repetición luego de recompilar se encontró un fallo de aserción del test: esperaba una opción `service` en un perfil appliance cuyo contrato solo entregaba `unit`. No era fallo de la pantalla; la expectativa era incorrecta respecto al contrato. Se cambió la prueba para verificar que una opción no autorizada/no declarada no aparezca y se repitió en el mismo emulador: **3/3 PASS**.
