# F1 — decisión de taxonomía compartida por alcance

## Estrategia definitiva

Se reutiliza **ShopSyncRecord** como proyección común de categorías y unidades. `payload.scope` admite `shared`, `product`, `service`; ausencia significa **shared legacy**, sin reclasificar por nombre, perfil ni referencias. No se añade otra tabla de unidades ni se migran claves de menú. Categorías conservan `shop_categories.id/shop_id` y sus relaciones; scope/metadata/UUID viven en el record ya usado por Android.

La metadata es aditiva en JSON, por lo que no requiere migrar el esquema SQL. Room sí necesitará una migración explícita27→28 para conservar scope/revisión localmente; se verificará su actualización con datos y outboxes existentes en F3. No se usa borrado destructivo ni fallback.

## Facultades derivadas

1. Se exige pertenencia real a la tienda; ser admin de plataforma por sí solo no concede acceso.
2. Lectura: scopes compatibles con los tipos que CatalogAccessService autoriza listar. Catálogo operativo de ventas puede consumir taxonomía de sus productos; la entrada de administración no convierte esa lectura en CRUD.
3. Crear `service`: facultad real de escribir servicios. Un vendedor services-only obtiene exclusivamente esa creación y edición; no obtiene `update(shop)`, CRUD físico, stock, mayorista, ajustes ni controles de owner.
4. Crear `product`: facultad de escribir el tipo físico/Decant correspondiente, no sólo el default legacy `products` de un vendedor lector.
5. Crear `shared`: owner/manager con facultades de los dos tipos. Para modificar shared legacy se exige administración física y autorización sobre **todos los tipos referenciados**, incluyendo productos archivados. Un actor services-only no modifica shared aunque no tenga referencias.
6. Modificar `service`/`product`: facultad correspondiente y ninguna referencia fuera de su alcance. Un record etiquetado service que tenga una referencia física inconsistente se rechaza; no se arregla moviendo productos ni borrando referencias.
7. Cambiar scope: sólo administración autorizada de origen/destino, sin referencias (históricas incluidas). Nunca se cambia scope como efecto secundario de editar nombre o sincronizar un cliente viejo.
8. Desactivar/eliminar en UI es lógico: status/flags del record. Se conservan categorías, UUID, relaciones y referencias históricas. Reactivar usa la misma facultad; no borra unidades o categorías físicamente en uso.

Las derivaciones se exponen aditivamente como `taxonomy_permissions`, con scopes de lectura/creación y acciones por record. Son facultades de operación, no claves persistidas nuevas. La cadena middleware → policy → servicio aplica las mismas comprobaciones; el enlace categories puede representar Services sin conceder Products.

## Identidades y compatibilidad

- Categoría Web histórica sin record: UUID v3 de `micatalogo:category:<shop.public_id>:<category.id>`, idéntico a Android. Al escribir se materializa ese record, sin crear otro UUID ni cambiar id entero.
- Categoría creada desde Android: conserva su UUID aleatorio; `payload.server_id` apunta al entero. Web debe reutilizar ese UUID.
- Unidad custom: UUID existente de `ShopSyncRecord(kind=unit)`. Relación de producto: `product_unit` por public_id → unit_uuid. Unidades built-in mantienen UUID determinista; service es de alcance service y las unidades físicas son product.
- No se adivina pertenencia por nombre. Cada lookup y relación usa shop/kind/UUID; UUID ya ligado a otra tienda no se reutiliza para mutar su recurso. Las colisiones no reasignan datos de Room.
- Nombres se mantienen únicos por tienda y clase (categoría/unidad), para conservar el comportamiento legacy y evitar selección ambigua por category_name. No se unen records de igual texto.
- Snapshot y ACK mantienen campos existentes y añaden scope/revisión/acciones. APKs viejos pueden ignorarlos. Los payloads viejos sin scope no borran el scope persistido.

## Concurrencia e idempotencia

- Cada mutación efectiva genera `payload.revision` (UUID). Web y Android nuevos envían `expected_revision`; revisión cambiada devuelve409 sin writes parciales y conservando outbox/borrador.
- Autorización y tenant se verifican **antes** del lookup idempotente. La revisión se comprueba al ejecutar una operación nueva; un replay autorizado y de hash idéntico devuelve el ACK previo sin reejecutar. Otro contenido con el mismo UUID devuelve409.
- Clientes legacy no pueden probar qué revisión editaron. Se conserva su contrato aditivo y sus replays; una edición ciega divergente después de un cambio Web versionado debe devolver conflicto en vez de borrar ese cambio silenciosamente. Los conflictos se conservan y se informa la necesidad de conciliar/actualizar cliente. No se promete resolver concurrencia que un cliente antiguo no representa.
- La transacción bloquea la tienda, resuelve identidad, verifica referencias y escribe categoría/proyección/revisión/comprobante de forma atómica. Un GET no crea ni modifica records.

## Ejemplos que deben aprobar los fixtures del gate

- `{services}` crea categoría/unidad service y las usa en un servicio; editar shared usada por una botella, scope product o unidad ligada a físico devuelve403 sin alterar el producto.
- Actor physical-only no edita service; vendedor con defaults de catálogo lector no obtiene creación física de taxonomía.
- Owner/manager con módulos efectivos puede gestionar registros de su alcance; contador/sin permiso no escribe; actor de otra tienda recibe403/404 según boundary.
- Cambiar service→product con referencias se rechaza; desactivar mantiene id/UUID/histórico.
- Mismo UUID/otro hash y revisión obsoleta devuelven409; revocar Services antes de replay devuelve403. unit_uuid/category_uuid/category_name no permiten asignación o creación de alcance ajeno.

**Gate F1 verificado:** `CatalogTaxonomyPolicyTest`: **5 PASS/25 aserciones**, testing/sqlite/:memory: con identidad verificada. Incluye actor Services, manager physical-only, vendedor lector, owner, contador/admin ajeno y módulo deshabilitado; categorías/unidades service con referencias físicas archivadas se rechazan. `CatalogTaxonomyAccessService` implementa sólo derivación/consulta, sin cambiar rutas ni persistir registros. La integración HTTP, los scopes persistidos y la concurrencia se ejecutan en F2; estos25 checks no se presentan como ese gate aprobado.
