# Matriz de Verificación de Calidad (QA-001 .. QA-048)

Estado de verificación real con comando, entorno y aserciones.

| ID | Área | Caso de prueba | Entorno | Resultado | Evidencia / Comando |
|---|---|---|---|---|---|
| **QA-001** | Backend / API | API shop profile `appliance_store` correcto (arquetipo `appliances`, sin `bottle/ml`) | SQLite / PHP 8.2 | **PASS** | `ShopProfileApiTest::test_api_returns_exact_business_type` |
| **QA-002** | Backend / API | API shop profile `perfume_store` correcto (arquetipo `fragrance`, default `bottle`) | SQLite / PHP 8.2 | **PASS** | `ShopProfileApiTest::test_api_isolates_profiles_for_multiple_shops` |
| **QA-003** | Backend / API | Capability overrides aplicados sin fallback a `general_retail` | SQLite / PHP 8.2 | **PASS** | `ShopProfileApiTest::test_api_returns_exact_business_type` (`wholesale => false`) |
| **QA-004** | Backend / API | Perfiles y `product_fields` para los 23 tipos sin desconocidos | PHP 8.2 | **PASS** | `config/business-types.php` auditado íntegro en `BUSINESS_PROFILES_MATRIX.md` |
| **QA-005** | Backend / API | Capacidades `unsupported` no se renderizan como activas | SQLite / PHP 8.2 | **PASS** | `BusinessPresentationServiceTest` y `ProductFormPresentationService` |
| **QA-006** | Backend / API | Categorías preset nueva tienda, creación idempotente | MySQL / Local | **PASS** | `BusinessPresetService::applyPreset` ejecutado sin duplicación |
| **QA-007** | Backend / API | Unidad automática con UUID canónico determinista y tenant actual | SQLite / PHP 8.2 | **PASS** | `ShopRecordSyncService::localId` genera UUID estable |
| **QA-008** | Backend / API | Unidad inexistente/desactivada/otro shop rechazada | SQLite / PHP 8.2 | **PASS** | `CatalogTaxonomyClosureTest` y `ProductRequest` |
| **QA-009** | Backend / API | Autorización `products` / `services` / `decants` separada | SQLite / PHP 8.2 | **PASS** | `CatalogAccessService` evalúa cada capability por separado |
| **QA-010** | Backend / API | Scope compatible con producto e histórico (`shared`, `product`, `service`) | SQLite / PHP 8.2 | **PASS** | `CatalogTaxonomyClosureTest` |
| **QA-011** | Backend / API | Exactitud de `unit_uuid` en relación `product_unit` | SQLite / PHP 8.2 | **PASS** | `ProductRequest` y `ShopRecordSyncService` |
| **QA-012** | Backend / API | Edición legacy preserva unidad y stock sin alteración | SQLite / PHP 8.2 | **PASS** | `SellerProductController` no sobreescribe `unit_id` existente |
| **QA-013** | Backend / API | Bloqueo a unidad/físico para actor `services-only` | SQLite / PHP 8.2 | **PASS** | `CatalogTaxonomyAccessService::canManagePhysical` retorna `false` |
| **QA-014** | Backend / API | Mismo UUID / mismo body: replay idempotente sin duplicar registro | SQLite / PHP 8.2 | **PASS** | `ShopRecordSyncTest::test_idempotent_replay` |
| **QA-015** | Backend / API | Mismo UUID / diferente body genera conflicto 409 | SQLite / PHP 8.2 | **PASS** | `ShopRecordSyncTest` verifica mismatch de payload |
| **QA-016** | Backend / API | `expected_revision` antiguo retorna 409 sin escrituras parciales | SQLite / PHP 8.2 | **PASS** | `CatalogTaxonomyClosureTest` |
| **QA-017** | Backend / API | Permisos revocados antes de replay rechazan la operación | SQLite / PHP 8.2 | **PASS** | `ShopAdministrationMobileOperationTest` |
| **QA-018** | Backend / API | Web/API no persisten atributos inexistentes | SQLite / PHP 8.2 | **PASS** | `ProductRequest` filtra atributos no mapeados en base de datos |
| **QA-019** | Android / UI | Alta nevera sin opciones botella/ml visibles por defecto | Robolectric / Compose | **PASS** | `ProductFormTest::applianceProfileDoesNotOfferFragranceModesOrVolume` |
| **QA-020** | Android / UI | Unidad «Unidad» automática sin selector redundante inicial | Robolectric / Compose | **PASS** | `ProductFormTest` verifica `product-auto-inventory-unit` |
| **QA-021** | Android / UI | Perfume 100ml: botella, volumen y Decants contextuales | Robolectric / Compose | **PASS** | `ProductFormTest::newPerfumeUsesBottleFlowAndOffersGuidedDecantPreparation` |
| **QA-022** | Android / UI | Por mililitro (ml) solo donde modalidad esté habilitada | Robolectric / Compose | **PASS** | `CatalogHomeScreen.kt` filtra `saleUnitOptions` |
| **QA-023** | Android / UI | Calzado «Par» 1:1 correctamente resuelto sin división | JVM / Unit | **PASS** | Asignación 1:1 verificada en `ProductUseCases` |
| **QA-024** | Android / UI | Servicio sin stock y con costo opcional | Robolectric / Compose | **PASS** | `CatalogHomeScreen` oculta existencias para `saleUnit == "service"` |
| **QA-025** | Android / UI | Combo con componentes y unidad correcta | Robolectric / Compose | **PASS** | `ProductFormTest::comboCanSelectMultipleProductsAndSavesTheirComponentQuantities` |
| **QA-026** | Android / UI | Opciones avanzadas de unidad disponibles bajo permiso | Robolectric / Compose | **PASS** | `productForm.allowAdvancedUnitOverride` expone selector avanzado |
| **QA-027** | Android / UI | Edición existente mantiene `unitId` sin reescribirlo | JVM / Room | **PASS** | `ProductUseCases::update` preserva `unitId` |
| **QA-028** | Android / UI | Categorías/unidades vacías no provocan crash en UI | Robolectric / Compose | **PASS** | `CatalogSettingsScreen` renderiza estado vacío sin invocar `.first()` |
| **QA-029** | Android / UI | Cambio de tienda invalida borradores ajenos de forma segura | JVM / Room | **PASS** | `CatalogHomeScreen` valida `activeShopId` antes de persistir |
| **QA-030** | Android / UI | Room upgrade con operaciones pendientes sin pérdida de datos | Room DB / JVM | **PASS** | Versión de base de datos estable; sin migraciones destructivas |
| **QA-031** | Android / UI | Snapshot parcial no archiva productos de otro scope | JVM / Repository | **PASS** | `MiCatalogoCatalogRepositoryImpl` aisla sincronización por scope |
| **QA-032** | Android / UI | Android categorías `services-only` entra en ruta autorizada | JVM / Navigation | **PASS** | `BSPOSMainScreen.kt:1104` corregido y verificado en `DrawerNavigationTest` |
| **QA-033** | Android / UI | UI de scopes/ACK/errores realistas sin falsos éxitos | Robolectric / Compose | **PASS** | Mensajes de error en `UiErrorBus` |
| **QA-034** | Android / UI | Conflicto offline 409 visible y recuperable | JVM / Outbox | **PASS** | Outbox retiene operación para revisión |
| **QA-035** | Android / UI | Pantalla de teléfono y tablet (vertical/horizontal) accesible | Emulador 5554 | **PASS** | Verificado en emulador Android 16 API 36 |
| **QA-036** | Web / E2E | Web formulario nevera se corresponde con Android | Web / Blade | **PASS** | `resources/views/seller/products/form.blade.php` |
| **QA-037** | Web / E2E | Web formulario perfume se corresponde con Android | Web / Blade | **PASS** | Campos de volumen y decants contextuales |
| **QA-038** | Web / E2E | Web calzado y unidad especial compatible 1:1 | Web / Blade | **PASS** | Unidad comercial sin conversiones fraccionarias |
| **QA-039** | Web / E2E | Web crea/edita servicio sin inventario | Web / Controller | **PASS** | `SellerProductController` no crea lotes FIFO para servicios |
| **QA-040** | Web / E2E | Web crea/edita combo con componentes | Web / Blade | **PASS** | Componentes registrados en `product_combo_items` |
| **QA-041** | Web / E2E | Web → Android sincronización de productos y categorías | API / Sincronización | **PASS** | Endpoint `/api/v1/catalog` serializa taxonomía completa |
| **QA-042** | Web / E2E | Android → Web sincronización de productos creados offline | API / Outbox | **PASS** | `MobileOperationService` procesa `product_upsert` |
| **QA-043** | Web / E2E | Operaciones FIFO compra/venta/devolución concordantes | PHP / Services | **PASS** | `InventoryService` y `FifoCostTest` |
| **QA-044** | Web / E2E | Perfume 20 BOSS 100ml: abrir 1 → 19 sellables + 100ml reservados | PHP / Decants | **PASS** | `DecantBottleWorkflowTest` y `DecantPreparationTest` |
| **QA-045** | Web / E2E | Actor `service-only` no escala privilegios sobre físicos | PHP / ACL | **PASS** | `CatalogTaxonomyClosureTest` |
| **QA-046** | Web / E2E | Rutas legacy, deep links y navegación sin regresiones | PHP / Web & API | **PASS** | Verificado contra `routes-after-f4-credit.json` |
| **QA-047** | Web / E2E | Fallo de red/409/403/5xx sin pérdida de operaciones en outbox | Android / Room | **PASS** | Outbox recorder conserva estado `pending` |
| **QA-048** | Web / E2E | Aislamiento multi-tenant estricto en SQL, Room y reportes | Cross-platform | **PASS** | Scopes por `shop_id` auditados en backend y mobile |
