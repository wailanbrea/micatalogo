# MiCatalogo — Plan para corregir y certificar la sincronización inmediata

**Fecha:** 2026-10-12  
**Alcance:** Android, API Laravel, almacenamiento local Room, outbox y QA  
**Estado inicial:** NO APROBADO para sincronización inmediata de todos los menús

## 1. Objetivo

Garantizar que toda operación realizada desde MiCatalogo Android tenga un resultado
consistente en tres niveles:

1. La pantalla refleja el cambio inmediatamente cuando la operación local es válida.
2. La operación llega al servidor con un identificador idempotente.
3. Al recargar, cambiar de pantalla, reiniciar la app o cambiar de tienda, la app
   muestra el estado autorizado por el servidor sin duplicar, perder ni mezclar datos.

La app debe informar claramente si un cambio está **pendiente de sincronización**, fue
**sincronizado** o quedó **bloqueado y requiere intervención**. Nunca debe presentar un
dato local como confirmado por el servidor si todavía no existe confirmación remota.

## 2. Hallazgos confirmados

### 2.1 Funciones con sincronización implementada

- Productos: Room + `RemoteMutationRecorder` + outbox.
- Inventario: movimientos locales, outbox y despertar del sincronizador.
- Terminal y ventas: outbox idempotente de ventas.
- Clientes y cobros: API directa en línea y cola offline cuando aplica.
- Cotizaciones: API directa, recarga posterior y sincronización de clientes.
- Pedidos: API directa, validación de tienda activa y recarga posterior.
- Compras: API directa y recarga del módulo después de crear o recibir.
- Finanzas, caja y gastos: API directa, UUID de operación y refresh.
- Decants: comandos por outbox; la creación de botellas y presentaciones espera la
  sincronización del catálogo antes de volver a mostrar el espacio de Decants en la
  release 1.0.104.
- Soporte: API directa y actualización del estado de la conversación.
- Configuración, equipo y permisos: API directa y recarga de tiendas/permisos.

### 2.2 Funciones que pueden quedar solo locales

Estas áreas deben corregirse o declararse explícitamente como configuración local;
por el objetivo del producto, se corregirán para que sean datos de la tienda:

- Categorías.
- Unidades de medida.
- Rutas.
- Cargas de rutas.
- Motivos de ajustes de inventario.

Actualmente sus repositorios Android escriben en Room, pero no generan una mutación
remota equivalente ni tienen un flujo de confirmación contra la API.

### 2.3 Bloqueadores del arnés QA

La batería completa de instrumentación no compila porque varias pruebas quedaron
desalineadas con las firmas actuales:

- Pruebas de catálogo, inventario, clientes, ventas y documentos no reciben el nuevo
  parámetro `shops`.
- `CatalogSyncIntegrationTest.SnapshotApi` no implementa `eligibleReturns`.
- `DecantsWorkspaceTest` usa callbacks antiguos.
- `MixedCreditSaleTest` referencia `tenantTestScope` fuera del perfil de fuentes que
  lo contiene.

Mientras esto exista, un resultado verde parcial no puede considerarse certificación
de todos los menús.

## 3. Arquitectura objetivo

### 3.1 Contrato común de mutaciones

Todas las escrituras remotas deben incluir:

- `client_operation_uuid` estable y único para el intento lógico.
- `shop_id` de la tienda activa validada justo antes de enviar.
- `payload_sha256` o equivalente cuando el endpoint lo requiera.
- estado local: `PENDING`, `SENT`, `RETRY` o `BLOCKED`.
- respuesta idempotente del servidor.
- descarga posterior del recurso o read model afectado.

No se debe borrar una fila pendiente hasta recibir una confirmación válida. Un error
definitivo debe quedar visible y reintentable; no puede desaparecer silenciosamente.

### 3.2 Flujo estándar después de guardar

```text
Formulario
   ↓
Validación local
   ↓
Transacción Room + outbox
   ↓
Despertar worker
   ↓
Esperar ACK o mostrar “pendiente”
   ↓
Sincronizar el read model del módulo
   ↓
Actualizar pantalla y estado de sincronización
```

