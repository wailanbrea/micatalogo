# Release readiness — formulario adaptativo

## Estado

**Release Android publicada bajo orden del usuario:** `1.0.116` (`versionCode 117`). Solo se desplegaron el APK y su manifiesto de actualización; el resto del backend/API no se desplegó. Los gates funcionales que siguen abiertos no se consideran resueltos por esta publicación.

## Gates antes de release

- [x] Test backend del contrato y de perfiles/tenant ejecutado.
- [x] Tests Android unitarios e instrumentados focalizados ejecutados.
- [x] QA navegador local con DB sintética aislada.
- [x] Sin migraciones de esquema.
- [ ] Completar pruebas integradas Web/API ↔ Android de venta/stock/Decants/FIFO/devolución y offline.
- [ ] Completar matriz de roles (owner/manager/seller sin costo/seller solo servicio) en Web y Android.
- [ ] Decidir e implementar paridad móvil para marca/barcode si se requiere como campo editable común.
- [ ] Verificar edición de productos históricos/custom unit y bloquear cambio semántico con inventario/historial si la política vigente lo permite.
- [x] Confirmar commits/push de trabajo en ambos repositorios antes de publicar.

## Rollback/compatibilidad

La API de producto solo agrega claves bajo `presentation.product_form`; cliente anterior ignora claves JSON opcionales. No hay migración a revertir. Para rollback de código, revertir commits focalizados en ramas y no tocar filas comerciales.

## Evidencia de publicación 1.0.116

- Paquete `com.bsolutions.micatalogo`, `minSdk=26`, `debuggable=false`.
- Certificado SHA-256 `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f`, igual al APK 1.0.115 verificado.
- APK SHA-256 `5352e62d08fd65af4a5a9a731f34516ef685a03d361dc6b7e4a221b197e70f30`, tamaño `41,277,772` bytes.
- URL: `https://micatalogo.bsolutions.dev/downloads/bspos-1.0.116-release.apk`; descarga HTTP 200 y bytes/hash verificados.
- Manifiesto respaldado en un archivo no público antes del cambio y validado con `php -l`; caché Laravel regenerada.
- Conteos e IDs de usuarios/tiendas leídos antes y después permanecieron iguales: 11 usuarios y 9 tiendas. No hubo escrituras de base de datos.
- Publicar el artefacto no equivale a instalarlo; no se instaló en teléfono físico ni emulador en este paso.
