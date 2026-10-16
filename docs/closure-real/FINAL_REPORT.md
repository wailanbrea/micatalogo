# Cierre de implementación y QA — 2026-10-16

## Resultado ejecutivo

Se corrigieron defectos reproducibles en la protección de presentación/unidad histórica en Web/API y en el formulario Android cuando el snapshot de categorías llega vacío. También se corrigieron dos aserciones Android desactualizadas y se reconciliaron las afirmaciones históricas de certificación.

**Estado: NO-GO para aprobación/publicación.** No se pudo certificar el conjunto solicitado: los recorridos E2E Web↔Android necesitan una API y base QA compartidas cuya identidad actual no está verificada; NAV requiere ejecución por caso; no se hizo QA Web en navegador. No se modificó producción, no se desplegó backend y no se publicó APK.

## Repositorios y ramas

| Repositorio | Rama | Base inicial | Estado de cambios |
|---|---|---|---|
| Web/API | `work/navigation-closure-20261015` | `d1cb137` | `0111b97` — commit y push verificados |
| Android | `work/navigation-closure-20261015` | `4f03ca9` | `03d18b4` — commit y push verificados |

Los heads locales coincidían con su rama remota de trabajo antes de editar. Remotos main/master no fueron modificados. La publicación de backend y APK quedó expresamente fuera del alcance.

## Conteo de aceptación por ID

`ACCEPTANCE_MATRIX.csv` tiene exactamente 126 filas: 48 QA, 8 E2E y 70 NAV. No se promocionó una prueba unitaria/feature a un recorrido integral. El criterio individual permanece BLOCKED cuando no existe evidencia del tipo exacto que pide el prompt.

| Grupo | PASS | FAIL | BLOCKED | NOT_RUN | Nota |
|---|---:|---:|---:|---:|---|
| QA-001..048 | 0 | 0 | 48 | 0 | Pest completo y JVM completos sí pasaron como suites, pero no se enlazó cada ID original a una evidencia de categoría y entorno exactos en este corte. |
| E2E A–H | 0 | 0 | 8 | 0 | No se ejecutaron journeys cruzados con ledger/Room compartido en una API QA identificada. |
| NAV-001..070 | 0 | 0 | 70 | 0 | No se heredan los 11 PASS históricos; la corrida UI amplia no es una ejecución válida de los escenarios live. |

Conteo derivado mecánicamente del CSV: **0 PASS, 0 FAIL, 126 BLOCKED**. Es una certificación incompleta, no un informe de que el código completo esté fallando.

## Pruebas ejecutadas

| Plataforma/comando | Resultado medido | Entorno |
|---|---|---|
| `php vendor/bin/pest --compact` | **686 passed, 0 failed, 7,774 assertions**, 49.08 s | PHP 8.2, `APP_ENV=testing`, SQLite `:memory:`, Turnstile desactivado en PHPUnit |
| `:app:testDebugUnitTest` | **152 tests, 0 failures, 0 errors, 0 skipped** | Android/JVM, XML en `app/build/test-results/testDebugUnitTest/` |
| `:app:assembleDebug :app:assembleDebugAndroidTest` | BUILD SUCCESSFUL | Variante QA debug aislada; no release |
| `connectedDebugAndroidTest` — ProductFormTest | **5/5 PASS** | Pixel 10 Pro XL AVD, perfil UI aislado y endpoint local no enrutable |
| `connectedDebugAndroidTest` — LoginScreenTest | **1/1 PASS** | mismo emulador/aislamiento |
| `connectedDebugAndroidTest` — DecantsWorkspaceTest | **13/13 PASS** | mismo emulador/aislamiento |
| `connectedDebugAndroidTest` perfil UI amplio | **110 tests; 10 entradas fallidas** | Primera corrida amplia; no se trata como PASS. 2 fueron expectativas obsoletas, 8 dependían de API QA live/flags no presentes. Las dos expectativas de UI se actualizaron y se repitieron sus clases focalizadas. |

No hubo escritura a una API comercial. En los tests live, la URL configurada para la corrida segura fue `127.0.0.1:9`; los escenarios que exigen `127.0.0.1:18768` no eran ejecutables en esa configuración.

## Defectos corregidos y regresión

