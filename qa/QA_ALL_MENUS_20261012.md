# MiCatalogo — QA integral de menús, lógica y pantallas

Fecha de ejecución: **2026-10-12**  
Checkout backend: `ec3322c`  
Checkout Android: `0047605`

## Resultado ejecutivo

| Capa | Resultado | Evidencia |
|---|---|---|
| Backend/Web/API | **PASS** | `576 tests`, `6370 assertions`, SQLite en memoria, `41.94 s` |
| Android unitario y compilación QA | **PASS** | `:app:testDebugUnitTest`, `assembleDebug` y `assembleDebugAndroidTest` terminaron correctamente |
| Android instrumentado | **PASS con bloqueos explícitos** | `175 tests OK` en el emulador `emulator-5554`; 7 líneas de `AssumptionViolatedException` corresponden a fixtures live no configurados |
| Navegación Android | **PASS estructural** | 54 destinos declarados y 54 registrados; `ShopSettings` usa una ruta parametrizada y no es huérfana |
| Producción | **SIN ESCRITURAS** | No se ejecutaron migraciones, seeders, altas, bajas ni pruebas destructivas |

El resultado Android se considera **PASS del lote local**, pero no un PASS E2E completo: las pruebas que necesitan un backend/fixture aislado se mantienen como `ASSUMED/BLOCKED_ENV`, nunca como aprobadas silenciosamente.

## Menús auditados

La siguiente es la lista funcional que debe permanecer cubierta. La presencia de una ruta no se considera suficiente: cada módulo debe validar carga, estado vacío, error/reintento, navegación de regreso, permisos, scroll y persistencia/sincronización cuando corresponda.

### Operación

- **Resumen**: indicadores, primera venta, pedidos visibles y estado de sincronización.
- **Terminal**: detalle/mayoreo, búsqueda, filtros, carrito, cantidades limitadas por stock y cobro.
- **Ventas**: resumen, filtros, contado/crédito/mixto, anuladas y acceso a comprobantes.
- **Cotizaciones**: catálogo compacto, cliente existente/genérico, carrito, vigencia y conversión idempotente.
- **Pedidos**: pendientes/confirmados, detalle, cliente a crédito, pago mixto, límite de crédito y confirmación.
- **Encargos**: lectura y estados del módulo.
- **Envíos**: pendientes/confirmados y totales.
- **Cierre de día**: esperado, contado, diferencia y cierre sin duplicar sesión.

### Compras

- **Contenedores**, **Cargas**, **Suplidores** y **Facturas**: formularios, validación, estados vacíos y persistencia.

### Catálogo

- **Inventario**: stock, lotes, ajustes, costos históricos y actualización inmediata.
- **Fotos**: selección y resolución de imágenes del catálogo.
- **Mi tienda**: vitrina, personalización, guías y vista pública.
- **Servicios**: productos/servicios según perfil del negocio.
- **Salud de precios** y **Precios automáticos**: margen, redondeo y límites de autorización.
- **Decants**: botellas fuente, presentaciones, frascos, ml, precio y sincronización.
- **Marcas y atributos**: altas, edición y aislamiento por tienda.
- **Importar**: Excel/PDF, sesiones, duplicados, errores y reintento.

### Cobros

- **Crédito**: clientes, límite, saldo, deuda y abonos.
- **Clientes**: lista única de la tienda, alta, edición, selección y sincronización.

### Finanzas

- **Ganancias**: ventas, costo FIFO, margen, períodos y detalle completo en modal.
- **Ajustes de inventario**: motivos, cantidades numéricas, auditoría y rollback.
- **Gastos**: alta, categorías, pago parcial y caja.
- **Socios**: movimientos enlazados a caja y permisos.

### Análisis

- **Reportes**: filtros, métricas, exportaciones y protección de datos financieros por rol.

### Equipo

- **Comisiones**: vendedor, venta, fecha/hora y comisión calculada.
- **Autorizaciones**: solicitudes, aprobación/rechazo y acceso por permiso.

### Ajustes y soporte

- **Configuración de la tienda** (`shop_settings`): negocio, apariencia, contacto, horario y menú de tienda.
- **Equipo y vendedores** (`sellers`): vendedores, roles y asignaciones.
- **Ajustes de la app** (`settings`): preferencias del dispositivo y sincronización.
- **Contador** (`accountant`): lectura financiera limitada.
- **Mi cuenta** (`profile`), **Novedades** (`updates`), **Ayuda** (`help`), **Practicar sin miedo** (`practice`) y **Soporte** (`support`).
- **Todas las tiendas** (`admin_shops`): solo administración de plataforma.

