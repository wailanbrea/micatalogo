# F0 — revisión de controles delegados y acciones compartidas

Las 58 mutaciones marcadas por el escáner están clasificadas a continuación. La ausencia de middleware menu no es un fallo por sí sola. Se distingue la frontera actor/tenant existente de las brechas por módulo identificadas. Esta revisión cierra el inventario; la resolución y reproducción por permiso de los hallazgos es el gate F4.

| Familia / acciones congeladas | Control real revisado | Evaluación y regresión asignada |
|---|---|---|
| AdminShopController.update; AdminModerationController.toggleShopStatus | isAdmin en API; grupo admin Web | Administración global intencional, distinta de owner de tienda; RouteActorTenantMatrixTest |
| AdminMenuVisibilityController.update | propietario configurado de plataforma, no admin común | Frontera específica; ACL-019 actualizado y PASS |
| ShopController.storeSeller/updateSellerMenus | canManageMenuVisibility=ownsShop; seller.shop_id; quota | Control owner-only delegado; ShopMenuVisibilityTest y ShopAdministrationMobileOperationTest |
| API AccountantAccessController.store | owner o isAdmin, validación/grant delegado | Diferencia frente a controls owner-only de UI; probar admin global y enabled_menu_keys en F4 |
| SupportChatController.store | soporte owner o canSellAtShop; privacidad por requester/assigned en authorizeConversation | Dominio cuenta/conversación; no agregar shop_settings; SupportChatTest |
| SellerAuthorizationController.storeApi | tenant canSellAtShop; requester; solicitud pendiente por acción | Pedir autorización no concede aprobar; no exigir authorizations para pedir |
| SellerAuthorizationController.approve/reject/approveApi/rejectApi | applyDecision: shop_id + can(update,shop), estado pendiente | Falta clave authorizations en rutas directas; envelope sí la verifica. Registrar paridad en F4 |
| API InventoryImportController.preview/store/confirm | authorizeImport: canManage + bulk_import; sesión shop_id | Control delegado real, pero falta import efectivo. Envelope usa inventory: discrepancia adicional por operación |
| MobileOperationController.store | tenant antes de validar; MobileOperationService dispatcher | Tipos financieros y online verifican sus keys; administración verifica key+owner en destino. Producto/records/routes requieren update,shop pero no key propia: brecha F4 |
| FinanceReportController.closeDay | authorizeDayClose mutating: canManage o day_close; contador sin mutación | Manager/owner pueden operar con menú oculto; lectura exige finance aunque destino day_close es independiente. Paridad F4 |
| PublicOrderController.store | tienda activa; productos publicados en shop; stock y precios snapshots; request validado | Ruta pública deliberada, sin confirmar venta implícita; PublicOrderAndAttributesTest y journey público |
| SellerInvitationController.activate | hasValidSignature + hash email + asignación activa | Infraestructura de invitación, no permiso de módulo; SellerInvitationTest |
| SellerShopController.destroy | can:delete,shop / ShopPolicy owner; contexto tienda | Administración de ciclo de vida, no hija operativa; SellerShopTest |
| SellerBusinessController.bulkRule/rule/approve | can:update,shop/product en rutas; requirePro; SKU del tenant por scopeBindings | Falta pricing por operación; política de plan sí existe. F4 + FifoPricingTest |
| SellerFeatureController.updateAttribute | can:update,shop y recurso del tenant | Falta attributes efectivo en URL de mutación; F4 + SellerFeatureModuleTest |
| SellerCommerceController.storeQuote/convertQuote/storeSupplier/storeSupplierDebt/previewPurchaseInvoice/storePurchaseDocument/receivePurchaseDocument/storePartner/storePartnerTransaction | can:update,shop; recursos tenant; validación/stock/idempotencia/caja en servicios | No verifica visibleForUser; faltan quotes/suppliers/purchase_invoices o loads/containers según tipo/partners. F4 por acción, sin fusionar permisos de compras |
| ShopMemberController.store/destroy | can:update,shop; quota; member.shop_id | manager puede llegar por URL al equipo oculto: revisar owner-only sin conceder por agrupación settings |
| AccountantAccessController.store/destroy | can:update,shop; AccountantAccessService recurso tenant | Alta/baja contador no es consulta financiera; exigir accountant efectivo en F4 |
| ShopPaymentAccountController.store/update/destroy | can:update,shop; paymentAccount.shop_id | Ajuste negocio; revisar shop_settings efectivo y owner-only en F4 |
| ShopCategoryController.store/update/destroy | can:update,shop y ShopCategoryPolicy owner/admin; category tenant | Separado de GlobalCategoryPolicy. Falta products efectivo en mutación; no inventar key categories |
| SellerProductController.store/update/destroy/restore | ProductRequest, policies y Shop update; capacidad services/decants al crear; cuota | Falta autorización por tipo products/services y menú efectivo. Preservar política de mutación mientras se define paridad de formularios |
| SellerProductController.uploadImage/importRemoteImage/destroyImage; ProductCatalogMediaController.resolve/use | policy producto; product.shop_id; límites imágenes | Verificar photos vs products/services de recurso en F4; no conceder producto ajeno desde tab Fotos |
| SellerBulkProductController.store | can:update,shop; bulk_import y cuota | Falta import efectivo; category id validado por existencia no tenant en batch: incidencia específica de importación para F4 |
| SellerInventoryImportController.preview/store | canManage + bulk_import; sesiones scoped al shop | Falta import efectivo en ruta Web; preservar preview/confirm/legacy rows |
| SellerInventoryController.recordRestock/openBottle/adjustStock | can:update,shop/product, scopeBindings; servicios stock | Falta inventory/decants/inventory_adjustments según operación; no autorizar ajuste por tener sólo inventory |

