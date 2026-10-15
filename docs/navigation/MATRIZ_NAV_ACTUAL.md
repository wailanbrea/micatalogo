# F0 — matriz de navegación actual

Fecha: 14/10/2026. Web `48cbd4a`; Android `ad9ecd7` con los cambios locales protegidos enumerados en README.

## Evidencia y alcance

- Extracción: 237 rutas, 46 claves, 3.199 referencias de enlaces/CTA/tabs, 327 archivos. `routes-before.json` conserva métodos, middleware y ubicación del controlador; `MENU_CURRENT_INVENTORY.csv` conserva las referencias de UI. Las marcas `REVIEW` del CSV no constituyen pruebas de autorización.
- Revisión estructural: sidebar desktop, menú móvil independiente, drawer, NavHost, `Screen`, `SellerMenuService`, middleware, policies y despacho de `SellerFeatureController::moduleData`.
- Capturas reales para owner, manager, sales, accountant, cash, expenses, services, reports, barber y grocery. Web desktop/móvil/móvil abierto; Android teléfono y tablet emulados con login API real. Árbol semántico Android incluido en `evidence/navigation-qa/*.txt`.
- Tablet simulada: 1920×1200 px, densidad 240 (1280×800 dp). Teléfono: 1344×2992 px, densidad 480. Restaurados tamaño/densidad físicos y autofill después del arnés.
- Tests baseline: backend 590 PASS / 6.458 aserciones; Android JVM 115 PASS / 0 skip, 37 reportes; build debug y androidTest PASS. Captura instrumentada: 1/1 PASS en cada formato, 10 roles por ejecución, sin skip/assumption. No equivale a ejecutar la suite instrumentada completa de 176 métodos declarados.
- Cobertura estructural comprobada por script: las 46 claves y las 54 Screen aparecen en esta matriz, sin destinos omitidos. SHA-256 de 75 archivos de evidencia en verification-baseline.json.
- Room27: snapshots read-only antes/después de teléfono y tablet, sin diferencias en conteos de tablas; outboxes vacías. No representa prueba de preservación de una cola no vacía.

## Reglas efectivas comunes

Cada clave mantiene su autorización individual. `visibleForUser` aplica asignación, tienda, capabilities y `enabled_menu_keys`; NULL conserva el comportamiento heredado. Seller con permisos NULL recibe sales/products/printers, no acceso total. Contador recibe finance/reports. Los controles settings/shop_settings/sellers son owner-only para visibilidad; manager conserva las facultades operativas que conceden policies sin heredar esos controles. Admin global y propietario configurado de plataforma son límites diferentes.

Tenant: `scopeBindings`, policies y guards por Shop en Web; `EnsureSellerMenuAccess` oculta tenants ajenos en API; Android usa contexto ShopScope y permisos del shop activo. Las mutaciones vuelven a validar servidor. Capacities/planes proceden de BusinessProfileService y servicios existentes; no hay un permiso por área nueva.

Leyenda: **O** flujo operativo existente; **R** consulta/información; **C** ruta contextual/alias; **U** contenedor UI; **G** control global. `feature:k` significa `seller.shops.feature` con feature=k; rutas Web abreviadas llevan prefijo `seller.shops.` salvo indicación.

## Registro completo de claves persistidas

