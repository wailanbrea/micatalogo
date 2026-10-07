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

### Menú ampliado y Ganancias

En una segunda pasada del menú móvil de Puntto se verificaron los grupos inferiores:

- Catálogo: Mi tienda, Servicios, Salud de precios, Precios automáticos, Decants,
  Marcas y atributos e Importar.
- Cobros: Crédito y Clientes.
- Finanzas: Ganancias, Ajustes de inventario, Gastos y Socios.
- Análisis: Reportes.
- Equipo: Comisiones y Autorizaciones.
- Ajustes: Configuración, Equipo, Contador, Mi cuenta, Novedades, Ayuda, Practicar
  sin miedo y Soporte.

El buscador del drawer encuentra módulos por nombre y reduce el menú a los resultados.
La pantalla `Ganancias` verificada en Puntto presenta selector Hoy / Este mes / Mes
pasado / Más, navegación del mes, ganancia neta, ventas, costo de lo vendido por FIFO,
ganancia bruta, gastos y pérdidas de inventario, incluyendo comparación contra el mismo
período anterior.

La pantalla `Reportes` de Puntto agrega acciones de exportación CSV/Excel, pestañas de
Ventas, Servicios, Visitas y Enlaces, período Hoy / Este mes / Últimos 7, ventas y
ganancia del período, transacciones, inventario al costo y productos más vendidos con
ganancia por producto.

MiCatalogo ya expone la fuente financiera equivalente en `Ganancias y resumen`,
`Gastos`, inventario FIFO y reportes; queda pendiente verificar visualmente cada uno
en Android y validar que el detalle mensual tenga la misma profundidad que Puntto.

### Cierre de día

Puntto muestra el estado de la jornada, el selector de fecha y cuatro bloques
independientes: ventas cobradas, abonos recibidos, gastos y devoluciones. Después
presenta el efectivo esperado —entradas, salidas y saldo que debería haber—, permite
introducir el efectivo contado y ofrece `Cerrar el día`. El arqueo es opcional, pero
al cerrar se advierte que ya no se podrán registrar o corregir ventas, gastos ni
abonos; solo el owner puede reabrirlo.

MiCatalogo ya tiene el equivalente funcional en web mediante sesión de caja, monto
contado, diferencia, cierre idempotente y bloqueo posterior. La pantalla Android
`Cierre de día` muestra ventas, abonos, gastos, devoluciones y resumen de caja; queda
pendiente completar la comparación visual de estados abierto/cerrado y la prueba de
reapertura con owner.

## MiCatalogo verificado

- La APK 1.0.49/código 50 abrió el panel, permitió iniciar sesión, mostró dashboard,
  drawer de menús, Terminal, carrito y el filtro horizontal de categorías.
- La Terminal Android usa tarjetas con imagen, búsqueda, escáner, modos Detalle/Mayoreo, carrito inferior y hoja de cobro.
- El carrito de MiCatalogo ya permite cliente, contado/crédito, tarjeta, transferencia y pago mixto.
- El flujo de crédito exige cliente y el backend conserva validación de pagos, FIFO, inventario, caja y cuentas por cobrar.
- Los módulos de decants muestran origen, volumen disponible, costo compartido y validación de botella fuente.
- El backend tiene rutas protegidas para POS, cotizaciones, pedidos, compras, caja, clientes, inventario, importación, imágenes, precios, comisiones y módulos de menú.

## Diferencias corregidas en esta auditoría

Puntto mostraba el efectivo recibido y el vuelto antes de confirmar. MiCatalogo confirmaba el total, pero no mostraba esa diferencia en el modal Android.

En la versión 1.0.48 se agregó al cobro en efectivo:

- Campo `Recibido` en moneda local.
- Cálculo de `Devolver` usando enteros monetarios.
- Atajos Exacto, 1,000, 2,000 y 5,000.
- Bloqueo de confirmación si el efectivo es menor que el total.
- El asiento de la venta permanece por el total real; el vuelto es una diferencia informativa para el cajero.

