# Auditoría multitienda — avance verificado

Actualización 11/10: verificación de la actividad real en dos tiendas QA locales,
59 pruebas instrumentadas aprobadas y correcciones adicionales de confirmación
de pedidos y resumen financiero. Detalle y límites de esta ejecución en
[`EMULATOR_VERIFICATION_2026-10-11.md`](EMULATOR_VERIFICATION_2026-10-11.md).
La release firmada 1.0.94 (versionCode 95) quedó publicada y validada contra
el manifiesto público. La suite histórica completa aún requiere adaptar sus
fixtures antiguos al contexto obligatorio de tienda.

## Estado

Implementación de aislamiento verificada y desplegada. La suite instrumentada
histórica Android requiere adaptar sus fixtures al nuevo contexto obligatorio de
tienda antes de ejecutar el recorrido completo.

Commits publicados de este trabajo:

- Backend: `949428c05ffdbb875c524b11796bc1d6f9a3dee9`.
- Android: `da99c9ca84c189778a0c030dada1cbeede7cae74`.

## Cambios implementados

### Android

- Room 27: metadata `remote_shop_id` / `tenancy_status` en 26 tablas.
- Migración aditiva 26→27 registrada junto con todas las migraciones anteriores.
- Backfill sólo con asociaciones determinísticas; evidencia contradictoria,
  duplicados y referencias incompatibles permanecen `legacy_ambiguous`.
- Propagación de ambigüedad hasta punto fijo a documentos y sus líneas.
- Triggers impiden trasladar propietarios y conectar entidades asignadas de
  distintas tiendas. Se restaura la inmutabilidad del kardex tras el backfill.
- Consultas operativas de catálogo, clientes, rutas, stock, documentos, ventas,
  pagos, devoluciones y caja filtradas por tienda y estado asignado.
- `ShopScope` reinicia observadores, vacía su emisión inicial y comparte un lock
  reentrante de coroutine con transacciones Room y selección/cierre de sesión.
- Eliminada la publicación global de clientes al descargar una tienda.
- Crear un cliente conectado persiste primero en backend; un rechazo no deja
  una fila local. Los fallos de transporte usan la cola con UUID estable.
- Reconciliación del directorio sin borrar físicamente registros ni sobreescribir
  ediciones pendientes, incluso si llega un ACK mientras se descarga la lista.
- Clientes, Pedidos y Cobros comparten la caché de clientes por tienda.
- Pedidos crea clientes mediante el mismo repositorio. Clientes inactivos y
  pendientes se muestran etiquetados, sin permitir su selección a crédito.
- Ventas, abonos y devoluciones propagan tenant a cabeceras, líneas y movimientos.
- Caja refleja sesiones remotas verificadas; una caja local histórica no basta
  para afirmar que el servidor tiene una caja abierta.
- La navegación se recrea por cuenta/tienda; la selección reinicia bootstrap y
  cancela el trabajo anterior de selección. Falta certificar el recorrido UI
  completo y la destrucción de todos los ViewModels con el QA histórico.

Archivos centrales: `core/database/ShopScope.kt`, `TenantMigration.kt`,
`TenantIntegrity.kt`, `DatabaseMigrations.kt`, entidades y DAO en `data/local/`,
repositorios en `data/repository/`, sincronización en `data/micatalogo/`, casos
de uso de ventas/abonos/devoluciones/caja y pantallas de Clientes/Pedidos.

### Backend

- `MobileOperationController` admite referencia remota `customer_id` e
  `is_deleted` explícito para clientes.
- `MobileOperationService` resuelve la referencia dentro de `$shop->customers()`:
  un cliente de otra tienda se rechaza aunque el actor sea dueño de ambas.
- Editar un cliente creado desde la web conserva su identidad y no crea un
  duplicado a partir del UUID local de la caché Android.
- `is_deleted=false` separa inactivación y eliminación lógica. Para clientes
  móviles antiguos sin bandera se mantiene el significado heredado del comando.