| Key | Web actual / operación | Android actual | Clase | Destino aprobado y límite a verificar |
|---|---|---|---|---|
| `sales` | pos; feature:sales; checkout e historial/export | `Screen.POS`, `Screen.SalesHistory` | O | Ventas; mantener snapshots, pagos/FIFO y filtros |
| `products` | products.index/create/edit/store/update/archive/restore; combos; categorías | `Screen.Catalog`, `Screen.CatalogCreate`, `Screen.Categories` | O | Catálogo > Productos; no conceder services |
| `printers` | Sin CRUD nativo Web; configuración en app | `Screen.Printers` | O app | Configuración > Dispositivo; sin landing Web ficticia |
| `quotes` | feature:quotes; quotes.show/pdf; crear/convertir | `Screen.Quotes` | O | Ventas > Cotizaciones; UUID/conversión una vez |
| `orders` | feature:orders; confirmar pedido | `Screen.Orders` | O | Pedidos; permiso de confirmar por acción |
| `encargos` | feature:encargos; fechas/estado del pedido | `Screen.Encargos` | O | Pedidos > Encargos; conservar feature |
| `shipments` | feature:shipments; seguimiento/envío | `Screen.Shipments` | O | Pedidos > Envíos; no heredar orders |
| `day_close` | feature:day_close; daily-close.store | `Screen.DayClose` | O | Ventas > Cierre diario; distinto a cerrar sesión caja |
| `containers` | feature:containers; documento logístico | `Screen.Containers` | O | Compras > Contenedores; no recibir stock implícito |
| `loads` | feature:loads; costos/logística | `Screen.Loads` | O | Compras > Cargas; separado de route_loads |
| `suppliers` | feature:suppliers; CRUD/deudas | `Screen.Suppliers` | O | Compras > Proveedores |
| `purchase_invoices` | feature:purchase_invoices; borrador/recibir | `Screen.PurchaseInvoices` | O | Compras > Facturas de compra; lotes una vez |
| `photos` | feature:photos; acciones products.images.* | `Screen.Photos` | O/C | Catálogo > Fotos; compartir composable no concede products |
| `storefront` | storefront; feature:storefront; preferencias | `Screen.Storefront` | O | Mi tienda > Vitrina; editar exige autorización propia |
| `services` | feature:services; formulario compartido sale_unit=service | `Screen.Services` | O/C | Catálogo > Servicios; crear/editar sólo servicios autorizados |
| `price_health` | feature:price_health; diagnóstico/consulta | `Screen.PriceHealth` | R | Catálogo > Precios; costos/margen restringidos |
| `pricing` | pricing.index y feature:pricing; reglas/aprobar | `Screen.AutomaticPrices` | O | Catálogo > Precios; límites Pro y mutación propios |
| `decants` | feature:decants; abrir/preparar/vender/devolver | `Screen.Decants`, `Screen.DecantCreate` | O | Catálogo > Decants; capability efectiva |
| `attributes` | feature:attributes; renombrar/retirar | `Screen.Attributes` | O | Catálogo > Atributos; categorías globales separadas |
| `import` | products.import.*; products.bulk.*; feature:import | `Screen.Import` | O/C | Inventario > Importar; métodos/preview no se eliminan |
| `customers` | customers.index/show/store/update | `Screen.Customers` | O | Clientes y cobros > Clientes; crédito independiente |
| `credit` | feature:credit; cartera | `Screen.Credit` (POS crédito, requiere sales+collections) | R/C | Clientes y cobros > Cartera; resolver discrepancia de alias |
| `inventory` | inventory.index/lots/movimientos/stock | `Screen.Inventory` | O | Inventario > Existencias/FIFO; costos restringidos |
| `collections` | Abonos en dominio customers/API; falta acceso independiente sidebar | `Screen.Collections`, `Screen.CreditLedger` | O/C | Clientes y cobros > Cobros; conservar asignación FIFO |
| `cash` | cash.index/open/movement/close | `Screen.Cash` (FinanceScreen tab4) | O/C | Ventas > Caja; no cargar finanzas ajenas |
| `finance` | business; estados/resultados y export financiero | `Screen.Finance` | R | Finanzas; gastos/caja no autorizados sólo por finance |
| `inventory_adjustments` | feature:inventory_adjustments; conteos/motivos | `Screen.InventoryAdjustments` | O/C | Inventario > Ajustes; mutación independiente |
| `partners` | feature:partners; aportes/retiros con caja | `Screen.Partners` | O | Finanzas > Socios; no confundir colaboradores |
| `expenses` | expenses.index/store/pay/categories | `Screen.Expenses` (FinanceScreen tab3) | O/C | Finanzas > Gastos; no cargar summary sin finance |
| `reports` | feature:reports; exports CSV/XLSX | `Screen.Reports` | R | Reportes independiente; misma redacción en export |
| `commissions` | feature:commissions; snapshots vendedor | `Screen.Commissions` | R | Configuración > Equipo; visibilidad financiera individual |
| `authorizations` | feature:authorizations; approve/reject | `Screen.Authorizations` | O | Configuración > Equipo; decidir exige permiso/rol |
| `returns` | Devoluciones en operación móvil/ventas; falta entrada desktop | `Screen.Returns` | O | Ventas > Devoluciones; contabilidad/stock conservados |
| `routes` | API rutas/cargas; sin feature Web registrado | `Screen.Routes`, `Screen.RouteLoads` | O app | Distribución contextual; routesEnabled y permiso |
| `more` | Sin destino Web; clave heredada | Drawer/barra secundaria, no Screen propio | U | Más abre drawer; no bloquea funciones por key more |
| `settings` | Clave de control; preferencias app sin feature Web | `Screen.Settings` | O app | Configuración > Dispositivo; conservar owner controls |
| `accountant` | feature:accountant; alta/baja colaborador | `Screen.Accountant` | O | Configuración > Equipo; contador no administra acceso |
| `account` | feature:account; datos propios | `Screen.Profile` | R/O app | Mi cuenta secundaria; accesible sin shop_settings |
| `updates` | feature:updates; downloads.index público | `Screen.Updates`, `Screen.DownloadApp` | R/C | Acerca de > Actualizaciones; un acceso visible |
| `help` | feature:help; guías/soporte | `Screen.Help` | R | Ayuda secundaria independiente del negocio |
| `practice` | feature:practice enlaza pantallas reales; sin sandbox real | `Screen.Practice` | R | Rotular como guía/exploración; no afirmar sandbox |
| `support` | feature:support; support.create; conversaciones | `Screen.Support` | O | Ayuda > Soporte; scope cuenta/conversación |
| `metrics` | metrics.index; qr.download/print | `Screen.Metrics` | R | Mi tienda > Métricas y QR |
| `public_catalog` | shops.show público; feature:public_catalog | `Screen.PublicCatalog` | R | Mi tienda > Compartir; no concede edición |
| `shop_settings` | edit/update; menus.edit/update owner | `Screen.ShopSettings` | O | Configuración > Negocio; anti-lockout del editor |
| `sellers` | sellers.index/store/update/destroy | `Screen.Team` | O | Configuración > Equipo; controles owner-only |