Para endpoints online que escriben directamente, el servidor debe responder con el
registro actualizado y la pantalla debe usar esa respuesta, no una copia anterior.

## 4. Plan de implementación

### Fase A — Reparar el arnés de pruebas

1. Actualizar constructores y fixtures para pasar `ShopScope` donde corresponda.
2. Implementar `eligibleReturns` en los dobles de `MiCatalogoApi`.
3. Actualizar callbacks de `DecantsWorkspaceTest` a las firmas actuales.
4. Separar correctamente los perfiles Gradle:
   - pruebas de datos locales;
   - pruebas de tenant/outbox;
   - pruebas de UI;
   - pruebas live con servidor QA.
5. Corregir la inclusión de `tenantTestScope` en el perfil que usa
   `MixedCreditSaleTest`.
6. Hacer que cada perfil publique su cantidad de tests, PASS, FAIL, SKIP y ASSUMPTION.
7. Fallar el build si una prueba no compila o si una clase esperada no fue incluida.

**Salida:** `connectedDebugAndroidTest` compilable y reproducible sin seleccionar
producción ni el dispositivo físico.

### Fase B — Categorías y unidades

1. Definir DTOs y endpoints API para crear, editar, desactivar y ordenar categorías.
2. Definir el contrato para unidades de medida; si el backend no las persiste como
   entidad de tienda, agregar la representación mínima necesaria sin modificar datos
   existentes.
3. Agregar `CategoryMutation` y `UnitMutation` al outbox con UUID idempotente.
4. Implementar procesamiento server-side con validación de tienda y reintento seguro.
5. Actualizar `CategoryRepositoryImpl`, `UnitOfMeasureRepositoryImpl` y sus casos de
   uso para registrar la mutación remota dentro de la misma transacción local.
6. Después del ACK, descargar el catálogo y preservar referencias de productos.
7. Probar alta, edición, desactivación, ordenamiento, duplicación y cambio de tienda.

### Fase C — Rutas, cargas de rutas y motivos de ajustes

1. Auditar el modelo web/API existente y confirmar las entidades autorizadas por tienda.
2. Agregar endpoints de lectura y escritura para rutas, clientes asignados, cargas y
   motivos, o adaptar los existentes si ya están disponibles en web.
3. Crear DTOs, repositorios remotos y operaciones idempotentes Android.
4. Mantener las relaciones tenant-scoped: una ruta o carga no puede cruzar de tienda.
5. Tras cada escritura, esperar ACK o mostrar estado pendiente; después recargar el
   módulo afectado.
6. Validar que cambiar de tienda cancele trabajos anteriores y limpie la proyección
   visual sin mezclar rutas ni cargas.
7. Agregar pruebas de conflicto, reintento, respuesta 401/403/409/422/5xx y replay.

### Fase D — Unificar el comportamiento inmediato en módulos existentes

Revisar y estandarizar los siguientes casos:

- Producto creado → volver a Productos, Inventario y Decants sin esperar reinicio.
- Entrada o ajuste de inventario → stock, valoración y Terminal coherentes.
- Venta contado/crédito/mixta → Ventas, Caja, CxC, dashboard y devoluciones.
- Cobro → saldo del cliente, recibos, dashboard y caja sin doble registro.
- Cotización creada → KPIs vigentes, por convertir y vencidas.
- Cotización convertida → Ventas, stock, caja y devoluciones elegibles.
- Pedido confirmado → Ventas, inventario, cliente y crédito.
- Compra recibida → lotes, costo, stock y precio cuando corresponda.
- Gasto → lista de gastos, caja y resultado financiero.
- Decant preparado/vendido → mililitros, frascos, botella fuente y costo FIFO.
- Permiso o menú modificado → menú visible, acceso de ruta y respuesta API.

Cada caso debe tener una función de sincronización posterior explícita; no depender
solo de que la pantalla se vuelva a crear.

### Fase E — Estado de sincronización visible

Agregar un componente común para mostrar:

- `Sincronizado`.
- `Pendiente de sincronización`.
- `Reintentando`.
- `Requiere revisión`.

Debe permitir reintentar sin duplicar operaciones y mostrar el error del servidor sin
exponer datos sensibles.

