# Auditoría funcional Puntto ↔ MiCatalogo

> Corte de auditoría: 2026-10-06. La navegación de Puntto se observó con una sesión Pro activa y la de MiCatalogo con la tienda BSolutions.dev. Las comprobaciones fueron de lectura; no se eliminaron datos ni se ejecutaron operaciones destructivas.

## Criterio

Una función cuenta como equivalente cuando existe la entrada de navegación, la pantalla de trabajo, sus validaciones y el resultado persistido. Un texto o una tarjeta de ayuda sin acción no se considera implementación.

## Menú observado

Puntto y MiCatalogo comparten las secciones principales: Operación, Compras, Catálogo, Cobros, Finanzas, Análisis, Equipo y Ajustes. MiCatalogo además separa sus utilidades propias en “Herramientas MiCatalogo” y “Menú administrativo”, lo que conserva la operación del negocio separada de la administración de plataforma.

| Área | Puntto observado | MiCatalogo observado | Estado |
| --- | --- | --- | --- |
| Operación | Resumen, Terminal, Ventas, Cotizaciones, Pedidos, Encargos, Envíos, Cierre de día | Mismos accesos con rutas por tienda | Equivalente inicial |
| Compras | Contenedores, Cargas, Suplidores, Facturas | Mismos módulos | Equivalente inicial |
| Catálogo | Inventario, Fotos, Mi tienda, Servicios, Salud de precios, Precios automáticos, Decants, Marcas y atributos, Importar | Mismos accesos; Decants se filtra por capacidad/tipo de negocio/plan | Revisar paridad de capacidad |
| Cobros | Crédito, Clientes | Crédito, Clientes | Equivalente inicial |
| Finanzas | Ganancias, Ajustes de inventario, Gastos, Socios | Mismos accesos | Equivalente inicial |
| Análisis | Reportes | Reportes | Equivalente inicial |
| Equipo | Comisiones, Autorizaciones | Mismos accesos | Equivalente inicial |
| Ajustes | Configuración, Equipo, Contador, Mi cuenta, Novedades, Ayuda, Practicar sin miedo, Soporte | Mismos accesos; “Equipo” se presenta como “Vendedores” en el contexto de tienda | Revisar terminología |
| Utilidades | Descargar la app, Ver tienda | Caja, Métricas y QR, Compartir catálogo, Productos, Categorías, Subida masiva, Lotes FIFO, Ver tienda | MiCatalogo tiene utilidades adicionales |

## Flujos comparados

### Terminal

Puntto presenta búsqueda/escaner, modos Cobrar/Cotizar, niveles Detalle/Mayoreo, filtros de Productos/Decants/Servicios, tarjetas de producto y carrito con cobro.

MiCatalogo presenta búsqueda, filtros por categoría, tarjetas con foto/stock, carrito y un cobro más completo: Contado, Pago mixto, A crédito, método de pago, cliente, monto recibido, vuelto, descuento y confirmación. El estado observado tenía el modal de cobro abierto y el carrito conservado, por lo que se debe validar su cierre y navegación después de cada confirmación en una prueba controlada.

### Ventas

Puntto ofrece períodos Hoy, Este mes y Últimos 7, búsqueda por número/cliente, filtros, selección de columnas y detalle por venta.

MiCatalogo ofrece períodos, búsqueda, estado (pagada, crédito, abono parcial, anulada), aplicación de filtros, actividad reciente y enlaces a Terminal y Finanzas. La equivalencia de filtros y detalle debe cubrirse con pruebas de cada estado, no solo con el render de la pantalla.

### Mi tienda

Ambas pantallas tienen Resumen, Apariencia, Contacto, Catálogo, Vitrinas, Anuncios y Google.

La guía de MiCatalogo ahora tiene acciones reales:

1. Configurar tienda → información básica.
2. Gestionar productos → catálogo de productos.
3. Ver enlace y QR → Vitrinas.

Puntto también incluye una checklist de seis pendientes y permite editar el horario semanal desde Contacto y horario. En la observación de Puntto cada día permite:

- hora de apertura y cierre;
- abierto 24 horas;
- cerrado;
- copiar el horario del lunes al resto de días.

MiCatalogo ahora guarda `business_hours` como JSON, permite editar los siete días y muestra el horario/estado actual en la vitrina pública. La migración es aditiva y no altera registros anteriores; las tiendas existentes empiezan sin horario configurado hasta que su dueño lo complete.

### Compras

Puntto permite crear un contenedor como borrador, adjuntar o leer una factura, agregar varias líneas y recibirlo después. MiCatalogo tenía inicialmente una sola línea que recibía el inventario de inmediato. Ahora el módulo admite varias líneas, moneda, notas y dos caminos explícitos: “Guardar borrador” (sin movimiento de inventario) y “Recibir inventario”. La recepción posterior crea los lotes FIFO una sola vez y bloquea reintentos duplicados. La lectura automática de factura (foto/PDF/Excel) sigue siendo una fase posterior.

## Faltantes detectados para la siguiente fase

1. Auditar la lectura automática de facturas de compra desde foto/PDF/Excel y convertir su previsualización en líneas confirmables.
2. Verificar que el acceso a Decants coincida con la combinación de tipo de negocio, plan y productos existentes; no debe desaparecer por una resolución de capacidad incorrecta.
3. Recorrer con datos controlados los estados de Ventas, Cotizaciones, Pedidos, Crédito, Cierre de día y Decants, verificando persistencia, inventario, caja y navegación posterior.
4. Auditar los módulos de ayuda de Puntto que no aparecen como menú propio en MiCatalogo: Tandas, Insumos y Recetas. Primero se debe determinar si son funciones aplicables al perfil del negocio o módulos que deben incorporarse al modelo de capacidades.

## Evidencia de la corrección más reciente

La guía de tres pasos de Mi tienda se publicó en `8ca20d4`; el horario semanal en `6acf420`; y la recepción de compras con borradores y múltiples líneas queda cubierta por las pruebas de `SellerCommerceWorkspaceTest`. Después de estos cambios, la suite completa pasa 395 pruebas y la publicación debe verificarse en el VPS antes de declarar cerrada la paridad.