| Defecto | Corrección | Prueba de regresión | Resultado |
|---|---|---|---|
| Edición Web podía cambiar unidad/volumen de producto con stock, movimientos, lotes FIFO, venta, decants, combos, reserva o relación comercial | `ProductPresentationChangeGuard` deniega cambio semántico con error de validación y mantiene stock/ledger intactos | `SellerProductTest`: producto con stock y movimiento no puede pasar de unidad a botella; `MobileOperationTest`: producto con lote FIFO no puede convertirse | Incluido en Pest completo verde |
| Android hacía `categories.first()` si el snapshot de taxonomía estaba vacío durante creación | Estado vacío, selector deshabilitado, mensaje contextual; la validación impide guardar sin categoría; la categoría de servicio sigue visible sin controles físicos | `ProductFormTest.emptyCategorySnapshotDoesNotCrashOrSaveProduct` y `serviceProductKeepsCategorySelectorVisibleWithoutPhysicalInventoryControls` | 5/5 instrumentadas verdes |
| Test de login esperaba etiqueta sin tilde aunque UI expone español accesible correcto | Se actualizó la expectativa a `Mostrar contraseña` | `LoginScreenTest` | 1/1 verde |
| Test Decants esperaba “1 tamaños” | Aserción ajustada al singular “1 tamaño” que muestra la UI | `DecantsWorkspaceTest` | 13/13 verde |
| Live QA de Fotos fallaba como fallo de producto al ejecutarse con endpoint de UI no live | El preflight identifica la configuración live requerida; no se considera probado sin ese servicio | `NavigationLiveRevocationTest` requiere instancia de navegación QA aislada | Bloqueado por entorno; no se declara PASS |

## Alcance funcional y paridad

- Se preserva el producto histórico cuando hay historial comercial: para cambiar presentación se requiere un flujo explícito de conversión segura o crear una presentación nueva vinculada.
- Android muestra estado vacío cuando faltan categorías, conserva la selección de categoría para servicios y evita controles de stock/unidad física en servicio-only.
- Suites existentes cubren fragmentos de arquetipos, UUID de unidades, permisos, inventario/FIFO, Decants y combos; **no se certifica la equivalencia operativa completa** sin E2E compartido.
- Persisten limitaciones conocidas: `brand`/`barcode` `web_only`; `model` `unsupported` en contrato móvil documentado.
- No se verificaron con operaciones integrales el costo/COGS de apertura/venta, devolución, combo desde ambas plataformas, reintento offline/outbox, cambio multi-tenant con colas reales ni la conciliación financiera en una misma fixture.

## Documentos reconciliados

Los viejos `QA_MATRIX.md`, `E2E_JOURNEYS.md` y `FINAL_REPORT.md` tenían afirmaciones incompatibles con `QA_REPORT.md` y `05_QA_EXECUTION.md`. Se etiquetaron como históricos/supersedidos y este directorio es la fuente actual. La matriz tiene criterios por ID, actual, estado, runner, entorno y evidencia limitada.

## Producción, migraciones y datos

- Producción: sin escrituras, migraciones, seeders, ventas o cambios de permisos.
- QA live: no se hicieron escrituras porque la identidad y el aislamiento actuales no pudieron verificarse.
- Base local SQL comercial: no se usó.
- APK release, versión pública, firma y servidor: no cambiados ni inferidos a partir de builds locales.

## Bloqueos para continuar y rollback

1. Preparar/identificar un endpoint QA aislado; comprobar `environment=testing`, driver SQLite y ruta exacta antes de crear registros.
2. Ejecutar los 8 journeys A–H con actores sintéticos y snapshots antes/después de SQL, Room, FIFO/ledger y outbox.
3. Ejecutar los 70 casos NAV individualmente, en las interfaces/roles/tamaños exigidos; salvar screenshots y trazas.
4. Ejecutar navegador Web con fixtures QA y validar equivalencia Web↔Android; revisar campos no soportados.
5. Repetir suites finales sobre los commits SHA publicados en la rama de trabajo.

No hay rollback de datos que hacer. Antes de despliegue, revertir únicamente los commits de esta tarea con `git revert <sha>` en la rama de trabajo; no force-push ni borrar datos.

## Evidencia y siguiente gate

- Detalle por ID: `ACCEPTANCE_MATRIX.csv` y `.json`.
- Comandos/aislamiento y baseline: `00_BASELINE.md`.
- Riesgos y limitaciones: `RISKS.md`, `KNOWN_LIMITATIONS.md`.
- Preparación: `RELEASE_READINESS.md`.

La ejecución no está lista para producción. Se puede revisar/continuar en la rama de trabajo tras commit/push, pero la aprobación requiere desbloquear el entorno QA integrado.
