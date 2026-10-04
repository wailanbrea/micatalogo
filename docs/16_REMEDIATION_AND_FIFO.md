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

La app sincroniza catálogo/clientes, ventas y nuevos abonos. Sigue pendiente la
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

Android en desarrollo local usa Room 16 y una cola persistente de operaciones.
Las ediciones y archivos en espera sobreviven a un catálogo remoto desactualizado;
productos creados localmente conservan su identidad al descargarse desde el servidor.
Ventas y operaciones se envían en orden por tienda. Un conflicto bloquea sus sucesoras;
una respuesta sin el identificador de venta no confirma su envío.
Validación Android: 43 pruebas unitarias y 76 instrumentadas, sin fallos.
La API móvil tiene 6 pruebas con 52 aserciones; la suite web completa pasa con
233 pruebas y 1,061 aserciones usando SQLite en memoria.

Falta conectar por completo la selección de tienda al crear productos, la entrada de
mercancía, conteos y devoluciones en las pantallas Android; añadir conciliación visible
de conflictos, cobertura de stock compartido y pruebas de extremo a extremo.
No publicar estos cambios bajo la versión 1.0.8 ni anunciar la sincronización general
como terminada. La próxima publicación necesita nueva versión, firma verificada,
backup de producción e identidades comparadas antes/después.
