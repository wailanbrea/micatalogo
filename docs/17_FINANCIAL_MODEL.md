# Modelo Financiero y Reglas Contables (Fase 1) — MiCatalogo

## 1. Principio Fundamental: Separación Contable y de Flujo

El sistema diferencia estrictamente entre el **Estado de Resultados (P&L / Ganancias y Pérdidas)** y el **Flujo de Efectivo (Cash Flow)**. Ninguna cifra contable de ganancia se confunde con liquidez disponible en caja o banco.

| Concepto | Definición | Impacta P&L | Impacta Flujo de Efectivo |
| :--- | :--- | :---: | :---: |
| **Venta al contado** | Venta cobrada al momento de facturar. | Sí (Ingreso) | Sí (Entrada inmediata) |
| **Venta a crédito** | Venta entregada con promesa de pago futura. | Sí (Ingreso) | No (Crea Cuenta por Cobrar) |
| **Cobro de crédito** | Abono de un cliente a su deuda pendiente. | No (Ya se reconoció ingreso) | Sí (Entrada de efectivo) |
| **Gasto operativo incurrido** | Obligación o servicio devengado en el período. | Sí (Gasto) | Solo porción pagada |
| **Pago a gasto pendiente** | Abono a un gasto previamente registrado a crédito. | No (Ya se reconoció gasto) | Sí (Salida de efectivo) |
| **Costo de Venta (FIFO COGS)** | Costo histórico exacto de la mercancía despachada. | Sí (Deducción directa) | No (El flujo ocurrió al comprar inventario) |
| **Impuestos (ITBIS / IVA)** | Recaudación fiscal por cuenta del Estado. | No (Pasivo fiscal neutral) | Sí (Flujo recaudado) |

---

## 2. Aritmética y Manejo Determinista del Dinero (`App\Support\Money`)

- **Prohibición de `float` en persistencia y lógica crítica**: Todas las operaciones contables se ejecutan en **centavos enteros (`int`)** o representaciones fijas formateadas (`BCMath` / strings decimales con 2 decimales).
- **Conversiones seguras**:
  - `Money::toCents('1,250.75')` = `125075`.
  - `Money::toDecimal(125075)` = `'1250.75'`.
  - Tolerancia de redondeo: Redondeo bancario simétrico de mitad al entero par (`PHP_ROUND_HALF_EVEN`), estrictamente controlado a 0 centavos en balances.
- **Validación de suma en pagos**:
  $$\text{Pagos recibidos (centavos)} + \text{Crédito asignado (centavos)} = \text{Total factura (centavos)}$$

---

## 3. Fórmulas del Estado de Resultados (Income Statement / P&L)

El Estado de Resultados se calcula sobre el período seleccionado (`from_date` a `to_date`) usando la fecha de emisión (`issued_at` / `occurred_at`):

### 3.1. Ingresos
1. **Ventas Brutas (`gross_sales`)**:
   $$\text{Ventas Brutas} = \sum (\text{items.line\_total} - \text{items.tax}) + \sum \text{invoices.discount}$$
   *(Suma de los precios bases de venta antes de descuentos e impuestos).*
2. **Descuentos (`discounts`)**:
   $$\text{Descuentos} = \sum \text{items.discount} + \sum \text{invoices.discount}$$
3. **Devoluciones (`returns`)**:
   $$\text{Devoluciones} = \sum (\text{returned\_items.line\_total} - \text{returned\_items.tax})$$
   *(Monto base devuelto a clientes sin ITBIS).*
4. **Ventas Netas (`net_sales`)**:
   $$\text{Ventas Netas} = \text{Ventas Brutas} - \text{Descuentos} - \text{Devoluciones}$$
   *El ITBIS/IVA nunca forma parte de las ventas netas ni de las ganancias.*

### 3.2. Costos y Márgenes
5. **Costo de Mercancía Vendida (COGS FIFO, `fifo_cogs`)**:
   $$\text{COGS} = \sum (\text{sales.total\_cost\_cents}) - \sum (\text{returns.restocked\_cost\_cents})$$
   *(Calculado mediante lotes FIFO históricos `inventory_lots` consumidos en cada venta).*
