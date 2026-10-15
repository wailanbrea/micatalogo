# F4 — avances verificados de autorización por operación

Estado: implementación y regresión avanzadas; el gate integral F4/F5 sigue pendiente. Entornos exclusivos: backend `testing/sqlite/:memory:`, Room en memoria y servidor QA `127.0.0.1:18768` con la base local `micatalogo-navigation-20261014.sqlite`.

## Corte actual — Compras, Cobros y Crédito

- Backend completo **663 PASS / 7.597 aserciones**; Android JVM **147 PASS**; instrumentadas focalizadas **49/49 PASS**. Evidencia `evidence/f4-purchase-credit-collection-final-pass.json`; detalle y matriz completa de casos en `QA_REPORT.md`.
- `PurchaseAccess`/ViewModel resuelven permisos antes de consultar, filtran tipos/documentos, bloquean creación/recepción ajena, limpian contexto y descartan respuestas tardías incluso al volver a la misma tienda. La recarga de suplidor exclusivo cuenta con prueba funcional del ViewModel.
- `CachedShopMenuPolicy` se consulta dentro del registro de abonos vinculados y antes de encolar cobros remotos. Sin permiso vigente no se altera saldo/recibo/cola. Cobrar deuda usa `collections` y capability clientes, sin exigir capability de crédito nuevo.
- Nueva deuda POS exige `credit` en Web/API y Android, también cuando llega por `payments[].method=credit`. `PaymentService` aplica el control compartido. Pedido a crédito y su replay revalidan `credit` antes de devolver un recibo existente. Se conservan ventas pagadas con sólo Ventas.
- `CompleteSaleUseCase` y `RecordPaymentUseCase` usan el lock de tienda alrededor de la transacción. Los payloads pendientes conservan UUID/contenido; las denegaciones no dejan stock, saldo ni recibos parciales.
- Exportación actual: `routes-after-f4-credit.json` y `OPERATION_ACCESS_MATRIX_F4_CREDIT.md`. Las identidades de las 237 rutas no cambian.

## Límites implementados y pruebas

| Límite | Implementación | Regresión |
|---|---|---|
| Operaciones por clave, importación y categorías del tenant | Middleware individuales, `MobileOperationService` y validación del shop de la categoría | `NavigationOperationBoundaryTest` |
| ACK no omite permiso actual ni propietario de Caja | Revalidación antes del lookup UUID; `FinancialMobileOperationService::authorizeReceipt` | `NavigationOperationBoundaryTest`, `FinancialMobileOperationTest` |
| Reportes sin Finanzas | Proyección operativa compartida Web/API/CSV/XLSX; costos y ganancias sólo con facultad financiera | `NavigationSensitiveReadTest`, `SellerNavigationPermissionsTest` |
| Productos y Servicios | `CatalogAccessService`, `EnsureCatalogOperationAccess`, policy y formulario; autorización del recurso persistido y tipo solicitado | `NavigationCatalogBoundaryTest` |
| Compras | `PurchaseAccessService` por tipo real de documento y recepción; suplidores/deudas separados | `NavigationPurchaseBoundaryTest`, `ApiPurchaseTest` |
| Clientes, crédito y cobros | Lectura compartida; escritura separada en `customers`, `credit`, `collections` | `NavigationCustomerBoundaryTest`, `CustomerPaymentAllocationTest` |
| Fotos sin Productos | Ruta Web específica y `product_photo_update`; sin mutación de metadata, stock ni costos | `NavigationPhotoBoundaryTest` (5 PASS) |
| Fotos Android | `canManageCatalogPhotos`, política del caché al encolar, selector independiente, UUID y hash en ACK | `CatalogMutationAccessTest`, `CatalogSyncIntegrationTest`, `NavigationLiveRevocationTest` |
| Finanzas Android | Pestañas y lecturas/mutaciones limitadas; descarte de respuestas tardías | `FinanceAuthorizationTest`, `FinancePermissionTabsTest` |

La exportación estática actual está en `OPERATION_ACCESS_MATRIX_F4.md`. Las 33 mutaciones que no llevan literalmente `menu:` requieren interpretar sus policies/servicios (incluidos `catalog.operation` y envelopes); el conteo no equivale a 33 vulnerabilidades ni certifica cada dispatch.

## Ejecución histórica del corte Fotos

