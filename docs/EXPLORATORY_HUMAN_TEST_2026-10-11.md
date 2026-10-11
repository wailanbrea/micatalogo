# Pruebas exploratorias como usuario — 11/10/2026

## Entorno y método

- Emulador `emulator-5554`, Android 17.
- Debug `com.bsolutions.micatalogo.offlinecheck.emulatorqa`, 1.0.95 / 96.
- Código Android base: `6560b8a` (`fix: stabilize orders initial loading`).
- Backend local `127.0.0.1:18766`, APP_ENV testing, SQLite QA separada:
  `C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-emulator-complete-20261011.sqlite`.
- Conexión efectiva verificada mediante `qa/emulator_backend.php`.
- Interacciones reales: tocar controles visibles, escribir, pulsar atrás,
  cambiar tiendas, cancelar, repetir pulsaciones. No se inyectaron estados de
  ViewModel ni respuestas de API para provocar los fallos.
- `qa/tap_android_qa.ps1` obtiene la jerarquía actual y sólo permite tocar el
  paquete QA en el emulador; no interactúa con la app normal.
- No se modificó código de negocio para ocultar los fallos. Los registros QA
  y los logs se conservaron. No hubo operaciones sobre producción/VPS.

## Hallazgos confirmados

### EXP-001 — cierre de Pedidos ante crédito insuficiente — Alta

**Pasos:**
1. QA Tienda B, pedido `QA-PEDIDO-B`, dos unidades, total RD$600.
2. Confirmar pedido → Crédito → Nuevo cliente.
3. Crear `QA Limite B` con límite RD$100.
4. El cliente queda seleccionado y el botón Confirmar venta sigue habilitado.
5. Pulsar Confirmar venta.

**Resultado real:** el servidor rechaza el crédito; la app se cierra.

```
10-11 12:54:33.128 PID 6354 FATAL EXCEPTION: main
java.lang.IllegalStateException:
El crédito solicitado supera el límite disponible del cliente.
OrdersViewModel.kt:218
```

**Causa localizada:** se lanza `error(...)` dentro de `onSuccess`, fuera del
`runCatching`; no pasa por `onFailure`. Falta además anticipar el límite disponible
en la confirmación del pedido.

**Datos:** tras el rechazo, B seguía con stock 20, sin facturas, saldo cero y
pedido sin confirmar. El cliente creado persistió correctamente.

**Evidencia:** `explore-insufficient-credit-ready-20261011-085342-7b5d42e9.png`
y `qa-exploratory-crashes-20261011.log` en Temp/opencode.

### EXP-002 — doble toque registra dos abonos — Alta, impacto financiero

**Pasos:**
1. Confirmar correctamente el pedido B a crédito con `QA Cliente B Activo`;
   deuda inicial RD$600.
2. Abrir Crédito/Cobros e introducir RD$200.
3. Dar dos toques rápidos al botón Cobrar.

**Resultado real:** se cobran RD$400; saldo final RD$200.

**Contraste de persistencia:**
- `invoice_payments` id 3: RD$200, UUID `0cb10844-a15e-44d1-8c0f-1d65aee471c6`.
- `invoice_payments` id 4: RD$200, UUID `13068ff7-dfa5-4885-8234-8cb3d372d5b8`.
- Dos movimientos de caja `customer_payment` por RD$200.

No fue un replay del mismo UUID: la UI creó dos operaciones diferentes.
`CollectionViewModel.collectRemote()` no tiene bloqueo de envío por operación
y genera un UUID distinto por pulsación (`CollectionViewModel.kt:82–105`).

**Esperado:** evitar el segundo envío accidental mientras se procesa el primero,
mantener identidad de reintento y ofrecer una confirmación clara.

**Evidencia:** `explore-double-collection-20261011-091953-fdf05791.png` y salida
del inspector de la base QA, que confirma ambos pagos.

### EXP-003 — Gastos se cierra al abrir su formulario — Alta

**Pasos:** menú → Gastos → Registrar Primer Gasto.

**Resultado real:** la app se cierra antes de permitir introducir datos.

```
10-11 13:23:52.918 PID 12351 FATAL EXCEPTION: main
java.lang.IllegalStateException:
Vertically scrollable component was measured with an infinity maximum
height constraints, which is disallowed.
```

