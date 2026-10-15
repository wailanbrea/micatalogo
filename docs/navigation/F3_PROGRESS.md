# F3 — shell Android implementado; certificación integral pendiente

## Base de contrato implementada y verificada

- ShopDto conserva `navigation` como JsonElement opcional, sin impedir la carga de tiendas antiguas ni de schemas futuros/desconocidos. La caché SHOP_CACHE existente serializa ese campo junto con los permisos del mismo shop.
- MiCatalogoConnectionRepositoryImpl decodifica el manifest a MiCatalogoShop.navigation. No mezcla contextos ni cambia selección de tienda, Room o outboxes.
- NavigationManifestDto acepta schema1, sólo áreas/destinos conocidos y pares destination/key válidos; no confía en URLs, rutas arbitrarias, queries de configuración desconocidas ni destinos admin global.
- Modelo MiCatalogoNavigation filtra hijos por acceso efectivo, rutas por la preferencia local routesEnabled, deduplica destinos compartidos y mantiene Cuenta/Ayuda. No consulta more como permiso para abrir un contenedor.
- Seis pruebas NavigationManifestContractTest PASS: caché/legacy, schema desconocido/malformed, rechazo de rutas y keys manipuladas, sibling permissions/revocación, distribución y secciones desconocidas.
- Android JVM actual: **147 PASS**, 0 fallos/errores/skips en 44 XML; build debug y androidTest **BUILD SUCCESSFUL** con API QA local y variante `.offlinecheck.syncqa`. El perfil `all` declara 192 métodos instrumentados; esa declaración no significa que se hayan ejecutado todos.

## Shell y permisos integrados

- `DrawerNavigation.kt` y `BSPOSMainScreen.kt` consumen el árbol autorizado, con fallback local para respuestas antiguas, once áreas, Distribución contextual y Cuenta/Ayuda secundarios. La barra inferior adapta Inicio/Vender/Pedidos/Inventario/Más sin exigir la clave `more` para abrir el drawer.
- Se conservan `Screen` y aliases; `DrawerNavigationTest` verifica destinos permitidos, búsqueda, contexto y rechazo de destinos desconocidos. Capturas de diez roles en teléfono/tablet en `evidence/f3-android/`.
- `SettingsViewModel` distingue bootstrap de un snapshot autoritativo vacío. `observeShops` publica cambios del caché sin exigir reinicio; `ShopSnapshotObservationTest` verifica revocación y eliminación de tiendas cacheadas.
- `FinanceAccess`, `FinanceViewModel` y `FinanceScreen` filtran pestañas, lecturas y mutaciones por operación. Los tests JVM incluyen cash-only, expenses-only, contador, techo restrictivo y respuesta tardía tras revocación.
- Catálogo separa productos/servicios, tipos de mutación y costos mediante `catalog_permissions`. Fotos tiene selector de imagen y una operación independiente; no abre el formulario de producto ni su archivo por pulsación prolongada.
- Elegir destino limpia la búsqueda del drawer, el foco y el teclado. La prueba real de Servicios confirma que revocar en servidor cierra el workspace y que Atrás no lo restaura.
- Compras resuelve permisos antes de leer y limita cada tipo/documento; descarta respuestas tardías incluso tras A→B→A. Cobros exige `collections` tanto en UI como al registrar/encolar. El POS y su alias `credit` exigen Ventas+Crédito para nueva deuda, sin heredar esa facultad de Cobros.

## Evidencia actual y límites

`evidence/f4-purchase-credit-collection-final-pass.json`: **49/49 PASS**, sin fallos, skips, assumptions ni métodos faltantes. Incluye Compras/Cobros/POS y la regresión previa de catálogo/outbox, tenants, Finanzas y las dos pruebas de `NavigationLiveRevocationTest` contra el servidor SQLite QA. Las pruebas de datos usan Room en memoria; las pruebas de UI real restauran los permisos y el autofill originales en `finally`. Los cortes anteriores de 22/22 y 28/28 conservan sus comprobantes.

El gate integral requiere terminar NAV-001..NAV-070, E2E A..G, accesibilidad y comprobaciones pendientes de pantallas compartidas. La ejecución focalizada no certifica los 192 métodos ni una release. Ver `QA_REPORT.md` y `F4_RESULTS.md` para la cobertura y las incidencias corregidas.