## Entradas adicionales, internas y globales

- `Screen.Dashboard` / summary: Inicio, autenticado con tienda accesible; separar resumen permitido de KPIs sensibles.
- `Screen.AdminShops`: `admin_shops`, G. Mantener panel global separado; no inferir privilegio platform-owner por isAdmin.
- CatalogCreate y DecantCreate son internos (enumerados arriba); ShopSettings.routeFor conserva query `section`; Inventory conserva `summary_stock_filter` en savedStateHandle.
- Categorías por tienda bajo products/policy ShopCategory; categorías globales bajo `/admin/categorias` y GlobalCategoryPolicy. Unidades locales/records conservan UUID y ShopScope.
- `Mis tiendas`, onboarding, avatar, logout, cambio tienda, soporte y descargas públicas no necesitan una nueva key de área. Enlaces firmados de PDF y QR se conservan.
- El markup `@if(false)` del sidebar no produce menú actual. No es una segunda fuente visible; revisar antes de retirarlo.

## Hallazgos concretos y regresión asignada

| ID | Evidencia actual | Corrección / pruebas requeridas |
|---|---|---|
| NAV-F0-01 P0 | Cash/Expenses reutilizan FinanceScreen; FinanceViewModel carga summary/gastos/caja indiscriminadamente | F3/F4: tabs y requests por permiso, rol cash-only/expenses-only; contador sin mutaciones |
| NAV-F0-02 | Web móvil usa una lista distinta: reports/services ausentes, sales-only ve Inventario | F2: misma fuente desktop/móvil; comprobación enlaces por rol y acceso directo |
| NAV-F0-03 P0 potencial | Productos/servicios comparten formulario y product policies; index no filtra tipo | F2/F4: services-only/products-only leer/crear/editar/archivar, IDs ajenos y capacidades |
| NAV-F0-04 | Credit Android requiere sales+collections; Web feature:credit usa credit | F3: conservar POS crédito interno y dar destino cartera correcto por credit |
| NAV-F0-05 | Android no comprueba services/public_catalog/metrics en requiredCapability; Web sí | F3/F4: paridad capabilities y permisos cacheados; no fabricar permiso offline |
| NAV-F0-06 | Finance drawer usa canManageShop como alternativa; destino exige finance | F3: contenedor derivado de hijos efectivos; revocación/cambio tienda |
| NAV-F0-07 | FEATURES muestra En preparación para módulos con read models/operaciones reales | F1/F2: clasificar por implementación; practice es guía, no sandbox verificable |
| NAV-F0-08 | Claves printers/routes/settings/more carecen de pantalla Web nativa; collections/returns carecen de accesos visibles desktop | F1/F2: describir destinos app/compatibles; no inventar feature inexistente |
| NAV-F0-09 riesgo por operación | Web agrupa mutaciones de cotizaciones/compras/socios/categorías/formularios bajo can:update,shop sin menu individual; SellerCommerceController no consulta visibleForUser | F4: reproducir URL directa con menú de tienda deshabilitado, comprobar cada acción y delegación; no confundir policy de tienda con permiso del módulo |

Pruebas existentes de referencia: RouteActorTenantMatrixTest (5 PASS, 151 aserciones), RouteProtectionInvariantTest, SellerNavigationPermissionsTest, ShopMenuVisibilityTest, BusinessProfile/Presentation, FinancialApiContractTest, SellerFeatureModuleTest, SellerProductTest, SellerPosTest y journeys Operational*. Android: MiCatalogoMenuAccessTest, contratos, tenant/Room/outboxes y EmulatorJourneyTest. Las matrices NAV-001..070 y E2E A..G aún requieren ejecución específica después de implementar.

## Estado del gate

Inventario de claves/destinos/aliases y baseline local Room/outboxes documentados. `OPERATION_ACCESS_MATRIX.md` enlaza cada ruta con middleware, guards extraídos y referencias literales de tests. `DELEGATED_GUARDS_REVIEW.md` clasifica las 58 mutaciones y las familias de CTA/tabs con su frontera real y brecha pendiente. **F0 aprobado como inventario**, sin destinos desconocidos; los hallazgos de autorización tienen corrección asignada a F2–F4 y permanecen abiertos. No es una aprobación de seguridad/producción.
