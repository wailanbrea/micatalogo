# Jornadas operativas

Se ejecutarán en una base QA sintética con Tienda A y Tienda B, nunca contra producción.

1. Alta → perfil de negocio → producto → catálogo público → pedido → confirmación → factura → stock.
2. Owner crea vendedor/contador → permisos mínimos → venta → comisión → revisión owner → bloqueo financiero.
3. Compra de dos lotes → FIFO → cotización → venta contado/tarjeta/crédito → abono → gasto → cierre → P&L.
4. Vitrina → pedido WhatsApp → confirmación Web/Android concurrente → una factura y una baja.
5. Perfumería → botella → decants 5/10/30 ml → venta/devolución → ml, costo y stock.
6. Sync catálogo → venta offline → kill app → reconexión → ACK único y dashboard conciliado.
7. Importador XLSX fila 8 → mapping → preview → confirmación/replay → no duplicados.

Cada jornada debe registrar request, actor, tienda, estado DB antes/después, invariantes, evidencia y SHA del fix si existe.

## Estado de ejecución

| Jornada | Estado | Evidencia |
|---|---|---|
| E2E-03 compra → cotización → venta crédito → abono → gasto → cierre | PASS aislado | `OperationalJourneyTest`: 1 test y 43 assertions en SQLite y MariaDB QA; stock 2→0, dos facturas, un lote, cartera RD$150, cierre esperado RD$320 y diferencia RD$0.00; la base QA quedó vacía |
| E2E-01 alta → tienda → producto → catálogo → pedido → factura | PASS aislado | `OperationalPublicOrderJourneyTest`: 1 test y 27 assertions en SQLite y MariaDB QA; registro/preset, vitrina, pedido con enlace firmado, factura pagada, stock 3→1, cierre RD$600 sin diferencia y replay sin segunda factura |
| E2E-04 pedido WhatsApp → confirmación concurrente | PASS aislado | `qa/order_confirmation_concurrency_probe.php`: dos procesos confirmaron el mismo pedido; ambos recibieron la misma factura, con una sola baja de stock y una sola entrada de caja por RD$300.00; WhatsApp real y Web↔Android siguen pendientes |
| E2E-11 compra internacional → carga/contenedor → costo | REQUIERE_DECISION | `qa/international_purchase_audit.php` confirmó que la tasa, flete y aduana se almacenan como metadatos y no afectan el lote; no se puede afirmar costo aterrizado sin política de moneda/prorrateo |
| E2E-02 roles → permisos → venta → comisión | PASS aislado / pendiente exhaustivo | `OperationalTeamJourneyTest`: 2 tests y 42 assertions en SQLite y MariaDB QA; owner crea/asigna vendedor y menús, manager consulta equipo, seller vende con comisión RD$20.00, accountant lee finanzas y seller queda bloqueado para finanzas/cierre/equipo y para bypass API de menús. Falta matriz completa verbo × ruta |
| E2E-05 decants → venta → devolución | PASS aislado | `OperationalDecantReturnJourneyTest`: 1 test y 16 assertions en SQLite y MariaDB QA; venta de 2 decants, consumo 10 ml, devolución de 1, restauración de 5 ml/costo FIFO y replay sin duplicación |
| E2E-06 venta offline → kill → reconexión → ACK | PASS acotado | POS live y replay pasan; caída física de red sigue pendiente |
| E2E-07 importador XLSX → mapping → replay | PASS aislado | `InventoryImportLiveIntegrationTest` 1/1; sin duplicados |
| E2E-10 owner Pro multi-tienda → cambio de tienda → vendedor asignado → venta | PASS aislado | `OperationalMultiShopJourneyTest`: 1 test y 16 assertions en SQLite y MariaDB QA; perfumería/ropa segregadas, vendedor limitado a Tienda A, venta en A conciliada y acceso/venta en B rechazados sin mutación |
| E2E-08 venta → devolución parcial → ajuste/reembolso → gasto → arqueo | REQUIERE_DECISION | La devolución actual restaura inventario/FIFO y P&L, pero no define método de reembolso ni movimiento financiero; no se declara caja conciliada hasta elegir la política F-036 |

Los estados no listados permanecen `NOT_RUN` hasta contar con evidencia reproducible.
