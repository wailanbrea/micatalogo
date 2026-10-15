# F0 — acceso efectivo por rol

Resultado read-only de `php qa/navigation_fixture.php permissions`, con entorno testing, conexión sqlite y base QA dedicada verificados antes de la consulta.

| Rol QA | Menús efectivos observados | Administra operación | Editor menús tienda |
|---|---|---|---|
| owner perfume | Las 46 claves registradas | Sí | Sí |
| manager perfume | 43 claves: todas salvo settings/shop_settings/sellers | Sí | No |
| accountant | finance, reports | No | No |
| sales | sales, account, help | No | No |
| cash | cash, account, help | No | No |
| expenses | expenses, account, help | No | No |
| services | services, account, help | No | No |
| reports | reports, account, help | No | No |
| barber owner | 45 claves: todas salvo decants | Sí | Sí |
| grocery owner | 45 claves: todas salvo decants | Sí | Sí |

Perfume tiene services/decants habilitados. Barber y grocery tienen services enabled, decants disabled; appointments/weighted_products son unsupported respectivamente. No se decide visibilidad solamente por business_type. Las capturas Android y Web están en evidence; el menú Web móvil omite opciones efectivas (ver NAV-F0-02).

`enabled_menu_keys=NULL` en las tres tiendas. Estos resultados no prueban todavía revocación durante pantalla abierta, modo offline ni un shop con todos los módulos deshabilitados; forman parte de F1/F3/F5.

## Conservación del fixture tras las capturas

Consulta `inspect` posterior: tienda perfume `01M4WS4YHD1C4XXATWMPA46QBC`; producto `01M4WS4YHHWNG915FCBZTX29VE` stock20, vendidos0, costo120.00; cliente `01M4WS4YHJ6AXMQGNZZG4ZHY3Q` saldo0.00. Cero facturas y cero sesiones de caja en las tres tiendas. Barber/grocery sin productos/clientes. Las capturas no ejecutaron ventas, cobros ni movimientos de stock/caja.

## Baseline local Android

Snapshots obtenidos por conexión SQLite **read-only** al archivo existente de Room del paquete QA, después de verificar API/entorno/base. Esquema27. Antes/después de las capturas de teléfono y tablet: ningún cambio en los conteos de tablas. Hay un producto, categoría, unidad, cliente, stock y movimiento de inventario de la importación inicial. Sales/payments/returns/cash_sessions/cash_movements y pos_sale_outbox/payment_sync_outbox/operation_outbox permanecen en cero.

Archivos: `evidence/navigation-qa/baseline-room-{phone|tablet}-{before|after}.json`. La comparación está en `verification-baseline.json`. Es una línea base de conteos y colas vacías; todavía no prueba conservación de operaciones pendientes no vacías ni igualdad de cada fila. Esa regresión pertenece a F3/F5.
