# Inventario funcional inicial

Inventario derivado del código vivo de `routes/web.php`, `routes/api.php`, controladores, servicios, tests y las pantallas Android. Los números de referencia del repositorio son Web `32450ff` / Android `1a8ba7f`; el árbol de trabajo contiene cambios QA locales aún no publicados.

| Módulo | Web/API | Android | Persistencia/offline | Permisos/plan | Estado inicial |
|---|---|---|---|---|---|
| Auth, registro y recuperación | Rutas Fortify + API auth | Login, bloqueo de sesión, updater | Token/sesión local | Cuenta activa, verificación y versión mínima | OPERATIVO en tests dirigidos SQLite/MariaDB (27/112); E2E de navegador y dispositivo físico pendiente |
| Tiendas, perfiles y planes | Shop, business profile, capacidades, multi-tienda | selector de tienda y catálogo | API/Room | owner/admin/membership/plan | OPERATIVO en matriz de 23 tipos × Gratis/Básico/Pro; owner Pro con dos tiendas verticales, límite de 3 y catálogos aislados PASS |
| Equipo y vendedores | creación, invitación, permisos, resumen/comisiones | selector de menús y dashboard vendedor | API; cache pendiente de prueba | menu_permissions + policy | OPERATIVO en baseline y E2E-02/ACL-003..006 aislados; matriz verbo × ruta exhaustiva pendiente |
| Catálogo e inventario | productos, imágenes, lotes, ajustes, importación | catálogo, inventario, sincronización | DB/Room/outbox | products/inventory/import | OPERATIVO en tests; offline pendiente |
| POS, cotizaciones y pedidos | venta, quote, conversión, pedido público | Terminal, carrito, cobro | factura/inventario/caja | sales/quotes/orders | OPERATIVO; `SellerFeatureModuleTest` 24/224 y jornadas E2E/concurrencia PASS en QA, E2E Web↔Android amplio pendiente |
| Decants y presentaciones | botella, ml, costo, venta y devolución | filtros, venta y devolución de decant | inventario, ml compartidos y snapshot de costo | capability decants | OPERATIVO en `SellerFeatureModuleTest` y E2E-05/live aislados; devolución Web↔Android amplia pendiente |
| Clientes, crédito y cobros | cuentas, pagos, allocations | clientes/cobros | deuda/pagos | customers/credit/collections | OPERATIVO en tests; journey pendiente |
| Compras y proveedores | suplidores, facturas, cargas, contenedores | módulos de compras | recepción/FIFO/deuda | compras y permisos | OPERATIVO parcial; prorrateo internacional por verificar |
| Caja, gastos y cierre | caja remota/local, gastos, cierre diario | cash/day close | ledger/cash/Room | cash/expenses/finance | OPERATIVO en tests; reconciliación interplataforma pendiente |
| Finanzas y reportes | P&L, FIFO, comisiones, exportación | dashboard/resumen | snapshots históricos | finance/reports | OPERATIVO parcial; auditoría de ocultación pendiente |
| Público, WhatsApp, imágenes y QR | vitrina, pedido, tracking, media | share/PDF/impresión | almacenamiento/URLs | scope por tienda | OPERATIVO parcial; R2/hardware pendiente |
| Administración y soporte | admin, moderación, usuarios, soporte | admin shops | DB | admin global | OPERATIVO en tests; smoke UI pendiente |

## Cobertura cuantitativa inicial

- Laravel route list: 216 rutas; 181 con autenticación; 125 mutaciones totales, 115 mutaciones autenticadas.
- El análisis estático encontró 10 mutaciones públicas/framework/auth sin middleware de dominio adicional; están clasificadas en `ROUTE_ACCESS_MATRIX.md`. No se encontró una mutación comercial privada autenticada sin protección de autorización o alcance de tienda.
- Graphify actualizado en modo `--code-only`: 2.013 nodos, 6.005 relaciones, 216 comunidades. Los documentos e imágenes no se incorporaron por falta de una clave de extracción semántica; se registra como limitación, no como PASS.

## Navegación Android auditada

- `Screen` declara 54 destinos y el `NavHost` registra los 54.
- `ShopSettings` utiliza una ruta parametrizada por sección.
- Las implementaciones compartidas se consideran una decisión de arquitectura, no duplicados: cobros/crédito, compras, pedidos y modos de catálogo/inventario reutilizan componentes con parámetros de contexto.
- Esta cobertura es de inventario estructural. No sustituye las pruebas operativas ni la revisión visual por pantalla.
