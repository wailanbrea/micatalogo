# Auditoría funcional de Puntto y paridad de MiCatalogo

Fecha de la evidencia: 6 de octubre de 2026. La aplicación se recorrió en el
emulador `emulator-5554`, paquete `com.puntto.app`, con la sesión de prueba
`LaCasaRD`. Las operaciones de cobro se dejaron sin confirmar; no se creó una
venta adicional durante esta auditoría.

## Menú observado en Puntto

### Operación

- Resumen
- Terminal
- Ventas
- Cotizaciones
- Pedidos
- Encargos
- Envíos
- Cierre de día
- Descargar la app

### Compras

- Contenedores
- Cargas
- Suplidores
- Facturas

### Catálogo

- Inventario
- Fotos
- Mi tienda
- Servicios
- Salud de precios
- Precios automáticos
- Decants
- Marcas y atributos
- Importar

### Cobros

- Crédito
- Clientes

### Finanzas

- Ganancias
- Ajustes de inventario
- Gastos
- Socios

### Análisis

- Reportes

### Equipo

- Comisiones
- Autorizaciones

### Ajustes

- Configuración
- Equipo
- Contador
- Mi cuenta
- Novedades
- Ayuda
- Practicar sin miedo
- Soporte
- Ver tienda

## Flujo de venta observado

1. `Terminal` inicia en la vista `Detalle` y permite cambiar a `Mayoreo`.
2. La búsqueda acepta texto o escáner; existen filtros de `Productos`, `Decants`
   y `Servicios`.
3. Cada artículo se agrega desde una fila y el carrito permite disminuir,
   aumentar y editar la línea.
4. `Cobrar` abre una hoja/modal inferior responsive con:
   - total grande y etiqueta del tipo de venta;
   - artículos, cantidades y precio por unidad;
   - `Descuento o nota`;
   - subtotal y margen estimado;
   - cliente general o cliente registrado;
   - fecha de la venta;
   - efectivo, transferencia y tarjeta;
   - recibido, cambio y montos rápidos;
   - pago mixto y venta a crédito;
   - nota en el recibo;
   - confirmación fija y opción de poner en espera.
5. La pantalla de `Ventas` resume periodo, total vendido, promedio por venta,
   porcentaje a crédito, búsqueda, filtros y acciones por factura.
6. `Cotizaciones` separa vigentes, por convertir y vencidas, y muestra cliente,
   vigencia, artículos, estado y total.
7. `Pedidos` presenta un estado vacío guiado y enlaza compartir la tienda.

## Estado de paridad en MiCatalogo

La navegación del panel publicado ya expone las secciones y rutas equivalentes,
además de herramientas propias de MiCatalogo como caja, métricas, lotes FIFO y
menú administrativo.

En el POS web se publicó primero el commit `c3a46ec` y después se consolidó el
modal moderno en `0995845`:

- el carrito mantiene el flujo existente;
- `Cobrar venta` abre ahora una hoja/modal responsive con fondo atenuado,
  total destacado, cierre accesible y confirmación separada;
- la revisión muestra el detalle con imágenes, tipo de cobro, método de pago,
  cliente, recibido, cambio, descuento, nota y total antes de confirmar;
- contado, pago mixto, crédito, cliente y vencimiento siguen conectados a los
  mismos campos contables y al mismo endpoint;
- se verificó en la sesión web publicada el cambio a crédito y la aparición del
  cliente/saldo/vencimiento;
- el carrito de prueba fue vaciado sin registrar una venta.

La pantalla web de Ventas también recibió filtros de período, estado y búsqueda
por factura, cliente o vendedor en `3577363`. La pantalla de Fotos recibió un
gestor de pendientes/con foto/todos, búsqueda por producto y selección segura de
sugerencias en `0619b33`. Ambos cambios fueron probados con la suite del módulo
y publicados sin migraciones ni datos demo.

En Android, la hoja `CheckoutReviewSheet` conserva el mismo flujo visual y
contable en la release firmada `1.0.40` (`2ccc138`). La release siguiente
`1.0.41` (`0465f79`, `versionCode 42`) agrega a Ventas búsqueda por factura o
cliente, período Todo y estado Anuladas. El APK fue instalado en
`emulator-5554`; el manifiesto público y el hash/tamaño del archivo publicado
fueron verificados.