## Dispatcher móvil: frontera antes del ACK

- FinancialMobileOperationService: cash_* → cash; expense_* → expenses; recurso scoped; validación reutiliza controladores.
- OnlineMobileOperationService: quotes/orders/suppliers/partners/day_close independientes; compras usa containers, import usa inventory. Se requiere corregir selección por tipo de documento y permiso import sin romper UUID/replay.
- ShopAdministrationMobileOperationService: shop_settings/sellers/authorizations por tipo y owner checks en controladores. Persistencia idempotente se mantiene.
- MobileOperationService: producto/stock/records/routes no deben usar update,shop como sustituto de clave individual. Revalidar también replay ya registrado después de revocación: un ACK previo no crea nuevo acceso.

## CTA, tabs y pantallas compartidas

Los 3.199 registros del CSV son referencias de sintaxis, no 3.199 módulos. Se agrupan por la pantalla y dominio de la matriz principal. Para cada familia se preservan lectura, alta/edición, selección de recurso, confirmación, exportación y links contextuales; la fuente concreta se conserva por archivo/línea en el CSV.

- Catálogo/Services/Photos/ProductForm: parámetro initialServiceMode separa presentación, no autorización; producto persistido decide permiso en edición. Categories comparte acceso products; unidades/records conservan UUID. Riesgo NAV-F0-03.
- Orders/Encargos/Shipments: feature distinto y guard distinto por ruta; confirmar requiere operación apropiada, no el área orders como permiso agregado.
- Inventory/Import/Adjustments: flags comparten composable; reposición/ajuste/import/FIFO conservan límites propios y costo visible sólo con facultad apropiada.
- Finance/Cash/Expenses: initialTab no protege tabs ni cargas; NAV-F0-01 P0. Botones de mutación y lecturas requieren controles separados, incluido contador.
- Collections/CreditLedger/Credit: aliases de cobranza frente a POS crédito; NAV-F0-04. Registrar abono no habilita catálogo/POS ni editar límite de crédito.
- Compras: documentos logísticos vs facturas vs cargas de ruta; mantener el tipo al escoger permiso y recibir stock una vez.
- FeatureScreen/reportes: acciones y filas de API deben redaccionar costos y exports igual que Web; reportes no exige finance para existir.
- Storefront/Metrics/PublicCatalog: ver/compartir y cambiar apariencia no comparten permiso de mutación.
- Settings/ShopSettings/Team: preferencias locales, negocio, equipo, owner/platform-admin separados; preserve anti-lockout.
- Dashboard, avatar, Ayuda, cambio tienda, logout, descargas y soporte no conceden operaciones comerciales nuevas.

## Cierre del gate F0

Auditados registro y todos los destinos; fronteras delegadas clasificadas; baseline conservado y verificado. **F0 aprobado como inventario y línea base**, con hallazgos abiertos explícitos para F2–F4. No significa aprobación de seguridad ni de producción. Los gates siguientes necesitan pruebas por operación y resolver P0; no se sustituyen por el PASS baseline.
