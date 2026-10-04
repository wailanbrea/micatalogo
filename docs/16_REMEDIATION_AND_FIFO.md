# Correcciones y costos por lote (2026-10-04)

Alcance autorizado: corregir los hallazgos web/API/Android, implementar costos FIFO,
reglas de precio por margen y redondeo (Pro), reportes de ganancia y actualizar APK.

## Orden de trabajo y aceptación

- [x] API: precio efectivo, descuentos/impuestos y venta idempotente.
- [x] Costos: lotes persistentes, asignación FIFO atómica y costo de venta histórico.
- [x] Precios Pro: subidas configurables, bajadas pendientes de aprobación explícita.
- [x] Reporte por producto: ingresos, costo conocido, ganancia y costos desconocidos.
- [ ] Android: abonos idempotentes, instantáneas inmutables, reconciliación y cola completa.
- [x] Pedidos: confirmación a venta sin doble descuento de inventario.
- [x] Dashboard, copy, planes reales, anuncios y metadatos del APK.
- [x] Pruebas web/Android, release firmado y verificación de publicación.
- [x] Producción: respaldo verificado y comparación de identidades antes/después.

## Compatibilidad

No se reconstruyen costos históricos que no se registraron. El stock anterior se
representará como lote inicial con procedencia de saldo previo y costo conocido
solo cuando exista. No se reescriben ventas anteriores ni se borran registros.
Los centavos se conservan al repartir el costo entre lotes, incluso para mililitros.
Gratis y Premium conservan sus límites; Pro es una capacidad nueva sin cobros
automáticos ni precios comerciales inventados. No se siembra producción.

## Pendientes que no deben anunciarse como terminados

La APK publicada sincroniza catálogo/clientes, ventas y nuevos abonos. Sigue pendiente la
cola bidireccional general de productos, compras/reposiciones y devoluciones.
Los cambios locales de esos módulos no se publican en la tienda; su gestión remota
se realiza desde el panel web. Los costos locales Android siguen siendo estimados:
el costo FIFO definitivo se asigna en el servidor al aceptar la venta.
Los abonos de versiones anteriores requieren conciliación; no se reenvían
automáticamente porque las ventas originales pudieron subir con crédito reducido.

Validación: 227 pruebas web (SQLite en memoria); 43 unitarias y 73 instrumentadas Android
en emulador, sin fallos; build de producción y firma del APK 1.0.8/code 9 verificados.
Certificado idéntico al APK 1.0.7. Tamaño 16,131,920 bytes; Android mínimo API 26.
Hay respaldo SQL y de fuentes fuera del directorio público, con checksum y manifiesto
de identidades. La verificación del archivo no equivale a una restauración ensayada.

Publicado en el VPS el 2026-10-04. Antes/después de la ventana de mantenimiento:
11 usuarios, 8 tiendas, 267 productos, 0 pedidos y 4 facturas; mismos identificadores,
ninguna eliminación. Antes del mantenimiento se observaron altas legítimas mientras
la tienda estaba en uso (258 → 259 → 267 productos); se preservaron en el respaldo final.
Home, descarga y catálogo de decants: HTTP 200. Manifest público: code 9 / 1.0.8,
hash `0d5222a4181e9a5de4d62742c9a5a8a419d39ae563703011b4f52ea4e0090075`.
APK HTTP 200, longitud 16,131,920 bytes; firma idéntica a 1.0.7. Versiones anteriores
del APK y archivos ajenos del VPS se conservaron.
Fuentes Android actualizadas: `C:\Users\waila\AndroidStudioProjects\BSPOS-MiCatalogo`.
Copia de fuentes sin credenciales ni artefactos de build:
`C:\Users\waila\AndroidStudioProjects\BSPOS-MiCatalogo-Releases\source-1.0.8-20261004.tar.gz`.

## Segunda etapa en desarrollo local — no publicada

Se añadió la API `mobile-operations`, con identificador inmutable por operación,
deduplicación transaccional, permisos por tienda y archivo reversible de productos.
Las reposiciones conservan sus costos; los conteos rechazan saldos remotos cambiados.
Las devoluciones de contado verifican la venta original, cantidades y monto cobrado,
restauran el costo histórico vendido y descuentan el reembolso del reporte de ganancia.
Incluyen el impuesto general de la factura sin convertirlo en ingreso.
La migración `2026_10_14_000000` es aditiva y todavía no se aplicó al VPS.

