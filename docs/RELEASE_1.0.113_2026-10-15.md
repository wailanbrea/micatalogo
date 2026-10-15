# Android 1.0.113 — publicación 2026-10-15

- Android commit: `4fe66cf`; versionCode 114, paquete `com.bsolutions.micatalogo`.
- Registrar cobro: distribución móvil, campos y confirmación completos, métodos de pago en dos columnas.
- Decants: Sin volumen entre Reporte y Más opciones; ventana con búsqueda sin límite de ocho productos y configuración de ml con permisos existentes.
- Pruebas: 147 JVM sin fallos/omisiones; 7 instrumentadas de cobros y 9 de Decants aprobadas en QA aislado durante la implementación. Capturas revisadas; detalles en los documentos QA del repositorio Android.
- Release firmada, no debuggable; certificado conservado y comparado con 1.0.112. No se instala ni desinstala un teléfono durante esta publicación.
- APK: `https://micatalogo.bsolutions.dev/downloads/bspos-1.0.113-release.apk`, 41.245.004 bytes.
- SHA256: `450b2b24207d32fb676fc2ff70d7914d6e54d2403e5d3c0ea0ee6b0f61ed1c7f`.
- Sin migraciones ni cambios funcionales de backend. Se conserva la versión mínima 23 y la APK anterior.
- Respaldo SQL privado verificado antes del despliegue. Comparación de conteos e identificadores antes/después y verificación de descarga pública forman parte del cierre operativo.
