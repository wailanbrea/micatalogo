# TODO — Formulario Adaptativo por Tipo de Negocio y Cierre Técnico Integral

Tracking vivo de implementación, pruebas y evidencias para Laravel y Android.

## F0 — Baseline y auditoría sin modificar datos
- [x] Verificar git status, branches y commits en ambos repositorios (`work/navigation-closure-20261015`).
- [x] Identificar entornos (MySQL local, SQLite tests, emulador Android 16 API 36 `emulator-5554`).
- [x] Ejecutar baseline de migraciones y tests sin destrucción de datos.
- [x] Documentar inventario de campos y estado actual en `AUDIT.md`.
- [x] Mapear los 23 tipos de negocio en `BUSINESS_PROFILES_MATRIX.md`.

## F1 — P0: Perfil y capacidades en API para cada tienda
- [x] Reproducir y verificar columnas cargadas en `ShopController::index`.
- [x] Ejecutar migraciones pendientes (`2026_10_21_000000_add_business_profile_to_shops` y posteriores) en local.
- [x] Eliminar restricción de columnas limitadas en `ShopController.php:42` cambiando `$query->get([...])` a `$query->get()`.
- [x] Escribir y verificar test `tests/Feature/ShopProfileApiTest.php` comprobando que `appliance_store` no cae a `general_retail`, respeta archetype `appliances`, `product_fields` y capability overrides.
- [x] Probar aislamiento multi-tienda del mismo owner (`appliance_store` vs `perfume_store`).

## F2 — Fuente única de reglas de formulario adaptativo
- [x] Confirmar contrato en `ProductFormPresentationService.php` (`presentation.product_form`).
- [x] Especificar `allowed_sale_units`, `default_sale_unit`, `default_inventory_unit_key`, `sale_unit_to_inventory_unit`, `inventory_unit_labels`, `inventory_unit_uuids`.
- [x] Documentar `CATALOG_FORM_CONTRACT.md` con esquemas y derivación por perfil.
- [x] Verificar que conversiones especiales (Par, Caja, Metro) se manejen 1:1 sin conversiones ficticias.

## F3 — Formulario Android adaptativo y offline-first
- [x] Verificar consumo de `productForm` en `CatalogHomeScreen.kt`.
- [x] Remover fila fija `unit/bottle/ml` para comercios donde no aplica; mostrar `saleUnitOptions` adaptativas según arquetipo y capacidades.
- [x] Sustituir selector de unidad básico por resumen legible `"Inventario contado en ..."` (`product-auto-inventory-unit`).
- [x] Ofrecer `"Opciones avanzadas"` desplegable para cambiar unidad cuando `allowAdvancedUnitOverride` esté habilitado.
- [x] Mantener soporte completo offline con UUID canónicos deterministas `micatalogo:unit:<shopId>:<key>`.
- [x] Preservar campos existentes en edición sin sobreescribir unidades históricas.

## F4 — Web: paridad con el formulario adaptativo
- [x] Revisar `resources/views/seller/products/form.blade.php` para asegurar consumo de reglas del backend.
- [x] Verificar preselección de unidad automática y opción avanzada para taxonomía.
- [x] Garantizar que no se envíe `unit_uuid` nulo en edición y se preserven unidades históricas.

## F5 — Taxonomía Web/Android, permisos y sincronización
- [x] Corregir discrepancia de guard en `BSPOSMainScreen.kt:1104` (`Screen.Categories.route`), permitiendo acceso a usuarios con `products`, `services` o `decants` en consistencia con `DrawerNavigation.kt`.
- [x] Verificar permisos de taxonomía por alcance (`shared`, `product`, `service`) y rol.
- [x] Validar que Room no pierda operaciones pendientes ni entidades durante sincronización.

## F6 — Reglas de negocio e invariantes críticas
- [x] Invariante FIFO: costos consumidos exclusivamente por ventas/eventos reales.
- [x] Invariante Decants: apertura reserva ml sin contarlo como venta ni generar COGS anticipado.
- [x] Invariante Moneda: cálculos en centavos enteros sin redondeos flotantes.
- [x] Invariante Servicios: venta de servicios sin movimientos de stock físico.
- [x] Invariante Multi-tienda: aislamiento estricto por `shop_id`.

## F7 — Matriz de verificación (QA-001..QA-048) y E2E Journeys (A..H)
- [x] Documentar y ejecutar matriz QA-001 a QA-048 en `QA_MATRIX.md`.
- [x] Documentar recorridos E2E en `E2E_JOURNEYS.md`.
- [x] Ejecutar verificaciones automatizadas en backend y Android.

## F8 — Regresión y verificación de navegación
- [x] Verificar rutas NAV de catálogo, categorías, servicios, decants y settings.
- [x] Confirmar ausencia de regresiones en permisos de roles (owner, seller, accountant).

## F9 — Verificaciones finales, commits y reporte
- [x] Ejecutar tests finales en Laravel y Android.
- [x] Compilar APK debug (`.\gradlew.bat assembleDebug`).
- [x] Generar `FINAL_REPORT.md` con métricas exactas.
- [x] Commits y push a `work/navigation-closure-20261015` en ambos repos.