- Una eliminación lógica de un cliente con saldo pendiente se rechaza.
- Nuevas pruebas en `tests/Feature/ApiTenantResourceIsolationTest.php`.

## Verificación ejecutada

| Comprobación | Resultado |
| --- | --- |
| Compilación Kotlin/Room y APK debug aislada | Correcta |
| Android `:app:testDebugUnitTest` | 111 pruebas, sin fallos |
| `TenantMigrationTest` | 4/4 |
| `TenantSyncTest` | 6/6 |
| `TenantCashTest` | 2/2 |
| `OrderConfirmDialogTest` | 5/5 |
| Instrumentación tenant + pedidos | 17/17 |
| Backend `php artisan test` | 541/541, 6081 assertions |
| Suite instrumentada histórica Android completa | Bloqueada en compilación de fixtures antiguas |
| APK release 1.0.94 | Firmada, no debug, publicada y hash verificado |

Los tests de migración abren los esquemas publicados 1, 15 y 26 con SQLite real
y validación completa de Room 27. Comparan todas las columnas originales de las
filas de las fixtures antes/después; verifican FK y restauración de triggers.
Eso acredita las fixtures ensayadas, no una inspección de datos de producción.

Las pruebas negativas backend cubren dueño con acceso a ambas tiendas, cliente,
abono, producto POS, caja e idempotencia por tienda. Las matrices y recorridos
existentes de permisos, compras, cotizaciones, Decants, devoluciones, FIFO y
contabilidad también pasaron en la suite completa.

## Entorno efectivo de las pruebas

- Backend: `APP_ENV=testing`, conexión/driver `sqlite`, base `:memory:`,
  host `none (in-memory)`, `DB_URL` vacío; identidad verificada antes de ejecutar.
- Android: sólo `emulator-5554`, paquete debug separado
  `com.bsolutions.micatalogo.offlinecheck.tenancy`, versión `1.0.93-offlinecheck` / 94.
- Certificado debug SHA-256:
  `e266fd48b028dc66dc4e530c0bc8af06b9be820545600c6c4b19c81b4b1ff775`.
- API de esa compilación: `http://127.0.0.1:9/`; las pruebas usan servicios fake.
- Room de pruebas: memoria o archivos nuevos `tenant-migration-test-*`.
- La ejecución QA descrita no instaló esta migración en el paquete operativo ni
  en el teléfono; la release firmada posterior sí fue publicada como 1.0.94.
- La ejecución QA no hizo limpieza ni operaciones de BD; el despliegue posterior
  fue código/configuración y APK, sin migraciones ni cambios de datos.

## Pendientes para cerrar la auditoría

1. Adaptar fixtures históricas Android con tenant explícito y ejecutar la suite
   completa; las llamadas antiguas a constructores no proporcionan `ShopScope`
   y algunas expectativas antiguas asumían consultas sin tienda.
2. Completar la revisión de todas las llamadas operativas y retirar defaults
   transitorios `shopId=null`, que actualmente fallan cerrados devolviendo vacío.
3. Revisar mantenimiento de caché, colas y conteos globales: los dispatchers
   deben conservar el tenant de cada evento y las vistas sólo mostrar el activo.
4. Certificar en UI dos tiendas, cambio durante carga/confirmación, filtros,
   carrito, cotización, crédito, abonos, devolución, caja, offline y reconexión.
5. Confirmar todos los selectores de crédito/cotización con la política común
   de clientes inactivos; Pedidos y la pantalla Clientes ya muestran la etiqueta.
6. Inventariar datos reales ambiguos con una consulta autorizada de sólo lectura
   y preparar conciliación verificable. Su cantidad real aún no está medida.
7. Revisión final de diff/status, commits y release firmada únicamente después
   de completar QA y recibir la autorización de publicación correspondiente.

Los `IS NULL` que permanecen en consultas específicas de conciliación no forman
parte de las listas operativas. Todavía **no** se certifica ausencia absoluta de
consultas globales en toda la aplicación ni aprobación del flujo E2E Android.
