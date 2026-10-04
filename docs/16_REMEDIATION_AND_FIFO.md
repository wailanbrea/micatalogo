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
- [ ] Pruebas web/Android, release firmado y verificación de publicación.
- [ ] Producción: respaldo verificado y comparación de identidades antes/después.

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

Validación: 227 pruebas web (SQLite en memoria); 73 pruebas instrumentadas Android
en emulador, sin fallos; build de producción y firma del APK 1.0.8/code 9 verificados.
Certificado idéntico al APK 1.0.7. Tamaño 16,131,920 bytes; Android mínimo API 26.
Hay respaldo SQL y de fuentes fuera del directorio público, con checksum y manifiesto
de identidades. La verificación del archivo no equivale a una restauración ensayada.