6. **Ganancia Bruta (`gross_profit`)**:
   $$\text{Ganancia Bruta} = \text{Ventas Netas} - \text{COGS}$$
7. **Margen Bruto (`gross_margin_percent`)**:
   $$\text{Margen Bruto} = \frac{\text{Ganancia Bruta}}{\text{Ventas Netas}} \times 100$$
8. **Cobertura Ponderada de Costos (`revenue_cost_coverage`)**:
   $$\text{Cobertura} = \frac{\text{Ingresos de ítems con costo FIFO conocido}}{\text{Ventas Netas}} \times 100$$
   *(Si la cobertura es inferior al 100%, el dashboard emite una alerta auditada de estimación de costos).*

### 3.3. Gastos y Utilidad Operativa
9. **Gastos Operativos (`operating_expenses_total`)**:
   $$\text{Gastos Operativos} = \sum_{\text{categoría}} \text{expenses.amount\_cents (incurridos en el rango)}$$
10. **Comisiones de Vendedores (`commissions`)**:
    $$\text{Comisiones} = \sum \text{invoices.commission\_amount}$$
11. **Ganancia Operativa (`operating_profit`)**:
    $$\text{Ganancia Operativa} = \text{Ganancia Bruta} - \text{Gastos Operativos} - \text{Comisiones}$$
12. **Margen Operativo (`operating_margin_percent`)**:
    $$\text{Margen Operativo} = \frac{\text{Ganancia Operativa}}{\text{Ventas Netas}} \times 100$$

---

## 4. Flujo de Efectivo (Cash Flow)

Representa el dinero real que entró y salió de la empresa en el período:

### 4.1. Entradas Operativas (`cash_inflow`)
- Cobros inmediatos de ventas (`invoice_payments` con método `cash`, `card`, `bank_transfer`, etc.).
- Cobros de deudas de clientes (`customer_account_entries` de tipo `payment`).
- Aportes de capital o ingresos manuales a caja (`cash_movements` de tipo `deposit`).

### 4.2. Salidas Operativas (`cash_outflow`)
- Pagos reales de gastos (`expense_payments` efectuados en el rango).
- Devoluciones de dinero en efectivo a clientes (`returns`).
- Retiros de efectivo / transferencias de caja (`cash_movements` de tipo `withdrawal`).

### 4.3. Flujo Neto (`net_cash_flow`)
$$\text{Flujo Neto} = \text{Entradas de Efectivo} - \text{Salidas de Efectivo}$$

---

## 5. Control de Gastos y Abonos Parciales (`Expense` & `ExpensePayment`)

- Todo gasto registrado almacena su monto devengado (`amount`), el monto efectivamente pagado a la fecha (`amount_paid_cents`), y su estado:
  - `paid`: `amount_paid_cents == amount_cents`.
  - `partial`: `0 < amount_paid_cents < amount_cents`.
  - `pending`: `amount_paid_cents == 0`.
- Cuando un gasto se registra con pago parcial o sin pago, la porción pendiente genera una cuenta pendiente.
- Al registrar un abono (`POST /gastos/{expense}/pagos` o `POST /api/v1/shops/{shop}/expenses/{expense}/payments`):
  1. Se valida que el abono no supere el saldo pendiente `unpaidAmount()`.
  2. Se crea un registro en `expense_payments`.
  3. Si el método fue efectivo y el usuario tiene una sesión de caja abierta, se registra automáticamente la salida en `cash_movements`.
  4. Se actualizan `amount_paid_cents` y `payment_status` del gasto.
  5. Se garantiza idempotencia mediante `client_operation_uuid` y hash SHA-256.

---

## 6. Arqueo y Control de Caja (`CashRegisterSession`)

- **Aislamiento por usuario y tienda**: Cada vendedor opera su propia sesión de caja.
  - Una sesión abierta posee `is_open_flag = 1`.
  - Un índice único compuesto `[shop_id, user_id, is_open_flag]` en base de datos impide a nivel de motor aperturas duplicadas concurrentes.
