# Cobertura de pantallas y acciones

La navegación Compose contiene pantallas para auth, dashboard/resumen, Terminal/POS, cotizaciones, pedidos, productos, inventario, decants, clientes, crédito/cobros, compras, proveedores, cargas, contenedores, facturas, caja, gastos, cierre diario, finanzas, reportes, equipo, configuración, soporte, rutas, impresora, actualización y administración.

## Inventario real de navegación Android

La auditoría de `BSPOSNavigation.kt` y `BSPOSMainScreen.kt` confirma:

- **54 destinos declarados** en `Screen`.
- **54 destinos registrados** en el `NavHost`.
- `ShopSettings` está registrado con la ruta parametrizada `shop_settings?section={section}`; no es una pantalla huérfana.
- Hay destinos que comparten implementación intencionalmente: `Collections` y `CreditLedger` usan cobros; `Photos`, `Services`, `Import` e `InventoryAdjustments` reutilizan el espacio de catálogo/inventario con un modo inicial; `Orders`, `Encargos` y `Shipments` reutilizan el espacio de pedidos con una capacidad distinta.
- La existencia de una ruta registrada no equivale a PASS funcional o visual. Cada destino aún debe validar carga, vacío, error, reintento, permisos, back, scroll, rotación y acciones de escritura según el gate inferior.

## Estado inicial

- Compilación y unit tests de presentación: PASS.
- Smoke instrumentado en emulador debug: la variante principal ejecutó 110 casos PASS y omitió 3 por assumption al no suministrar fixtures live (`OK (113 tests)`); `com.bsolutions.micatalogo.offlinecheck` conserva su última ejecución de 109 PASS y 3 omitidos (`OK (112 tests)`). Cubrió datos/sincronización, operaciones financieras, pantallas críticas, el no-replay de una venta bloqueada tras revocación de permisos, la conservación de tienda original en outbox y el modo financiero de solo lectura del Contador. El runner seguro exige un destino emulador y no permite seleccionar el Samsung.
- No se declara PASS visual solo por compilar.
- Deben recorrerse por pantalla: carga, vacío, error, reintento, búsqueda, filtro, paginación, scroll, back, drawer, permisos, cambio de tienda y rotación.
- Terminal y Cobrar tienen regresiones funcionales en tests; falta captura comparativa con la referencia Puntto.
- POS web: smoke QA ejecutado en panel aislado; búsqueda/catálogo, carrito, modal de cobro, contado, confirmación, factura y decremento de stock PASS después de F-039. El recorrido visual completo y la comparación con Puntto siguen pendientes.
- Smoke autenticado QA: con un owner Pro sintético y tres productos se cargaron panel, Resumen, Terminal, Cotizaciones, Inventario, Cierre de día, Compras, Ganancias, Configuración, Mi tienda y vitrina; se agregó un producto al carrito, se abrió el modal de cobro y se agregó un producto a una cotización sin confirmar mutaciones. Playwright reportó 0 errores de consola. Se conservaron capturas en `output/playwright/qa-authenticated-terminal.png`, `qa-authenticated-inventario.png`, `qa-authenticated-finanzas.png` y `qa-authenticated-cobro.png`; el fixture fue eliminado al terminar.
- Navegación web: 53 destinos reales del panel recorrieron carga y título con HTTP 200; Configuración de tienda y Crear tienda quedaron sin errores Alpine tras F-040. Falta todavía validar exhaustivamente estados de error, permisos, rotación y acciones de escritura de cada pantalla.
- Entradas públicas y límites: en servidor QA local, landing/login/registro/términos/privacidad respondieron 200; `/panel` y `/admin` anónimos redirigieron a login, la API protegida respondió 401 y una tienda inexistente 404. Esto cubre fronteras de navegación, no reemplaza el recorrido autenticado completo por cada rol.
- Inventario y vendedor tienen cambios recientes; requiere smoke UI con debug en emulador y release firmado en físico cuando se autorice instalar.

## Gate de revisión

Una pantalla solo pasa cuando el botón abre su destino, el estado se conserva al volver, el permiso se valida también por API y no hay doble encabezado, clipping, loader infinito o saldo cero ficticio.
