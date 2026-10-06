# Auditoría de paridad MiCatalogo ↔ Puntto

Fecha de referencia: 2026-10-06  
Alcance: panel web, API móvil y aplicación Android `com.bsolutions.micatalogo`.

Este documento registra lo observado al recorrer Puntto con una sesión Pro y al
compararlo con MiCatalogo. No contiene credenciales ni datos de autenticación.

## Menú observado en Puntto

| Grupo | Entradas auditadas |
| --- | --- |
| Operación | Resumen, Terminal, Ventas, Cotizaciones, Pedidos, Encargos, Envíos, Cierre de día, Descargar app |
| Compras | Contenedores, Cargas, Suplidores, Facturas |
| Catálogo | Inventario, Fotos, Mi tienda, Servicios, Salud de precios, Precios automáticos, Decants, Marcas y atributos, Importar |
| Cobros | Crédito, Clientes |
| Finanzas | Ganancias, Ajustes de inventario, Gastos, Socios |
| Análisis | Reportes |
| Equipo | Comisiones, Autorizaciones |
| Ajustes | Configuración, Equipo, Contador, Mi cuenta, Novedades, Ayuda, Practicar sin miedo, Soporte |

MiCatalogo conserva además las herramientas propias de administración de la
plataforma. Estas deben permanecer separadas del menú operativo del dueño y del
menú limitado de un vendedor.

## Formularios y validaciones observados

### Operación

- **Terminal:** búsqueda o escaneo, modo Cobrar/Cotizar, lista Detalle/Mayoreo,
  selección de cliente, carrito, pago contado/crédito/mixto, descuento e
  impresión. El cobro debe validar existencias, precio vigente, crédito y caja.
- **Cotizaciones:** búsqueda por número o cliente, carrito con cantidad y precio
  editable, notas y conversión idempotente a venta.
- **Pedidos:** estado pendiente, confirmación de venta y enlace a la orden
  recibida desde catálogo/WhatsApp.
- **Cierre:** día, efectivo esperado, efectivo contado, diferencia y regla de
  que una caja cerrada no se modifica sin reapertura autorizada.

### Compras

- **Contenedor:** documento/factura, suplidor, moneda, notas y recepción. La
  recepción crea un lote separado y conserva el costo de esa compra.
- **Carga:** nombre, moneda, tasa, courier/naviera, guía, ETA, libras y notas.
- **Suplidor:** nombre, contacto, teléfono, correo y moneda de facturación.
- **Factura:** suplidor, monto, moneda, fechas, número de factura y notas.

### Catálogo

- **Mi tienda:** resumen, apariencia, contacto/horario, catálogo, vitrinas,
  anuncios y Google. La guía de finalización cubre logo, WhatsApp, fotos,
  horario, dirección y redes.
- **Salud de precios:** bajo costo, margen bajo, sin precio, sin costo y costo
  dudoso; margen objetivo y precio sugerido.
- **Precios automáticos:** regla, alcance, lista de precios, margen, redondeo y
  política separada para subidas automáticas y bajadas sujetas a revisión.
- **Decants:** presentación en ml vinculada a botella fuente, disponibilidad
  calculada desde los ml compartidos y costo FIFO sin doble valoración.
- **Importar:** Excel/CSV/PDF, previsualización, mapeo, advertencias, imágenes
  opcionales y confirmación como único paso que escribe en el catálogo.

### Cobros, finanzas y equipo

- **Clientes/Crédito:** identidad, WhatsApp, correo, RNC, nivel de precio,
  límite, saldo, antigüedad y abonos FIFO.
- **Gastos:** categoría, descripción, monto, fecha y método de pago; afecta
  rentabilidad al registrarse y caja al pagarse.
- **Socios:** participación, aportes, retiros y distribución enlazados a caja.
- **Comisiones:** vendedor, porcentaje o monto fijo, ventas, devoluciones,
  base y comisión congelada al momento de la venta.
- **Autorizaciones:** solicitudes y permisos sensibles; el servidor sigue
  siendo la autoridad final.
- **Configuración:** moneda, zona horaria, impuestos, rubro, identidad,
  empleados y métodos de pago.

## Estado implementado en MiCatalogo

- El panel web expone el menú agrupado, protege cada módulo por tienda y rol y
  reutiliza los dominios contable, inventario, caja, crédito y FIFO existentes.
- El POS web y Android soportan venta de detalle, mayoreo, crédito y pagos
  mixtos; las pruebas cubren existencias, idempotencia y costos.
- El endpoint móvil de módulos entrega el mismo modelo de lectura que el panel.
  Las mutaciones contables continúan en APIs dedicadas para evitar que una
  consulta móvil cree datos.
- Decants ahora muestra acciones guiadas en web y Android: crear presentación,
  ver inventario compartido y consultar lotes FIFO. La acción de crear
  presentación navega al formulario de Productos nativo en Android.
- Importar abre el selector de archivos Android y el backend conserva el flujo
  de previsualización/confirmación.
- Los roles administrativos y de vendedor se aplican en servidor y se reflejan
  en el menú recibido por la app.

## Brechas pendientes de paridad nativa

Estas brechas no se ocultan como “operativas” hasta tener una prueba de escritura
equivalente:

1. Algunos módulos secundarios de Android son modelos de lectura y abren el
   panel web para mutaciones complejas; todavía no son formularios nativos
   completos como el Terminal.
2. Decants tiene la creación de la presentación y la consulta de inventario
   conectadas, pero aún falta un flujo nativo separado para “abrir botella” y
   “preparar decant” si el negocio necesita registrar esas etapas como eventos.
3. La apariencia de la tienda abierta desde Android usa el navegador externo;
   si el navegador no comparte la sesión, solicita iniciar sesión de nuevo. No
   es una pérdida de sesión de la app, sino una sesión independiente del
   navegador.
4. Debe seguirse ampliando la comparación pantalla por pantalla para que cada
   formulario secundario tenga la misma guía, estados vacíos y validaciones que
   Puntto.

## Evidencia de la release publicada

- Web: `9d7520f release: publish Android 1.0.32 manifest`.
- Android: `0c651ac feat: expose decant workflow in Android`.
- APK: MiCatalogo `1.0.32`, código `33`, firmada y verificada con el certificado
  existente.
- El manifiesto y los bytes descargados del VPS coinciden en hash SHA-256 y
  tamaño.
- La comprobación remota de migraciones no reportó migraciones pendientes.
- Suite web: 383 pruebas, 2049 aserciones. Suite Android unitaria: exitosa.
- Emulador: login, menú, versión 1.0.32, módulo Decants y navegación a Productos
  verificados sin excepción fatal.