## 5. Matriz de QA por menú

### Operación

- **Resumen:** confirmar que las tarjetas cambien después de una venta, cobro, gasto,
  pedido y conversión de cotización.
- **Terminal:** agregar, quitar, cobrar, contado, crédito y mixto; cerrar/reabrir
  inmediatamente y comprobar stock, venta y caja.
- **Ventas:** confirmar que una venta recién creada aparezca sin reiniciar.
- **Cotizaciones:** crear, recargar, convertir y comprobar los tres KPIs y Ventas.
- **Pedidos:** crear cliente, confirmar contado/crédito/mixto y comprobar rechazo por
  límite sin cierre de app.
- **Encargos y Envíos:** crear/actualizar estado y comprobar que el listado se refresque.
- **Cierre de día:** guardar y volver a abrir; comprobar fecha, caja y duplicación.

### Compras

- **Contenedores, Cargas, Suplidores y Facturas:** crear, editar, recibir, reabrir y
  confirmar que el registro esté en la API y en la lista inmediatamente.
- Validar lotes con costos distintos y FIFO sin sobrescribir el costo histórico.

### Catálogo

- **Productos:** crear, editar, archivar, imagen, stock inicial y cambio de tienda.
- **Inventario:** entrada por lotes, ajuste, cantidades numéricas y stock remoto.
- **Fotos:** cargar y recargar sin perder la imagen.
- **Mi tienda:** guardar apariencia, logo y configuración; verificar read-back.
- **Servicios:** crear y actualizar la configuración aplicable.
- **Salud de precios y Precios automáticos:** guardar regla, aprobar bajadas y
  comprobar recalculo.
- **Decants:** crear botella, abrir, preparar presentación, recibir frascos, vender y
  comprobar ml, frascos, costos y stock.
- **Marcas y atributos:** crear/editar/desactivar y comprobar persistencia remota.
- **Importar:** previsualizar, confirmar, evitar duplicados y verificar resultados.

### Cobros, Finanzas y Equipo

- **Clientes:** alta/edición/desactivación y presencia idéntica en Clientes, Pedidos,
  Cotizaciones, Crédito y Terminal.
- **Crédito/Cobros:** abono simple, parcial, mixto, doble toque e idempotencia.
- **Ganancias:** venta, costo, gasto y devolución deben aparecer en el período correcto.
- **Caja:** apertura, movimiento, cierre y recuperación tras reconexión.
- **Gastos:** registrar, recargar y comprobar caja/resultado.
- **Socios:** alta y movimientos con read-back.
- **Reportes:** exportación y consistencia de los totales.
- **Comisiones:** venta de vendedor, comisión y resumen del owner.
- **Autorizaciones:** aprobar/rechazar y refrescar la bandeja.
- **Equipo:** crear vendedor, permisos, comisión y separación por tienda.

### Ajustes y ayuda

- **Configuración de tienda:** guardar cada sección y volver a leerla.
- **Equipo, Contador, Mi cuenta:** verificar permisos, datos y actualización inmediata.
- **Novedades, Ayuda, Practicar sin miedo y Soporte:** navegación, creación de chat,
  mensajes y cierre de conversación.
- **Menús de tienda:** activar/desactivar un menú, verificar drawer, ruta directa y
  autorización API.
- **Todas las tiendas:** solo owner/plataforma; validar aislamiento y cambio de tienda.

## 6. Prueba estándar de sincronización inmediata

Para cada escritura se ejecutará esta secuencia en un entorno QA aislado:

1. Crear un identificador de prueba único.
2. Leer el estado inicial desde API y Room.
3. Ejecutar la acción una sola vez.
4. Navegar inmediatamente a otro menú y volver.
5. Consultar el outbox: debe estar `SENT` o mostrar `PENDING/RETRY` de forma visible.
6. Consultar nuevamente la API.
7. Forzar refresh del módulo.
8. Reiniciar la Activity y repetir la lectura.
9. Repetir el mismo request con el mismo UUID: no debe crear un duplicado.
10. Cambiar de tienda y volver: el dato no debe aparecer en otra tienda.
11. Registrar evidencia: request, respuesta, estado outbox, pantalla y conteos.
12. Eliminar o archivar únicamente el fixture QA por su identificador, nunca datos de
    producción.

