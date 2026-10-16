# Release readiness — formulario adaptativo

## Estado

**No publicar automáticamente.** El backend/API y Android tienen cambios locales verificados parcialmente y commits/push de ramas de trabajo son el límite de esta tarea. No se hizo deploy VPS, publicación de APK, cambio de versión ni escritura en producción.

## Gates antes de release

- [x] Test backend del contrato y de perfiles/tenant ejecutado.
- [x] Tests Android unitarios e instrumentados focalizados ejecutados.
- [x] QA navegador local con DB sintética aislada.
- [x] Sin migraciones de esquema.
- [ ] Completar pruebas integradas Web/API ↔ Android de venta/stock/Decants/FIFO/devolución y offline.
- [ ] Completar matriz de roles (owner/manager/seller sin costo/seller solo servicio) en Web y Android.
- [ ] Decidir e implementar paridad móvil para marca/barcode si se requiere como campo editable común.
- [ ] Verificar edición de productos históricos/custom unit y bloquear cambio semántico con inventario/historial si la política vigente lo permite.
- [ ] Revisar branch CI/checks y confirmar commits/push remotos antes de preparar release.

## Rollback/compatibilidad

La API solo agrega claves bajo `presentation.product_form`; cliente anterior ignora claves JSON opcionales. No hay migración a revertir. Para rollback de código, revertir commits focalizados en ramas y no tocar filas comerciales. Ningún APK release fue producido o publicado.
