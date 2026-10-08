# Invariantes financieros e inventario

Todas las comprobaciones deben usar centavos enteros y datos sintéticos. Nunca cambiar una factura histórica al cambiar un precio actual.

## Ventas y cartera

- `total = subtotal - descuentos + impuestos` con redondeo determinista.
- `pagado + saldo_cliente = total`.
- Una UUID y payload idénticos producen un solo ACK; una UUID con payload distinto produce conflicto.
- Transferencia y tarjeta no incrementan efectivo.

## Inventario y FIFO

- `stock_final = stock_inicial + entradas - ventas + devoluciones ± ajustes`.
- Cada lote conserva su costo. Para 10 unidades a RD$100 y 10 a RD$130, vender 12 produce COGS `10×100 + 2×130 = RD$1,260`.
- `ganancia_bruta = ventas_sin_impuesto - COGS_snapshot`.
- Una recepción, venta, devolución o ajuste repetido no duplica movimiento.
- Una devolución pagada debe invertir también el efecto monetario una sola vez; la política de método de reembolso aún está pendiente y no se debe declarar conciliación de caja hasta definirla.

## Caja, gastos y cierre

- `efectivo_esperado = apertura + entradas_cash - salidas_cash + ajustes`.
- Escenario de control: venta cash RD$400 + cobro cash RD$120 - gasto cash RD$40 = RD$480; arqueo RD$475 ⇒ diferencia -RD$5.
- Gasto devengado RD$90 con pago cash RD$40 deja P&L -RD$90, efectivo -RD$40 y pendiente RD$50.
- Un cierre por tienda/fecha no se duplica ni se sobreescribe con un conteo incompatible.
- El costo de una compra internacional debe declarar si ya está en moneda base o si se convierte y aterriza; no se puede usar tasa, flete o aduana en FIFO sin una regla de prorrateo auditable.

## Decants

- Si hay 80 ml y se venden dos decants de 10 ml, la reducción neta es 20 ml, salvo reservas modeladas explícitamente.
- El costo proporcional usa el lote/origen real y no se atribuye dos veces.
- Un negocio sin capability no puede abrir ni crear decants aunque manipule el cliente.

## Datasets QA y resultados verificados

Todos los datasets se ejecutan con `RefreshDatabase` en SQLite o con la base
`micatalogo_qa_20261008` en MariaDB QA. No contienen correos, tiendas ni
transacciones reales.

| Dataset | Entrada | Resultado esperado y observado |
|---|---|---|
| FIN-001 | Compra de 3 unidades a RD$100; venta a RD$250 con RD$400 cash, RD$150 tarjeta y RD$200 crédito; cobro posterior RD$120 cash; gasto RD$90, de los cuales RD$40 se pagan | COGS RD$300, ganancia bruta RD$450, ganancia operativa RD$360, cartera RD$80, efectivo neto RD$480, tarjeta RD$150 y gasto pendiente RD$50; diferencia de cierre RD$0.00 |
| PUR-004 | Lotes 10×RD$100 y 10×RD$130; venta de 12 | COGS RD$1,260 y 8 unidades remanentes del segundo lote |
| E2E-05 | Dos decants de 5 ml desde botella fuente; devolución de uno | Consumo neto de 5 ml, restauración del costo FIFO y una sola devolución tras replay |
| E2E-10 | Owner Pro con perfumería y ropa; vendedor asignado solo a perfumería | La venta en A crea una factura y reduce solo A; la operación en B devuelve 404 y deja B sin factura ni reducción de stock |

| E2E-11 | Contenedor USD: 10 unidades × 10.00, tasa 60, flete 120, aduana 80 | Observado: subtotal/total 100.00 y lote 10.00; la conversión/prorrateo no ocurre. Requiere decisión antes de declarar FIFO y margen aterrizados |

### Devolución pagada — decisión requerida

La implementación actual permite devolver una venta pagada y reconcilia ventas netas,
impuestos, stock y costo FIFO, pero no tiene una semántica financiera completa porque
el request no indica cómo se devuelve el dinero. Antes de cerrar `RETURN-001`,
`RETURN-002`, `CASH-002`, `CLOSE-004` y `E2E-08`, debe elegirse una política:

1. **Reembolso por método original:** guardar método/cuenta, crear un movimiento
   negativo idempotente y reflejarlo en caja/flujo; para tarjeta o transferencia puede
   requerir referencia o comprobante.
2. **Crédito/ajuste pendiente:** no sacar efectivo automáticamente; registrar un saldo
   a favor o ajuste auditable, con aplicación posterior y su propio impacto financiero.

No se considera PASS financiero una devolución que solo crea `invoice_returns` si la
caja conserva el ingreso original sin una decisión explícita.

## Controles SQL de reconciliación

Estos controles se ejecutan únicamente después de cada jornada dentro de la base
QA seleccionada por el runner. Se comparan por `shop_id`, fecha y referencia; no
se ejecutan contra producción.

```sql
-- Una factura debe cuadrar sus líneas y pagos/crédito.
SELECT i.id, i.shop_id, i.total,
       COALESCE(lines.lines_total_cents, 0) AS lines_total_cents,
       COALESCE(payments.payments_total_cents, 0) AS payments_total_cents
FROM invoices i
LEFT JOIN (
    SELECT invoice_id, SUM(ROUND(line_total * 100)) AS lines_total_cents
    FROM invoice_items GROUP BY invoice_id
) lines ON lines.invoice_id = i.id
LEFT JOIN (
    SELECT invoice_id, SUM(amount_cents) AS payments_total_cents
    FROM invoice_payments GROUP BY invoice_id
) payments ON payments.invoice_id = i.id;

-- El stock visible debe coincidir con entradas - salidas + devoluciones.
SELECT pi.product_id, pi.stock_quantity,
       COALESCE(SUM(im.quantity), 0) AS movement_balance
FROM product_inventories pi
LEFT JOIN inventory_movements im ON im.product_id = pi.product_id
GROUP BY pi.product_id, pi.stock_quantity;

-- Cartera: saldo de cliente frente a cargos y abonos asignados.
SELECT c.id, c.shop_id, c.balance,
       COALESCE(SUM(ROUND(cae.amount * 100)), 0) AS ledger_balance_cents
FROM customers c
LEFT JOIN customer_account_entries cae ON cae.customer_id = c.id
GROUP BY c.id, c.shop_id, c.balance;
```

Las aserciones de Pest además comparan los valores en centavos, el `shop_id`, la
referencia de idempotencia, el lote FIFO y el estado final. La suite completa del
corte terminó en **500 pruebas / 5.104 aserciones PASS** por motor; las tablas
críticas quedaron en cero después de limpiar cada jornada QA.

La regresión F-042 también verifica que una devolución decimal de `RD$0.01` no
introduce una conversión `float` antes de asignar un cobro posterior: la factura
queda con saldo exacto de `0` centavos en SQLite y MariaDB QA.
