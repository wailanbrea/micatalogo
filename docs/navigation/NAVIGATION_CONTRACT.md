# Navegación canónica, schema_version 1

`config/seller-navigation.php` conserva el orden de las once áreas, las claves individuales y los destinos Web/Android. `SellerNavigationService::forUser` filtra por `SellerMenuService::visibleForUser`, policy para escoger pantallas administrativas o feature fallback, y controles owner-only. No escribe claves, roles ni datos de tienda. Cada área aparece sólo si tiene hijos.

Contrato aditivo: `/api/v1/shops` añade `navigation` y `catalog_permissions` sin modificar `menu_permissions`, `enabled_menu_keys`, `menu_options`, `presentation`, `quota` ni `sellers`. Clientes antiguos pueden ignorar esos campos. Las reglas de plan/capability se siguen resolviendo por los servicios existentes; `business_label` reutiliza `BusinessPresentationService` para búsquedas/contexto sin cambiar los nombres de áreas.

- `sections`: áreas con `id`, `label`, `items`.
- `secondary`: cuenta/ayuda accesibles al actor autenticado con tienda accesible; soporte/actualizaciones/guía mantienen sus claves. Web añade Mis tiendas para quienes pueden acceder al selector. Esta excepción de cuenta/ayuda no cambia permisos comerciales ni su JSON persistido.
- `contextual_sections`: Distribución sólo con permiso routes; `requires_local_setting=routesEnabled` indica que Android además filtra según su preferencia local existente. El backend no inventa un nuevo ajuste persistido.
- Item: `id`, `key` heredada, `label`, `business_label`, `destination` Android conocido, `url` Web o null si no hay flujo Web nativo, `route`, `feature`, `active_routes`, `external`.
- Web solicita platform=web y descarta destinos exclusivamente nativos. Incluye `active` por hijo/grupo, una única coincidencia más específica.
- `preferred_query` desambigua destinos que comparten ruta: Clientes y Cobros conservan `seller.shops.customers.index`, y Cobros usa `view=collections`. La query sólo selecciona contexto visual; las escrituras mantienen sus claves individuales.
- `more` se mantiene en el registro de permisos como legacy container key; no se usa para filtrar hijos ni para abrir el drawer.

El consumidor Android debe aceptar sólo destinos de su whitelist `Screen`, filtrar de nuevo con claves efectivas y capabilities cacheadas, aplicar `routesEnabled`, y conservar fallback de estructura para respuestas antiguas. No navegar a URL/route arbitraria enviada por servidor. El manifest no autoriza endpoints ni reemplaza policies ni revalidación de outboxes.

## Facultades de catálogo y Fotos

`catalog_permissions` contiene booleanos `manage_products`, `manage_services`, `manage_decants`, `manage_photos` y `read_costs`, calculados por `CatalogAccessService`. No son claves nuevas persistidas. Android combina cada facultad con la clave/capability efectiva del destino. Ausencia de metadata no promueve los defaults de vendedor a CRUD físico. `services` explícito permite operar servicios; no permite transformar productos físicos ni cambiar costos ocultos.

`POST /api/v1/shops/{shop}/mobile-operations` admite el nuevo tipo `product_photo_update`, con `client_operation_uuid`, `product_id`, `image_base64` y `image_sha256` (SHA-256 hexadecimal de los bytes enviados). Requiere pertenencia, ability `pos:write` y autorización de Fotos o escritura del tipo concreto de producto. Precio, costo, stock, tipo y otros campos de metadata están prohibidos en ese envelope. La validación de imagen y los límites del plan existentes permanecen activos.

La respuesta 201 confirma UUID, `product_id` e `image_sha256`. Un replay revalida permiso y tenant antes de devolver el comprobante previo; otro contenido con el mismo UUID sigue siendo conflicto. Android sólo marca `SENT` si coinciden UUID, producto y hash, conserva el payload inmutable al reintentar y mantiene una descarga de catálogo durable tras el ACK. No borra ni sustituye automáticamente fotos anteriores.

La pantalla Fotos Android permite elegir imagen sin abrir CRUD de producto. La foto se copia a bytes acotados al encolarla, dentro del bloqueo de tienda y revalidando el permiso cacheado antes y después de preparar la imagen; la proyección del catálogo se actualiza al confirmar y descargar del servidor. El servidor conserva compatibilidad con `product_upsert` de clientes anteriores.

## Clientes, cobros y emisión de crédito

`customers` permite ficha, `collections` cobrar deuda existente y `credit` emitir nueva deuda/ajustarla según las policies correspondientes. Leer clientes para un cobro no concede crear fichas ni emitir crédito. Cobrar depende de capability clientes, no de habilitar crédito nuevo.

En POS, `sales` autoriza la venta pagada; un importe a crédito requiere además `credit`, tanto en `credit_amount` como en pagos normalizados con método crédito. Se comprueba antes de resolver UUID previo y en `PaymentService`. Confirmar un pedido a crédito y repetir su envelope revalida también la clave. La ruta Android legacy `credit` conserva su identidad y exige Ventas+Crédito; `credit_ledger` conserva la unión de lectura de cartera/cobros sin conceder mutuamente las escrituras.

Las mutaciones Android consultan el snapshot efectivo del mismo shop dentro del lock/transacción: sin caché vigente de autorización no registran un abono remoto ni una venta vinculada. Los borradores ya encolados conservan identidad/contenido y la API vuelve a autorizar al sincronizar.

## F1 verificado

SellerNavigationManifestTest: registro completo de 46 claves, ocho delegaciones aisladas, NULL legacy, owner anti-lockout/manager, contador, tres planes/cinco tipos, native-only Web/fallback y aislamiento multitienda/admin global. Junto a ShopMenuVisibilityTest y BusinessPresentationTest: 23 PASS, 3.374 aserciones antes de la integración visual F2. No certifica las mutaciones pendientes F4.