- Backend completo: **660 PASS, 7.570 aserciones**, 105,92 s, con identidad de conexión mostrada antes de Pest. Comando: `php vendor/pestphp/pest/bin/pest --compact --stop-on-failure --colors=never`.
- Selección backend Fotos/Catálogo/operaciones móviles: **35 PASS, 296 aserciones**.
- Android JVM: **143 PASS**, 0 fallos/errores/skips, 42 XML actuales.
- `testDebugUnitTest assembleDebug assembleDebugAndroidTest`, variante QA y API local: build exitoso. Último rebuild sólo del APK de tests también exitoso.
- Instrumentadas finales: **28 declaradas, 28 ejecutadas, 28 PASS**, sin fallos/skips/assumptions/missing. Clases: `CatalogSyncIntegrationTest`, `FinancialOutboxTest`, `TenantSyncTest`, `FinancePermissionTabsTest`, `NavigationLiveRevocationTest`. Evidencia: `evidence/f4-f5-photo-scope-final-pass.json`. Se conserva también la selección previa de 22/22 en `evidence/f4-f5-photo-revocation-regression-pass.json`.
- APK debug QA: `com.bsolutions.micatalogo.offlinecheck.syncqa`, 1.0.111-offlinecheck/112, certificado SHA-256 `e266fd48b028dc66dc4e530c0bc8af06b9be820545600c6c4b19c81b4b1ff775`, instalado con `install -r` únicamente en `emulator-5554` tras verificar identidad/firma.
- Rutas: **237 antes / 237 después**, ninguna identidad de ruta removida, cambiada ni añadida. `routes-after-f4.json` y `verification-baseline.json`.

## Qué prueban Fotos y la revocación

- Web permite subir una imagen con `photos` sin permitir editar metadata del producto.
- Backend móvil valida bytes/hash, rechaza campos de producto, tenant externo, replay revocado y exceso de cuota; conserva íntegros los atributos del producto, inventario y fotos previas. El ACK repetido no crea otra imagen ni recibo.
- Room conserva payload/UUID aunque cambie el archivo origen o se revoque el caché. ACK de otro producto/hash queda pendiente; 403 bloquea sin borrar la solicitud; el reintento confirmado crea una descarga durable. Un fallo de descarga no pierde esa obligación.
- El encolado desde el ViewModel comparte `ShopScope.mutate`/`ShopSwitchLock` con las demás mutaciones. La política de Fotos se verifica antes y después de preparar los bytes. La regresión revoca el caché entre esas lecturas y comprueba que no se encola otra operación ni se cambia el payload previo. `TenantSyncTest` mantiene las comprobaciones del lock y aislamiento entre tiendas.
- UI real con permiso exclusivo Fotos abre el selector del sistema, no el editor de producto. La prueba cancela la selección: **no es una carga completa de imagen desde el selector hasta el servidor QA**. Esa integración por todas las capas sigue sin certificar.
- UI real de Servicios se cierra tras revocar el permiso en servidor y refrescar el snapshot; navegar a Cuenta y pulsar Atrás no restaura el workspace revocado. El test restaura los permisos originales y el autofill en `finally`.

Evidencias de UI: `f5-photo-only-workspace.txt`, `f5-photo-only-picker.png`, `f5-live-revocation-after-back.txt` y `.png` dentro de `evidence/`.

## Fallos de comprobación reproducidos y corregidos

1. La primera comparación PHP usó atributos de un modelo recién creado frente a uno rehidratado por SQLite. Se normalizó el baseline con `refresh()` antes de comparar; se mantienen comparaciones exactas de todos los atributos e inventario.
2. El primer test de descarga de Fotos esperaba `true` cuando la descarga ya había terminado. `CatalogRefreshSyncRepository.sync()` devuelve si **queda** trabajo; se corrigió a `false`, manteniendo las comprobaciones de cola durable tras fallo y vacía tras éxito. Registro inicial: `f4-photo-outbox-initial-failure.json`.
3. El test visual intentó usar UiAutomator, dependencia ausente. Se usa la API de automatización disponible, sin añadir dependencias.
4. El selector del emulador es `com.google.android.photopicker`, no los paquetes DocumentsUI/MediaProvider esperados inicialmente. Se confirmó mediante Activity Manager (`PhotopickerGetContentActivity`, acción GET_CONTENT/image) y se amplió la identificación del selector. Registro inicial: `f5-photo-picker-initial-failure.json`; el rerun completo terminó 22/22 PASS.

## Conservación y pendientes

Inspección read-only del fixture después de las pruebas reales y nuevamente después de la selección final: QA_NAV_Perfumes conserva stock20, vendidos0, costo120.00, cliente saldo0, cero facturas y cero sesiones de Caja; Barber/Grocery sin productos ni movimientos. Las pruebas reales sólo modifican temporalmente permisos sintéticos y los restauran. La lectura de permisos confirmó Servicios/Cuenta/Ayuda restituidos para el actor utilizado.

Se corrigió además `PurchaseModuleViewModel.createSupplier`: al finalizar recarga `activeFeature` en lugar del default `containers`. En el corte siguiente se añadió `PurchaseAuthorizationTest.supplierCreationReloadsOnlySuppliers`, PASS, que registra la llamada de escritura y únicamente la recarga de Suplidores; la operación desde UI completa queda en los journeys pendientes.

No hay aprobación global de seguridad ni de release: faltan revisar los puntos restantes de la matriz F4, completar NAV-001..NAV-070 y E2E A..G, accesibilidad, comprobaciones offline/multitienda de todas las pantallas y documentación/commits de entrega. Los tests de Room de esta selección prueban colas no vacías en memoria; no sustituyen una comparación persistente completa antes/después de recomponer toda la navegación.