La auditoría de navegación detectó que la pestaña inferior `Más` repetía Caja,
Inventario, Finanzas, Cobros, Devoluciones, Cargas, Proveedores, Impresoras y
Ajustes, todos ya presentes en el menú hamburguesa. El commit `93ea985` retiró
esa ruta duplicada, mantuvo la clave de permisos heredada para no invalidar
perfiles existentes y dejó el menú hamburguesa como único origen de navegación.
La release firmada `1.0.43` (`versionCode 44`) fue instalada en el emulador y
verificada contra el endpoint público.

## Diferencias que siguen siendo trabajo pendiente

La paridad visual y funcional todavía no debe declararse completa. El POS web
de MiCatalogo aún debe incorporar, con pruebas contables equivalentes:

- edición de precio por línea con autorización cuando corresponda;
- poner una venta en espera y recuperarla;
- iniciar una cotización directamente desde la terminal;
- confirmación posterior con recibo/factura y acciones de compartir;
- recorrer y probar cada pantalla del menú en Android con sus acciones de
  escritura, no solo comprobar que la ruta exista.

Ya se incorporaron al modal publicado el recibido, cambio, montos rápidos,
descuento, nota de recibo y el caso de crédito sin un pago de RD$0. Estos puntos
quedaron cubiertos por la prueba de POS y por la prueba contable de crédito.

## Auditoría adicional: cierre e inventario

### Cierre de día

Puntto muestra, en tarjetas separadas, ventas cobradas por método, cantidad de
cobros, abonos recibidos, gastos, devoluciones y el efectivo esperado. Cuando hay
una sesión abierta también presenta Entró, Salió, Deberías tener, una nota sobre
qué medios se cuentan en la gaveta, el arqueo y la advertencia de que el cierre
restringe correcciones posteriores.

MiCatalogo tenía el cierre conectado a la sesión real de caja, pero la pantalla
solo exponía tres KPI y el formulario de cerrar caja. El commit `671beb2` agregó
el resumen detallado usando `InvoicePayment`, gastos, devoluciones y
`CashRegisterService`, sin insertar datos ni crear otra contabilidad. La ruta
publicada fue verificada con la sesión web: muestra Ventas cobradas, Abonos
recibidos, Gastos, Devoluciones y la conciliación de efectivo; si no hay sesión,
invita a abrir Control de caja.

### Inventario

La pantalla de Inventario de Puntto observada contiene acciones rápidas para
añadir del catálogo, precios y costos, movimientos, importar, combo y producto;
tabs de activos/archivados/combos; aviso de productos sin foto; capital al costo;
unidades y existencia; nivel bajo; margen promedio; búsqueda/filtros y una tabla
con selección y acciones por producto. Su formulario de Nuevo producto separa
información, atributos, foto, precio/costo, oferta e inventario inicial por lote.

MiCatalogo ya tenía importación, subida masiva, creación, edición, imágenes,
FIFO y filtros. El commit `3f0de61` agregó al índice los indicadores de activos,
archivados, capital al costo, unidades controladas, nivel bajo, agotados, margen
promedio y productos sin fotografía. La ruta publicada fue verificada con los
datos actuales de BSolutions.dev y conserva la tabla y sus acciones sin cambiar
registros.

### Evidencia de navegación

Las capturas y XML de Puntto se conservaron en el directorio de visualizaciones
de la sesión: `puntto-cierre-bottom.*`, `puntto-inventario-2.png` y
`puntto-product-form.*`. El formulario de producto se abrió solo para inspección;
no se guardó un producto nuevo ni se ejecutó una operación financiera.

La auditoría de rutas web también cubrió Clientes, Crédito, Ganancias, Gastos,
Reportes, Equipo, Comisiones, Ayuda, Salud de precios, Precios automáticos,
Decants, Importar y Mi tienda. Estos módulos ya exponen contenido guiado y
acciones propias; falta completar las pruebas de mutación equivalentes en
Android. No se deben resolver con datos demo en producción ni con migraciones
destructivas.
