# AUDIT — Cierre Técnico del Formulario Adaptativo

Registro de hallazgos, diagnóstico de causas raíz, decisiones de diseño e impacto de pruebas.

---

## Hallazgo P0-1: Restricción selectiva de columnas en `ShopController::index`
- **Archivo y línea**: `app/Http/Controllers/Api/V1/ShopController.php:42`
- **Código anterior**:
  ```php
  $shops = $query->get(['id', 'user_id', 'public_id', 'name', 'slug', 'enabled_menu_keys'])
  ```
- **Causa raíz**: Al especificar una lista manual de 6 columnas en el `get(...)`, si el constructor no tenía columnas previas se omitían `business_type`, `business_capability_overrides`, `business_profile_version`, `operational_settings` y `business_hours`. Como consecuencia, `$shop->business_type` evaluaba a `null`, provocando que `BusinessProfileService::normalizeType(null)` retornara `'general_retail'`, anulando el tipo real de negocio y borrando todos los capability overrides para clientes móviles.
- **Decisión**: Cambiar a `$shops = $query->get()`, permitiendo que el modelo Eloquent cargue las columnas de la tabla de forma íntegra y delegando la proyección segura de salida a la función `map()`, que define explícitamente el contrato JSON retornado sin exponer datos sensibles.
- **Prueba asociada**: `tests/Feature/ShopProfileApiTest.php` verifica que `appliance_store` reporte arquetipo `appliances`, campos de electrodomésticos y overrides de capacidades sin caer en `general_retail`.

---

## Hallazgo F5-1: Discrepancia de guard en ruta de Categorías y Unidades
- **Archivo y línea**: `app/src/main/java/com/example/bspos/presentation/main/BSPOSMainScreen.kt:1104` vs `DrawerNavigation.kt:47`
- **Código anterior**:
  - `DrawerNavigation.kt:47`:
    ```kotlin
    "categories" -> return canSeeMenu("products") || canSeeMenu("services") || canSeeMenu("decants")
    ```
  - `BSPOSMainScreen.kt:1104`:
    ```kotlin
    composable(Screen.Categories.route) {
        RestrictedMenuDestination(canSeeMenu("products"), navController) {
            CatalogSettingsScreen()
        }
    }
    ```
- **Causa raíz**: El menú lateral (Drawer) permitía la navegación a la ruta `categories` para usuarios con permisos en `products`, `services` o `decants`. Sin embargo, la definición del composable en `BSPOSMainScreen` restringía el destino exclusivamente a `canSeeMenu("products")`. Un usuario con permisos solo de `services` veía la opción en el menú pero al pulsarla era bloqueado con pantalla de acceso restringido.
- **Decisión**: Actualizar el guard de `Screen.Categories.route` en `BSPOSMainScreen.kt` a:
  ```kotlin
  RestrictedMenuDestination(canSeeMenu("products") || canSeeMenu("services") || canSeeMenu("decants"), navController)
  ```
- **Prueba asociada**: `DrawerNavigationTest.kt` (`servicesOnlyCanOpenTaxonomyWithoutUnlockingPhysicalCatalog`).

---

## Hallazgo F2-1: Unidades de inventario y conversiones no soportadas
- **Archivos**: `app/Services/ProductFormPresentationService.php`, `resources/views/seller/products/form.blade.php`, `CatalogHomeScreen.kt`
- **Análisis**: El sistema no implementa un motor de factores de conversión fraccionarios entre unidades complejas (ej. 1 caja = 12 piezas, o 1 par = 2 zapatos). Si se simularan conversiones automáticas, se producirían descuadres en el inventario FIFO y en las existencias físicas.
- **Decisión**: Tratar las unidades vendibles (`unit`, `par`, `caja`, `metro`) como presentación 1:1. El comerciante elige qué vende; la unidad automática asigna el UUID determinista de tienda (`micatalogo:unit:<shopId>:<key>`). Se permite anulación manual avanzada (`allow_advanced_unit_override`) únicamente si la unidad alternativa existe, está activa y pertenece al tenant.

---

## Hallazgo F3-1: Selector fijo de unidad (unit / bottle / ml) en formulario de producto
- **Archivo**: `app/src/main/java/com/example/bspos/presentation/catalog/CatalogHomeScreen.kt`
- **Causa raíz**: En versiones previas, la interfaz exponía una fila fija de radio buttons con opciones `unit`, `bottle` y `ml` para cualquier tipo de negocio (incluyendo electrodomésticos, repuestos o calzado), lo cual generaba confusión operativa y riesgo de seleccionar unidades de volumen en negocios de artículos físicos.
- **Decisión**: `CatalogHomeScreen` consume `presentation.productForm`. Para tiendas no perfumería (`archetype != "fragrance"`), la lista de opciones se reduce a las modalidades relevantes (`unit`, o `unit` + `service`). Se reemplaza el selector interactivo de unidad de inventario básica por una tarjeta informativa `"Inventario contado en: Unidades/Botellas/etc."`. La modificación de unidad pasa a una sección desplegable de opciones avanzadas.
