# Auditoría comparativa Puntto / MiCatalogo — 2026-10-07

## Alcance y seguridad

- Revisión realizada en el emulador `emulator-5554`, sin ejecutar operaciones contables confirmadas en Puntto.
- En Puntto se agregó un artículo al carrito únicamente para inspeccionar el cobro y luego se cerró el panel.
- En MiCatalogo se agregaron artículos al carrito únicamente para inspeccionar el cobro y luego se vació sin confirmar la venta.
- No se ejecutaron migraciones, seeders, truncados ni borrados sobre producción.

## Puntto observado

### Navegación

El menú lateral está dividido en los mismos grupos que el panel web:

- Operación: Resumen, Terminal, Ventas, Cotizaciones, Pedidos, Encargos, Envíos, Cierre de día y Descargar la app.
- Compras: Contenedores, Cargas, Suplidores y Facturas.
- Catálogo: Inventario, Fotos, Mi tienda, Servicios, Salud de precios, Precios automáticos, Decants, Marcas y atributos e Importar.
- Cobros: Crédito y Clientes.
- Finanzas: Ganancias, Ajustes de inventario, Gastos y Socios.
- Análisis: Reportes.
- Equipo: Comisiones y Autorizaciones.
- Ajustes: Configuración, Equipo, Contador, Mi cuenta, Novedades, Ayuda, Practicar sin miedo y Soporte.

El drawer usa búsqueda, grupos plegables, estado activo destacado, acceso a la tienda y la cuenta del usuario. En el dashboard aparecen pasos guiados con progreso, enlaces a ayuda y una lección de introducción.

### Terminal y cobro

La Terminal observada incluye:

- Selector Detalle / Mayoreo.
- Búsqueda por producto o escáner.
- Pestañas Productos, Decants y Servicios.
- Productos recientes y lista de selección rápida.
- Carrito persistente con cantidad, precio unitario y total.
- Hoja de cobro con cliente, fecha, descuento o nota, margen, método de pago, recibido, vuelto y confirmación.

### Cotizaciones

Las cotizaciones se crean desde Terminal. La pantalla muestra tarjetas con estados `Vigente`, `Vencida` y `Convertida`, total por convertir, búsqueda por número o cliente y el número de artículos. La conversión sucede desde la cotización y no se mezcla con una venta hasta confirmarla.

## MiCatalogo verificado

- La APK 1.0.47/código 48 abrió el panel, permitió iniciar sesión, mostró dashboard, drawer de menús, Terminal y carrito.
- La Terminal Android usa tarjetas con imagen, búsqueda, escáner, modos Detalle/Mayoreo, carrito inferior y hoja de cobro.
- El carrito de MiCatalogo ya permite cliente, contado/crédito, tarjeta, transferencia y pago mixto.
- El flujo de crédito exige cliente y el backend conserva validación de pagos, FIFO, inventario, caja y cuentas por cobrar.
- Los módulos de decants muestran origen, volumen disponible, costo compartido y validación de botella fuente.
- El backend tiene rutas protegidas para POS, cotizaciones, pedidos, compras, caja, clientes, inventario, importación, imágenes, precios, comisiones y módulos de menú.

## Diferencia corregida en esta auditoría

Puntto mostraba el efectivo recibido y el vuelto antes de confirmar. MiCatalogo confirmaba el total, pero no mostraba esa diferencia en el modal Android.

En la versión 1.0.48 se agregó al cobro en efectivo:

- Campo `Recibido` en moneda local.
- Cálculo de `Devolver` usando enteros monetarios.
- Atajos Exacto, 1,000, 2,000 y 5,000.
- Bloqueo de confirmación si el efectivo es menor que el total.
- El asiento de la venta permanece por el total real; el vuelto es una diferencia informativa para el cajero.

## Evidencia automatizada

La suite crítica del backend ejecutada durante la auditoría pasó **45 pruebas y 359 aserciones**, cubriendo:

- POS contado, crédito, pago mixto, mayorista y decants.
- FIFO, compras, lotes, devoluciones e inventario.
- Caja, gastos, ganancias, cuentas por cobrar y cierre.
- Comisiones y permisos de vendedores.
- Operaciones móviles, imágenes, importación e idempotencia.

La compilación release Android 1.0.48 también pasó las pruebas unitarias y produjo un APK firmado/no-debuggable.

## Pendientes de la auditoría amplia

1. Recorrer cada pantalla de Puntto y MiCatalogo con una matriz de botones, validaciones, estados vacíos, permisos y respuestas de error.
2. Repetir la matriz para owner y vendedor, comprobando que cada menú administrativo se oculte y que la ruta devuelva 403 cuando corresponde.
3. Ejecutar una venta controlada con caja abierta en un entorno de prueba y verificar factura, inventario, caja, ganancia y comisión de extremo a extremo.
4. Comparar visualmente cotizaciones, compras, clientes, caja, ganancias, decants, importación y configuración; la paridad visual completa todavía no está demostrada.

