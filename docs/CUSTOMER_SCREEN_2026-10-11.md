# Clientes: tarjetas compactas y detalle de cuenta

## Implementado

- Tarjeta de cliente compacta: avatar cuadrado de 36dp, nombre y contacto en dos
  líneas, saldo alineado a la derecha, bordes y paleta compartidos con Inventario.
- Se retiraron de la lista los bloques grandes de documento y límite; el detalle
  presenta la información financiera necesaria sin aumentar la tarjeta.
- Toda la tarjeta abre los movimientos. El menú de acciones conserva ver
  movimientos, editar y eliminar; las acciones no disparan la apertura de la tarjeta.
- El detalle muestra saldo, límite, crédito disponible, estado inactivo y teléfono.
- La sección Origen de la deuda muestra factura/pedido, fecha, total, abonos y
  pendiente; los saldos iniciales/ajustes sin factura se presentan explícitamente.
- Movimientos diferenciados: venta a crédito, abono, cargo manual y ajuste.
  Los importes negativos de abonos conservan su signo; no se muestran como cero.
- Abonos con asignación muestran las facturas a las que se aplicaron.
- Volver desde el detalle conserva búsqueda y posición del listado.
- Consultas ligadas al cliente y a la tienda activa; un cambio de tienda cancela
  el detalle y una respuesta tardía no lo vuelve a abrir.

## Backend

`GET /api/v1/shops/{shopId}/customers/{customerId}` mantiene customer/entries y añade:

- `debt_origins`: facturas pendientes y su atribución, descontando pagos y devoluciones.
- `unallocated_balance`: saldo que no está representado por facturas abiertas.
- `entries_has_more`: indica truncación del historial a los últimos 100 movimientos.
- `entries[].allocations`: distribución de un abono entre facturas.

La lectura no modifica saldos ni documentos. La suma atribuida nunca supera
el saldo autoritativo del cliente. Se rechazan IDs de otra tienda aunque el dueño
tenga acceso a ambas. No se añadió migración ni se cambiaron datos de producción.

## Verificación

- Compilación Kotlin/Room/debug: correcta.
- Pruebas unitarias Android: suite aprobada.
- 4 pruebas de pantalla: tarjeta/clic/acciones, origen de deuda, sin movimientos,
  errores con reintento.
- 3 pruebas del ViewModel: tienda seleccionada, rechazo de cliente ajeno y
  cancelación de respuesta tardía.
- 1 recorrido con actividad real y API QA local: tarjetas compactas, factura de
  RD$600, pagos de RD$400 y pendiente RD$200, movimientos y retorno al listado.
- Backend: 15 pruebas seleccionadas aprobadas de cuenta/aislamiento; la nueva
  suite de detalle contiene 4 casos, 24 assertions, incluida lectura no destructiva.

Entorno de backend de pruebas: APP_ENV testing, SQLite en memoria. El recorrido
real usa exclusivamente el backend local 127.0.0.1:18766 y la base SQLite QA
separada verificada. App debug `com.bsolutions.micatalogo.offlinecheck.emulatorqa`.
Servicio de autocompletado restaurado tras el recorrido.

Capturas privadas: `C:/Users/waila/AppData/Local/Temp/opencode/customer-ui-20261011-evidence/`.

## Pendientes previos

Las correcciones de doble cobro, rechazo de Pedidos, formulario de Gastos,
inventario tras cotizaciones y devoluciones remotas permanecen como trabajo
pendiente separado. Este informe verifica el cambio solicitado de Clientes.
No se publicó una release ni se desplegó el backend al VPS.