El stack muestra scroll vertical anidado. Revisar `ExpenseCreateDialog`,
`FinanceScreen.kt:1279–1286`, y el contenedor de texto del diálogo compartido.
No se creó ningún gasto en este intento.

**Evidencia:** mismo log de crashes y captura
`explore-expense-form-20261011-092354-497de896.png` (la actividad QA ya terminó).

### EXP-004 — inventario queda desactualizado tras convertir cotización — Media/Alta

**Pasos:** crear cotización de una unidad B por RD$300 → convertir en venta →
abrir Inventario y detalle del producto.

**Resultado real:**
- Servidor: 19 unidades, una unidad vendida y factura pagada de RD$300.
- Inventario de la app: 20 unidades.
- La misma pantalla muestra capital RD$2,280 (19 × RD$120).
- Detalle del producto: 20 unidades, valor RD$2,400 y ventas 30 días = 0.

Se mezclan métricas remotas actuales con stock/historial de caché anterior.
Un bootstrap posterior al cambiar de tienda actualiza el stock, pero no debe ser
necesario hacerlo manualmente después de una conversión.

**Evidencia:** `explore-inventory-20261011-090834-7affcac0.png` y lectura QA de
stock 19/factura RD$300. Después del pedido posterior el servidor quedó en 17;
ese cambio es una operación QA distinta y no invalida la observación anterior.

### EXP-005 — Devoluciones no ofrece la venta de cotización — Brecha funcional

En B existe factura de contado pagada `FAC-20261011-07FRCRDZ`, RD$300, procedente
de la cotización. Devoluciones muestra `No hay ventas elegibles para reembolso`.

`ReturnScreen` utiliza el repositorio de ventas local y filtra ventas CASH
completadas. No presenta esta factura remota. Se verificó la ausencia; queda
por definir/implementar la devolución de este origen desde Android.

**Evidencia:** `explore-returns-paid-quote-20261011-093328-12a036e9.png`.

## Otros recorridos realizados

| Función | Intento | Observación |
| --- | --- | --- |
| Terminal | 21 toques adicionales en + con stock 20 | Cantidad queda limitada a 20 |
| Cobro Terminal | Recibido insuficiente y número enorme | Confirmación bloqueada; sin crash |
| Cotizaciones | Elegir vigencia en el pasado | Calendario deshabilita fechas pasadas |
| Cotizaciones | Crear y dar doble toque al convertir | Una sola factura y una sola unidad descontada |
| Cliente cotización | Abrir selector | Inactivos no seleccionables; se ocultan, a diferencia de Pedidos |
| Entrada de stock | Cantidad cero con costo válido | Se rechaza sin cerrar la app |
| Caja | Abrir con -1 | Error visible; no crea sesión |
| Caja | Dos toques rápidos al abrir con cero | Una sola sesión B |
| Compras/Facturas | Quitar única línea y guardar incompleta | Conserva una línea y no guarda una compra incompleta |
| Cambio de tienda | Cambiar con carrito B cargado | A muestra sólo sus productos y carrito vacío |
| Navegación | Atrás, cancelar hojas, volver a módulos | Funcionó en los recorridos ensayados |

No se certifican todas las combinaciones, hardware Bluetooth/cámara ni resiliencia
offline completa con esta exploración. La apertura del formulario de Gastos
impidió continuar dentro de esa función.

## Estado final de QA

- A: se conservan su factura anterior, stock 18, saldo cero y caja cerrada.
- B: stock 17, factura de cotización RD$300 pagada, pedido RD$600 parcialmente
  pagado, deuda RD$200 y caja abierta con dos cobros de RD$200.
- Los cambios B fueron creados con la interfaz, no con SQL para fabricar un fallo.
- Capturas privadas y log: `C:/Users/waila/AppData/Local/Temp/opencode/`.

## Orden recomendado de corrección

1. Bloqueo/identidad de envío en Cobros: evitar cobros involuntarios dobles.
2. Manejo de respuestas rechazadas en Pedidos: no lanzar excepciones fuera del
   bloque protegido y validar crédito disponible antes de enviar.
3. Corregir restricciones de altura del formulario de Gastos.
4. Invalidar/refrescar caché y métricas tras conversiones remotas.
5. Completar la integración de facturas remotas con Devoluciones.