También se revisaron destinos transversales que no deben perderse: **Caja**, **Devoluciones**, **Impresoras**, **Rutas**, **Métricas**, **Compartir catálogo**, **Descargar app**, **Categorías**, **Crear producto** y **Crear decant**.

## Qué probó cada capa

### Backend/Web/API

La suite completa ejecutada fue:

```text
vendor/bin/pest.bat --log-junit qa/artifacts/backend-full-qa-20261012.xml
576 passed (6370 assertions)
```

Incluye cobertura de módulos de vendedores, menú y permisos, aislamiento por tienda, planes/capacidades, importación, inventario/lotes, FIFO, POS, cotizaciones, pedidos públicos, crédito, abonos, caja, gastos, ganancias, decants, devoluciones, equipo/comisiones, compras y jornadas operativas.

El inventario actual del backend devuelve **233 rutas** con `php artisan route:list --json`; esto incluye API, web, autenticación y destinos derivados, por lo que no se confunde el número de rutas HTTP con el número de menús visibles.

Pruebas de referencia principales:

- `SellerFeatureModuleTest`
- `SellerNavigationPermissionsTest`
- `ShopMenuVisibilityTest`
- `RouteActorTenantMatrixTest`
- `RouteProtectionInvariantTest`
- `OperationalRolePositiveMatrixTest`
- `OperationalMultiShopJourneyTest`
- `OperationalTeamJourneyTest`
- `OperationalJourneyTest`
- `OperationalPublicOrderJourneyTest`
- `OperationalDecantReturnJourneyTest`
- `FifoPricingTest`

### Android

El lote instrumentado cubre persistencia Room, sincronización/outbox, tenant activo, ventas contado/crédito/mixto, cobros, crédito, caja, devoluciones, inventario, decants, cotizaciones, pedidos, clientes, dashboard, finanzas, cierre, importación, actualización, PDF y componentes visuales críticos.

El runner reproducible es:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\qa\run_android_emulator_tests.ps1 `
  -Serial emulator-5554 `
  -OutputFile .\qa\artifacts\android-full-qa-20261012-final2.txt
```

El runner ahora:

1. Rechaza cualquier destino físico (`ro.kernel.qemu` distinto de `1`).
2. Guarda la salida completa si se indica `-OutputFile`.
3. Falla si aparece `FAILURES!!!`, un crash o no existe un resumen `OK (N tests)`.
4. Advierte cuando hay `AssumptionViolatedException`.
5. Permite convertir esas omisiones en fallo de CI con `-FailOnAssumptions` cuando los fixtures aislados estén disponibles.

La ejecución final produjo:

```text
OK (175 tests)
```

El caso `creatingCustomerKeepsTheMixedOrderConfirmationOpen` falló una vez dentro del lote, pasó en ejecución aislada (`OK (1 test)`) y volvió a pasar en el lote completo final. Queda registrado como intermitencia a vigilar, no se oculta ni se eleva a un fallo reproducible del producto.

## Bloqueos y límites declarados

- Los casos live de importación, decants y POS que requieren servidor/fixtures aislados quedaron omitidos por assumptions. Para cerrarlos hay que levantar el entorno QA aislado y ejecutar el runner con esos parámetros; no se deben probar escribiendo en producción.
- No se declara validación visual pixel a pixel contra Puntto en esta corrida. La prueba instrumentada valida estructura, textos, acciones, scroll y estados; la comparación visual requiere capturas controladas por pantalla y viewport.
- No se instaló ni publicó una release en el Samsung durante esta auditoría. El debug solo se usó en el emulador, como exige la regla del proyecto.

## Corrección de seguridad del proceso

Una primera orden genérica de Gradle detectó el Samsung conectado e intentó resolver una instalación debug; Android la rechazó antes de instalar por incompatibilidad de firma (`INSTALL_FAILED_UPDATE_INCOMPATIBLE`). La ejecución se interrumpió y el Samsung quedó sin cambios. Desde entonces, el runner exige un emulador y la corrida válida se hizo únicamente en `emulator-5554`.

## Estado final

- QA backend completo: **PASS**.
- QA Android local: **PASS**, con assumptions live explícitos.
- Runner de Android endurecido: **APLICADO**.
- Datos de producción: **INTACTOS**.
- Despliegue VPS/APK: **NO REALIZADO**, porque esta tarea pidió corregir y ejecutar QA, no publicar.