Android en desarrollo local usa Room 17 y una cola persistente de operaciones.
Las ediciones y archivos en espera sobreviven a un catálogo remoto desactualizado;
productos creados localmente conservan su identidad al descargarse desde el servidor.
Ventas y operaciones se envían en orden por tienda. Un conflicto bloquea sus sucesoras;
una respuesta sin el identificador de venta no confirma su envío.
Validación Android: 43 pruebas unitarias y 76 instrumentadas, sin fallos.
La API móvil tiene 6 pruebas con 52 aserciones; la suite web completa pasa con
233 pruebas y 1,061 aserciones usando SQLite en memoria.

Ya están conectados localmente el selector de tienda para crear productos, entradas
con costo decimal y conteos físicos. La lista de tiendas tiene caché por cuenta para
trabajar sin conexión; la autorización definitiva permanece en el servidor.
Producto nuevo y saldo inicial se guardan juntos. Movimiento, actualización del costo
estimado y operación pendiente comparten transacción: un saldo desactualizado revierte
todo. Una entrada de botella conserva sus mililitros parciales y actualiza sus decants
con una sola operación remota. Los conteos verifican también el saldo en mililitros,
porque vender decants puede no cambiar el número de botellas enteras.
Los costos y precios aceptan centavos sin truncarlos. La interfaz de entradas/conteos
dirige al inventario fuente en lugar de modificar un decant de forma independiente.
Validación actual de esta etapa: 234 pruebas web / 1,067 aserciones; 44 unitarias
y 78 instrumentadas Android, sin fallos. Pruebas añadidas: entrada + cola atómicas,
reversión por saldo obsoleto, costo por entrada, botella parcial con decants y conflicto
de conteo en mililitros aunque las botellas enteras no cambien. Estas comprobaciones
no sustituyen las pruebas visuales ni de extremo a extremo previas a publicar.

### Devoluciones y controles adicionales verificados localmente

Las devoluciones de contado ya se conectaron en Android. Caja, documento, stock y
operación remota comparten transacción. Las cantidades no pueden superar lo vendido;
el reembolso exacto incluye descuentos e impuestos y conserva los centavos entre
devoluciones parciales. Restituir un decant recompone los mililitros de su botella
y sus presentaciones, sin sustituir el costo actual por el costo histórico devuelto.
Un fallo por falta de caja abierta no deja documento, movimiento ni operación pendiente.

Room 17 añade importes exactos de devolución e instantáneas de unidad, volumen y
botella de origen a las líneas de venta; no reconstruye esos datos en ventas antiguas.
La migración web `2026_10_15_000000` captura también la botella de origen en las
nuevas facturas. Devoluciones antiguas de decants sin ese dato requieren conciliación
para restituir mercancía; el reembolso sin restitución no inventa un origen.

Las nuevas ventas Android envían la presentación capturada. La API devuelve
`presentation_conflict` si cambió, antes de consumir inventario. Los clientes anteriores
siguen funcionando sin estos campos. Un reintento de una venta ya aceptada conserva
su respuesta original aunque el producto haya cambiado posteriormente.

Ajustes muestra ventas, operaciones y abonos pendientes, su estado, motivo e
identificador. Reintentar mantiene la instantánea y el UUID: no equivale a resolver
un conflicto de precio o stock. Las pantallas de catálogo e inventario solo permiten
editar productos remotos de propietarios/administradores; el servidor vuelve a
comprobar permisos. Una venta con reintento futuro mantiene el trabajo de fondo
pendiente; un bloqueo no genera reintentos automáticos interminables.

Validación actual: **238 pruebas web / 1,113 aserciones; 50 unitarias y 83 instrumentadas
Android**, sin fallos. Android se probó solamente en `emulator-5554`. No se ejecutaron
pruebas ni migraciones nuevas en producción durante esta tanda.

Falta completar la resolución explícita de conflictos (la lista y el reintento no bastan),
fotos de producto, descarga automática del precio Pro después de confirmar una entrada
y pruebas visuales/de extremo a extremo. También debe probarse y corregirse el orden
causal cuando dos eventos tienen la misma marca temporal; ordenar solo por fecha no
garantiza ese caso. Los costos locales siguen siendo estimaciones hasta la asignación
FIFO del servidor. No presentar estos puntos como terminados.

No publicar estos cambios bajo la versión 1.0.8 ni anunciar la sincronización general
como terminada. La próxima publicación necesita nueva versión, firma verificada,
backup de producción e identidades comparadas antes/después.