- **Permisos de supervisión**: Solo el propietario (`shop->user_id`) o un usuario con permiso explícito puede visualizar, auditar o cerrar la caja de otro vendedor.
- **Conciliación**:
  $$\text{Balance Esperado} = \text{Monto Apertura} + \sum \text{Ventas en Efectivo} + \sum \text{Cobros Crédito} + \sum \text{Aportes} - \sum \text{Pagos Gastos} - \sum \text{Retiros}$$
  $$\text{Diferencia} = \text{Monto Cierre Declarado} - \text{Balance Esperado}$$

---

## 7. Cuentas por Cobrar, Antigüedad (Aging) y Amortización FIFO de Cobros

- **Amortización FIFO a Facturas Impagas**:
  Al registrar un cobro de cliente (`PaymentService::recordCustomerDebtPayment`), el monto recibido se aplica automáticamente en orden cronológico estricto (FIFO) a las facturas pendientes (`COALESCE(due_date, issued_at) ASC, issued_at ASC, id ASC`):
  - Por cada factura amortizada, se genera un registro en `invoice_payments` con el campo `customer_account_entry_id` vinculado al asiento contable en `customer_account_entries`.
  - El estado de la factura se actualiza automáticamente a `paid` si el saldo remanente llega a cero, o `partial` si queda deuda.
  - Si el cobro es en efectivo y hay una sesión de caja abierta, se registra exactamente un `CashMovement` en la caja física.
- **Aging y Reconciliación Auditada**:
  - El cálculo de antigüedad de deuda clasifica cada factura impaga por su vencimiento real (`due_date` o `issued_at`):
    $$\text{Deuda Pendiente Factura} = \text{invoice.total} - \sum \text{invoice.payments.amount} - \sum \text{invoice.returns.amount}$$
  - Tramos de antigüedad:
    - **Al día / 0 a 30 días**: Facturas no vencidas o con menos de 30 días de vencimiento.
    - **31 a 60 días**: Vencimiento entre 31 y 60 días atrás.
    - **61 a 90 días**: Vencimiento entre 61 y 90 días atrás.
    - **Más de 90 días**: Vencimiento superior a 90 días.
  - Ecuación de Reconciliación:
    $$\text{total\_receivable} = \text{invoices\_total} + \text{unallocated\_receivables}$$
    $$\text{reconciliation\_difference} = \text{total\_receivable} - (\text{invoices\_total} + \text{unallocated\_receivables}) = 0$$

---

## 8. Idempotencia y Resiliencia en Red

Para prevenir registros duplicados por reintentos de red en clientes Web y móviles Android:
- Las tablas `expenses`, `expense_payments`, `invoice_payments`, `cash_movements` y `cash_register_sessions` incorporan:
  - `client_operation_uuid`: Identificador universal único estándar UUIDv4 (`Str::isUuid(...)`) generado en el cliente antes de transmitir (sin sufijos incompatibles como `-pay`).
  - Índices únicos compuestos `UNIQUE(shop_id, client_operation_uuid)` a nivel de motor de base de datos.
  - `payload_sha256`: Hash criptográfico SHA-256 de los parámetros del payload.
- Comportamiento ante reintentos:
  - Si el hash coincide: Retorna la respuesta previa de forma idéntica e idempotente con código `200` o `201`.
  - Si el hash o montos difieren: Rechaza con código `HTTP 409 Conflict` evitando corrupción o desalineación de datos.
  - En cierre de caja (`CashRegisterService::closeSession`): Si la sesión ya está cerrada y se reenvía un arqueo con monto contado dispar, se rechaza inmediatamente con `409 Conflict`.

---

## 9. Prevención de Doble Contabilización en Flujo de Efectivo

Para garantizar que ningún peso se cuente dos veces en el Flujo de Efectivo:
- Los cobros de ventas de mostrador (`invoice_payments`) se contabilizan filtrando estrictamente `whereNull('customer_account_entry_id')`.
- Los cobros de deudas de clientes entran a través de `customer_account_entries` (tipo `payment`).
- De esta manera, el cobro de una factura a crédito se refleja exactamente una vez en la tesorería (al momento en que el cliente abona su cuenta), evitando duplicar el ingreso en el reporte de caja o cash flow.