Puntto también permite acotar rápidamente el catálogo desde Terminal. En Android 1.0.49
MiCatalogo incorpora una fila horizontal de categorías sincronizadas, con `Todos` y
selección individual; el filtro se combina con la búsqueda, el modo mayorista y el
carrito existente. Solo se muestran categorías que tienen productos en la tienda activa.

Durante la verificación de navegación Android se detectó que el destino financiero se
mostraba como `Finanzas` aunque Puntto lo identifica como `Ganancias`. Se corrigió el
nombre de la opción y del breadcrumb para que el drawer muestre `Ganancias` y la
pantalla quede como `Finanzas / Ganancias`; esto también permite encontrarla buscando
`Ganancias`.

La comparación de `Reportes` detectó otra diferencia: Puntto muestra el valor actual
del inventario al costo y accesos directos a sus análisis. MiCatalogo ahora agrega al
read model el valor FIFO disponible, marca el módulo como operativo y ofrece `Ver
ganancias y resumen` y `Abrir métricas`. En Android 1.0.51 ambos botones están
conectados; el primero fue probado y abrió `Finanzas / Ganancias` sin salir de la app.

La versión 1.0.53 completa también las cuatro secciones observadas en Puntto:
`Ventas`, `Servicios`, `Visitas` y `Enlaces`. Se sirven desde el mismo módulo
protegido de la web y de la API Android; las pestañas son de solo lectura y reutilizan
ventas/FIFO, catálogo sin inventario, métricas de vitrina y enlaces públicos existentes.
No crean productos, ventas, visitas artificiales ni movimientos contables.

La versión 1.0.54 elimina el segundo título interno del módulo Android: el encabezado
del shell queda como la única identificación de la pantalla, mientras la descripción y
el contenido contextual permanecen disponibles.

La exportación también quedó alineada: el panel ofrece CSV y Excel; la API autenticada
expone ambos formatos y Android 1.0.52 los guarda en Descargas usando el token de la
sesión. Las pruebas verifican que la exportación sea de solo lectura y no cree ni
modifique productos, ventas o inventario.

## Evidencia automatizada

La suite crítica del backend ejecutada durante la auditoría pasó **45 pruebas y 359 aserciones**, cubriendo:

- POS contado, crédito, pago mixto, mayorista y decants.
- FIFO, compras, lotes, devoluciones e inventario.
- Caja, gastos, ganancias, cuentas por cobrar y cierre.
- Comisiones y permisos de vendedores.
- Operaciones móviles, imágenes, importación e idempotencia.

La compilación release Android 1.0.49 (código 50) también pasó las pruebas unitarias y
produjo un APK firmado/no-debuggable.

La batería enfocada en autenticación, permisos, menú de vendedor, actualización,
alcance por tienda e idempotencia pasó **32 pruebas y 136 aserciones**. Incluye la
comprobación de que las rutas administrativas no queden disponibles para un vendedor
sin permisos.

## Pendientes de la auditoría amplia

1. Recompilar y publicar la corrección del rótulo `Ganancias` en Android; verificar en
   el emulador que buscar `Ganancias` abre `Finanzas / Ganancias`.
2. Recorrer cada pantalla de Puntto y MiCatalogo con una matriz de botones, validaciones, estados vacíos, permisos y respuestas de error.
3. Repetir la matriz para owner y vendedor, comprobando que cada menú administrativo se oculte y que la ruta devuelva 403 cuando corresponde.
4. Ejecutar una venta controlada con caja abierta en un entorno de prueba y verificar factura, inventario, caja, ganancia y comisión de extremo a extremo.
5. Comparar visualmente cotizaciones, compras, clientes, caja, ganancias, decants,
   importación y configuración; la paridad visual completa todavía no está demostrada.
6. Completar la matriz de estados y validaciones de cada pantalla y repetirla para
   owner y vendedor con pruebas equivalentes en web y Android.

