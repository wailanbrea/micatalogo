# QA — navegación Web/API/Android

> **Estado fuente para navegación:** este documento conserva 70 IDs individualizados. El último corte previo reporta 11 PASS y 59 BLOCKED; ese estado se considera evidencia histórica y cada PASS se vuelve a evaluar contra el SHA final. El reporte resumido `adaptive-catalog/FINAL_REPORT.md` queda supersedido. Ver la matriz de este cierre en `docs/closure-real/ACCEPTANCE_MATRIX.csv`.

## Entrega 15/10/2026 — actualización de evidencia

Se publicó Web y Android1.0.112/113. Backend666PASS/7.614 aserciones, Android147JVM PASS y194 identidades instrumentadas PASS entre las configuraciones QA18768/18767. Se navegaron46 destinos Web internos con HTTP200. Ver `../RELEASE_1.0.112_2026-10-15.md` y `evidence/release-1.0.112-combined-final.json` para fallos iniciales del arnés, repeticiones, hashes, publicación y límites. Las matrices siguientes son históricas: no se convierten todos sus recorridos manuales/accesibilidad en aprobados por ejecutar estas suites.

## Corte actual: Compras, Cobros y crédito POS

Este reporte registra cobertura y evidencia; no aprueba todavía los gates F4–F6. `PASS` corresponde al resultado específico descrito. `BLOCKED` en la matriz significa que falta una comprobación para certificar el caso completo, aunque existan componentes aprobados; no indica que haya que solicitar permisos adicionales para continuar el QA aislado.

### Entornos y ejecución

- Backend completo final: **663 PASS, 7.597 aserciones**, 68,95 s. `testing`, conexión `sqlite`, host ninguno, base `:memory:` verificada antes de Pest.
- Android JVM: **147 PASS**, cero fallos/errores/skips, 44 reportes XML actuales.
- Compilación: `testDebugUnitTest assembleDebug assembleDebugAndroidTest` PASS; variante `miCatalogoOfflineCheck=true`, perfil `all`, API `http://127.0.0.1:18768/`. El perfil declara **192** métodos, no todos ejecutados en este corte.
- Instrumentadas focalizadas: **49 declaradas, 49 ejecutadas, 49 PASS**, sin skips, assumptions ni faltantes. Comprobante: `evidence/f4-purchase-credit-collection-final-pass.json`.
- Dispositivo: `emulator-5554`, `sdk_gphone16k_x86_64`; paquete `com.bsolutions.micatalogo.offlinecheck.syncqa`, versión `1.0.111-offlinecheck`/112. Certificado debug SHA-256 `e266fd48b028dc66dc4e530c0bc8af06b9be820545600c6c4b19c81b4b1ff775`, verificado con apksigner antes de instalar con `-r`.
- UI real: sólo servidor QA `127.0.0.1:18768`, `testing/sqlite`, base `C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-navigation-20261014.sqlite`. Los tests verifican esa identidad antes de escribir permisos de actores sintéticos y los restauran.
- Room: pruebas de datos en memoria. La inspección posterior del fixture remoto local conserva stock20, vendidos0, costo120.00, saldo0, cero facturas/cajas; mismos IDs de tiendas, producto y cliente.
- Bases Git auditadas: Web `48cbd4ae7b73656369f2274db754ae63036a800f`; Android `ad9ecd7610d3089e55b2bcb8067ed4776a9bd5c1`. Los cambios siguen sin commits de entrega; las referencias de test no implican un SHA nuevo ni CI remoto.

### Defectos reproducidos y corregidos