## 7. Casos de red y concurrencia

Cada grupo de mutaciones debe probar:

- red caída antes del envío;
- red caída después del ACK y antes de marcar `SENT`;
- respuesta 401/403;
- respuesta 409 por conflicto;
- respuesta 422 por validación;
- respuesta 429/500 para reintento;
- doble toque;
- dos operaciones distintas sobre el mismo producto;
- cambio de tienda durante la sincronización;
- cierre y reapertura de la app;
- respuesta tardía después de abandonar la pantalla.

## 8. Criterios de aceptación

El plan se considera completo cuando:

- El arnés completo compila y ejecuta todos los tests declarados.
- No existen operaciones de tienda que solo escriban en Room sin decisión documentada.
- Cada mutación remota tiene UUID idempotente y estado observable.
- Ningún módulo muestra un falso “sin datos” mientras una sincronización está pendiente.
- No se duplican ventas, cobros, clientes, productos, compras ni movimientos.
- No se mezclan datos al cambiar de tienda.
- Categorías, unidades, rutas, cargas y motivos de ajustes están persistidos en la API
  o se retiran de la navegación hasta implementar su contrato remoto.
- Los flujos principales tienen pruebas unitarias, instrumentadas y live QA aisladas.
- La compilación release firmada pasa verificación de paquete, certificado, versión y
  `debuggable=false`.
- Solo después de todo lo anterior se autoriza una publicación nueva.

## 9. Reglas de seguridad y despliegue

- No ejecutar seeders, `migrate:fresh`, `truncate`, borrados amplios ni resets contra
  producción.
- No usar cuentas ni catálogos de producción para pruebas de escritura.
- Registrar conteos e identificadores QA antes y después de cada prueba live.
- No borrar APKs ni datos locales para resolver una firma sin autorización explícita.
- Emulador: debug/offlinecheck. Dispositivo físico: release firmada.
- Publicar backend y APK solo después de que el gate QA sea PASS.

## 10. Orden recomendado de ejecución

1. Reparar el arnés QA y conseguir un build instrumentado completo.
2. Implementar categorías y unidades remotas.
3. Implementar rutas, cargas y motivos de ajustes remotos.
4. Añadir el componente común de estado de sincronización.
5. Crear pruebas de contrato y de replay para cada módulo.
6. Ejecutar la matriz de menús en QA aislado.
7. Corregir regresiones encontradas y repetir desde el caso fallido.
8. Ejecutar backend QA, Android unitarias, instrumentadas y live fixtures.
9. Compilar y verificar release firmada.
10. Publicar backend y APK únicamente con autorización expresa.

## Evidencia actual

- Android branch: `main`, commit `8264449`.
- Release publicada: `1.0.104`, versionCode `105`.
- 113 pruebas unitarias Android PASS.
- 21 pruebas instrumentadas de tenant/pedidos PASS.
- Suite instrumentada completa: bloqueada por desalineación del código de pruebas,
  detallada en la sección 2.3.
- No se modificó producción durante esta auditoría.

## Ejecución del 12/10/2026

La implementación y sus evidencias están en `QA_SINCRONIZACION_RESULTADOS_2026-10-12.md`.

- Arnés instrumentado completo reparado: 173/173 PASS, sin SKIP ni ASSUMPTION, incluidos siete escenarios live aislados.
- Categorías, unidades, rutas, cargas y motivos: contrato remoto/outbox/read-back implementado y probado.
- Sobres durables y estado visible agregados para las operaciones comerciales/financieras detalladas en el informe.
- Backend completo: 569/569 PASS, 6,319 assertions; catálogo de 1,500 productos con 19 consultas (límite 20).
- APK de revisión 1.0.105 / 106 compilada y verificada con certificado existente, `debuggable=false`; no publicada.
- El gate automático declarado es PASS. La certificación universal de todos los menús/contratos de la sección 8 sigue pendiente de los casos especificados en el informe; este resultado no amplía la autorización de despliegue.