| ID | Reproducción | Corrección | Regresión |
|---|---|---|---|
| NAV-F4-PURCHASE | Sin claves de Compras se consultaba `purchases` y se podía invocar `purchase_create`; factura-only aceptaba encolar un contenedor; respuesta tardía restauraba datos revocados | `PurchaseAccess`, resolución previa al HTTP, autorización por tipo/documento, epoch/versión de lectura, descarte de respuesta y limpieza de formularios por contexto | `PurchaseAuthorizationTest`: 3 FAIL/1 PASS inicial; 5/5 PASS final, incluido A→B→A |
| NAV-F4-COLLECT | Un abono local de cliente remoto se registraba sin permiso actual | `CachedShopMenuPolicy` obligatorio y control en `RecordPaymentUseCase`, dentro de ShopScope/Room; control adicional antes de encolar el abono remoto | `RecordPaymentUseCaseTest`: rojo reproducido, 5/5 PASS; `CollectionAuthorizationTest` 2/2 PASS |
| NAV-F4-POS-CREDIT | API POS respondió 201 a Ventas+Cobros sin `credit` y creó deuda | Guard `credit_amount > 0` antes del lookup UUID en el controlador compartido Web/API; presentación POS y alias Android exigen `credit`; `CompleteSaleUseCase` valida ventas/crédito antes de mutar y encolar | `NavigationCustomerBoundaryTest`, `CompleteSaleUseCaseTest`, `DrawerNavigationTest` |
| NAV-F4-ORDER-CREDIT | Un pedido a crédito ya confirmado devolvía 201 al repetir su envelope después de revocar Crédito | Control en `OnlineMobileOperationService` antes del UUID y en `OrderConfirmationService`; `PaymentService` protege también crédito normalizado desde `payments[].method=credit` | `OnlineMobileOperationTest`: replay rojo403/201 → PASS; backend completo663 PASS |
| QA-COLLECT-WAIT | El test consultaba la cola antes de terminar el trabajo asíncrono Room del ViewModel | Esperar `collecting=false`, además de drenar el scheduler; se conserva la exigencia de exactamente un payload pendiente | 48 PASS/1 FAIL inicial → 49/49 PASS final |

Evidencias rojas conservadas: `f4-purchase-authorization-red.json`, `f4-collection-authorization-red.json`, `f4-purchase-credit-collection-initial-failure.json`. La primera reproducción POS está en la salida Pest: 403 esperado/201 recibido. La suite completa posterior verifica 403, ninguna mutación parcial y replay autorizado una sola vez.

Al añadir la autorización compartida, SALE-008 falló porque su mock estricto no permitía ejecutar el nuevo método real previo al cobro. Se convirtió ese doble en un runtime partial: conserva `processInvoicePayments` exactamente una vez con la excepción inyectada y todas las comprobaciones de rollback de factura, upload, inventario y caja. No se eliminó ni relajó la regresión transaccional.

### Procedimiento de las regresiones nuevas

- **Compras:** actor sintético con `purchase_invoices`; cargar facturas, intentar crear/recibir contenedor, recibir factura propia. Esperado/real: tipos ajenos no llaman al gateway, sólo factura propia lo hace. Repetir revocando durante una lectura y cambiando A→B→A: la respuesta anterior no restaura workspace. Supplier-only guarda y recarga únicamente `suppliers`.
- **Cobros:** actor con `collections` y capability clientes activa/crédito nuevo deshabilitado. Cobrar25 de100: saldo75. Retirar caché de permiso y repetir: se conserva saldo75 y un solo recibo. Con pantalla aún autorizada pero caché ya revocada no se encola otro abono; el payload pendiente anterior permanece idéntico.
- **Crédito POS:** actor con `sales,collections`; intentar deuda100 por Web/API. Esperado/real: 403, saldo0 y stock3. Conceder `credit`, enviar y repetir UUID: una factura, saldo100, stock2. Revocar `credit` y repetir: 403. Una venta pagada sigue admitida con sólo Ventas. Android conserva stock/saldo ante crédito no autorizado y permite venta pagada.
- **Regresión ampliada:** los 49 métodos incluyen además colas/ACK, Decants, pagos mixtos, aislamiento de tenant, Finanzas y revocación real/Back/Fotos de las selecciones anteriores.

## Matriz NAV-001..NAV-070

Las capturas F0/F3 conservan su carácter histórico. Las verificaciones de negocio backend y Room no se presentan como journeys completos desde ambos shells. Cada fila identifica la evidencia disponible y el paso adicional necesario cuando el estado es BLOCKED.

| ID | Caso / permisos | Estado | Evidencia y comprobación restante |
|---|---|---|---|
| NAV-001 | Owner, paridad Web/Android | BLOCKED | Árbol canónico, `SellerNavigationWebTest`, capturas F3; falta recorrido comparativo de todos los hijos en build actual. |
| NAV-002 | Sólo Ventas | BLOCKED | Guard/manifest y costos redactados aprobados; falta recorrido UI actual de venta/historial con ese actor. |
| NAV-003 | Servicios sin Productos | BLOCKED | `NavigationCatalogBoundaryTest` y Servicios real abierto; falta alta/edición desde Android hasta Web. |
| NAV-004 | Productos sin Servicios | BLOCKED | Rechazos de servicio Web/API y política Android aprobados; falta recorrido UI actual completo. |
| NAV-005 | Caja sin Finanzas | BLOCKED | Guards backend, `FinanceAuthorizationTest`, tabs Compose y prueba de rol previa; falta operación de caja desde ambos shells en el mismo journey. |
| NAV-006 | Reportes sin Finanzas | BLOCKED | CSV/XLSX/Web/API redactados; falta reporte/exportación desde el shell Android actual. |
| NAV-007 | Contador read-only | BLOCKED | Matrices backend y Finance JVM; falta smoke de todos los accesos read-only Android actuales. |
| NAV-008 | Manager sin controles owner | BLOCKED | Manifest/roles/policies aprobados; falta secuencia actual de formularios administrativos Android. |
| NAV-009 | Admin global separado | BLOCKED | Guards y whitelists aprobados; falta navegación visual global/tienda en build actual. |
| NAV-010 | Productos oculto | BLOCKED | Filtrado de manifest y tipo aprobado; falta categorías/servicios contextuales en ambos shells. |
| NAV-011 | Cambio de tienda | BLOCKED | `TenantSyncTest`, Compras A→B→A PASS; falta verificar cada pantalla del back stack. |
| NAV-012 | Offline con caché | BLOCKED | Caché, UUID y revalidación por operación aprobados; falta recorrido del shell completo offline. |
| NAV-013 | Decants perfumería | BLOCKED | Backend y consumo ml/stock Room PASS; falta preparación/venta navegadas desde ambos shells. |
| NAV-014 | Barbería sin Decants | BLOCKED | Capability/endpoint/whitelist y captura F3; falta deep link real en build actual. |
| NAV-015 | Crear/vender servicio | BLOCKED | Sin stock físico en Web/API; falta journey Android de alta y venta. |
| NAV-016 | Producto, foto, precio, stock | BLOCKED | Contratos/regresiones de catálogo PASS; falta recorrido completo desde UI Android. |
| NAV-017 | Combo | BLOCKED | `SellerPosTest` conserva consumo/costo; falta creación y venta desde ambos shells. |
| NAV-018 | Importar | BLOCKED | Permisos/tenant/idempotencia backend; falta selección XLSX/CSV y confirmación UI actual. |
| NAV-019 | Comprar/recibir | BLOCKED | Compra por tipo y recepción idempotente backend, ViewModel Android PASS; falta journey compartido de recepción. |
| NAV-020 | Abrir/cerrar Caja | BLOCKED | Dominio/API y tabs PASS; falta reconciliación de una misma operación desde ambas UIs. |
| NAV-021 | Venta/devolución | BLOCKED | Regresiones FIFO, devolución y outbox PASS; falta journey actual de navegación Web/Android. |
| NAV-022 | Crédito/abono | BLOCKED | Permisos independientes, no duplicación y balances PASS; falta crédito+abono entre ambos shells sobre la misma venta. |
| NAV-023 | URL Web antigua | BLOCKED | 237 identidades conservadas y selección de rutas probada; falta visitar todos los enlaces operativos. |
| NAV-024 | Deep links Android | BLOCKED | 54 Screen inventariadas y whitelist/aliases probados; falta abrir todos los aliases en build actual. |
| NAV-025 | Barra inferior máximo5 | PASS | `DrawerNavigationTest`: hasta cuatro destinos más Más; capturas F3 por rol/formato y shell integrado. |
| NAV-026 | Cuenta/Ayuda sin Ajustes | PASS | `SellerNavigationWebTest` y Cuenta real tras revocar Servicios en `NavigationLiveRevocationTest`. |
| NAV-027 | Visibilidad de módulos | BLOCKED | NULL/owner/roles y refresh PASS; falta rehabilitación desde el editor en Android actual. |
| NAV-028 | Fotos/QR/Mi tienda | BLOCKED | Fotos con permiso exclusivo PASS; falta QR/vitrina y carga de foto desde selector hasta servidor. |
| NAV-029 | Cargas de compras/ruta | BLOCKED | Destinos distintos y `PurchaseAccess` niega `route_loads`; falta apertura real de ambos flujos. |
| NAV-030 | IDOR | BLOCKED | Matriz de actor/tenant backend y Room PASS; falta cerrar revisión por cada tab/acción compartida. |
| NAV-031 | Finanzas compartidas | BLOCKED | Caja/Gastos sin lecturas hermanas y backend Reportes PASS; falta recorrido integral de reportes Android y revocación de sus datos. |
| NAV-032 | Concurrencia/sync | BLOCKED | UUID, receipts, colas/orden y A→B→A PASS; falta navegación concurrente con colas persistentes no vacías. |
| NAV-033 | Módulos sin flujo real | BLOCKED | Fallback/registry revisados; falta smoke de cada CTA visible. |
| NAV-034 | Accesibilidad | BLOCKED | Falta auditoría actual TalkBack, contraste, targets y teclado Web. |
| NAV-035 | Reversión | BLOCKED | Baselines y claves preservados; falta ejecutar ensayo de rollback de código/configuración. |
| NAV-036 | Menú NULL legacy | PASS | `SellerNavigationManifestTest` y `NavigationManifestContractTest`: semántica/fallback legacy conservados. |
| NAV-037 | Owner con módulos ocultos | BLOCKED | Anti-lockout contractual probado; falta rehabilitar desde UI Android. |
| NAV-038 | Sólo Servicios | BLOCKED | Misma cobertura de NAV-003; pendiente alta/edición Android real. |
| NAV-039 | Sólo Productos | BLOCKED | Misma cobertura de NAV-004; pendiente UI actual. |
| NAV-040 | Caja no carga P&L/flujo | PASS | `FinanceAuthorizationTest` registra sólo lecturas de caja; tabs Compose no componen Finanzas/Gastos; backend deniega hermanos. |
| NAV-041 | Gastos no carga Caja/Finanzas | PASS | Finance JVM registra sólo gastos/categorías; Compose elimina otros tabs; límites backend PASS. |
| NAV-042 | Reportes/exportar sin Finanzas | BLOCKED | `NavigationSensitiveReadTest` valida incluso XLSX real; falta recorrido Android. |
| NAV-043 | Finanzas no abre/cierra Caja | PASS | Matrices API y `FinanceAuthorizationTest` no ejecutan mutaciones de caja sin `cash`. |
| NAV-044 | Contador | BLOCKED | Cobertura de NAV-007, pendiente smoke integral UI. |
| NAV-045 | Categorías contextual/Servicios | BLOCKED | Crear físico denegado; falta workflow contextual de categorías sin conceder CRUD físico. |
| NAV-046 | Servicio sin FIFO físico | BLOCKED | Regresiones backend PASS; falta alta/venta Android real para completar caso. |
| NAV-047 | Navegación no cambia producto | BLOCKED | Fixture y snapshots de conteos iguales; falta comparación persistente de filas de producto tras todos los destinos. |
| NAV-048 | Decants conserva balances | BLOCKED | Consumo ml/stock y backend PASS; falta journey navegable completo. |
| NAV-049 | Sin Decants | BLOCKED | Guards/capability PASS; falta deep link real Android actual. |
| NAV-050 | Fotos sin Productos | PASS | `NavigationPhotoBoundaryTest`, `CatalogMutationAccessTest`, selector real y outbox; sólo permiso explícito, sin CRUD físico. |
| NAV-051 | Pricing/Price health | BLOCKED | Middleware individual aplicado; falta prueba pareada Web/Android y costos. |
| NAV-052 | Importar sin Ajustar | BLOCKED | Límites backend/envelope PASS; falta flujo importación UI Android con clave aislada. |
| NAV-053 | Cargas/Distribución | BLOCKED | Misma cobertura de NAV-029; pendiente recorrido real. |
| NAV-054 | Pedidos/Encargos/Envíos | BLOCKED | Claves independientes en árbol; falta combinaciones de tabs/acciones en UI actual. |
| NAV-055 | Cuenta/Ayuda sin shop_settings | PASS | Igual que NAV-026; endpoint/menú de Ajustes no se concede. |
| NAV-056 | Offline y cambio de tenant | BLOCKED | TenantSync y colas por tienda PASS; falta hacerlo desde shell sin red y back stack completo. |
| NAV-057 | Revocar con app abierta | PASS | `NavigationLiveRevocationTest` contra QA real: Servicios se cierra tras refresh; APIs de replay deniegan permiso revocado. |
| NAV-058 | Tablet/teléfono/Web estrecha | BLOCKED | Capturas/paridad F3 y Web previas; faltan teclado/TalkBack/contraste y layouts nuevos Compras/Cobros. |
| NAV-059 | Rutas/aliases antiguos | BLOCKED | Inventario y tests de aliases PASS; falta smoke por alias en el shell actual. |
| NAV-060 | Atrás no restaura restringida | PASS | Prueba real Servicios → revocación → Cuenta → Atrás. |
| NAV-061 | Revocar durante formulario | BLOCKED | Se impiden mutaciones en Fotos/Compras/Cobros/POS; falta validar conservación segura de borradores en todas las UIs. |
| NAV-062 | Admin plataforma vs owner | BLOCKED | ACL-019 y matrices PASS; falta smoke visual global con ambos actores. |
| NAV-063 | Cliente de otra tienda | BLOCKED | Guards/matriz backend PASS; falta caso E2E desde ruta manipulada en Android actual. |
| NAV-064 | Firma/versión release | BLOCKED | Debug QA verificada; nueva release/publicación no está solicitada, sin incremento/versionado de candidato. |
| NAV-065 | Ayuda/Descargas/Novedades | BLOCKED | Whitelist/alias PASS; falta abrir cada acceso anterior y comprobar no duplicación visual. |
| NAV-066 | Cambiar business_type | BLOCKED | Contratos por perfil PASS; falta cambio controlado de perfil con comparación de datos antes/después. |
| NAV-067 | Conteos Web antes/después | PASS | Fixture QA conserva IDs, stock20/costo120/saldo0/cero facturas y cajas en inspección posterior; sólo certifica estos conteos. |
| NAV-068 | Outboxes y recomposición | BLOCKED | Colas no vacías/UUID probadas en memoria; falta recomposición del shell con colas persistentes y ventas/abonos. |
| NAV-069 | Búsqueda por nombres antiguos | BLOCKED | Servicios/Fotos/Cuenta buscados en UI real; falta Cargas y ambigüedad logística en ambas UIs. |
| NAV-070 | APK anterior/API aditiva | BLOCKED | Contratos de respuesta/decoder legacy PASS; falta ejecutar APK anterior contra el payload actual completo. |

## E2E A..G

| Journey | Estado | Cobertura actual / siguiente comprobación |
|---|---|---|
| A — Perfumería/producto/Decants/venta/caja/reportes | BLOCKED | Regresiones de dominio aprobadas; falta secuencia compartida Web/Android con los mismos datos QA. |
| B — Barbería/servicio/venta/cobro | BLOCKED | Perfiles y servicio sin stock aprobados; falta alta/venta/cobro navegados entre ambos clientes. |
| C — Delegar/restringir/revocar sin reinicio | BLOCKED | Revocación real/Back y API 403 PASS; falta alta del vendedor y ajuste/concesión desde ambos shells en el mismo journey. |
| D — Dos tiendas online/offline | BLOCKED | TenantSync y A→B→A PASS; falta selección offline real con back stack. |
| E — Compra/FIFO/venta/devolución | BLOCKED | Suites backend/Room PASS; falta recorrido UI completo con verificación cruzada. |
| F — Caja/Gastos sin requests ajenos | BLOCKED | Interceptación a nivel de repositorio y tabs aprobada; falta traza HTTP del journey completo en la app actual. |
| G — API/APK anterior | BLOCKED | Contratos aditivos/legacy PASS; falta ejecución del binario previo contra el servidor QA actual. |

## Próximo trabajo de ejecución

1. Completar las pantallas compartidas pendientes de F4 (pricing/price_health, pedidos y lectura de reportes Android).
2. Preparar journeys QA con actores/datos propios, snapshots y outboxes persistentes no vacías; ejecutar A..G sin usar cuentas comerciales.
3. Cerrar accesibilidad, aliases y comparación de filas; después documentación final, commits/push de trabajo y runbook de candidato/rollback.
