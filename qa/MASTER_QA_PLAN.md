# MiCatalogo — plan maestro QA revisado

Fuente de alcance: `C:\Users\waila\Downloads\MICATALOGO_MASTER_QA_OPERACIONAL_WEB_ANDROID.md`.
Este documento es la ejecución controlada del plan contra los HEAD reales, no una copia histórica.

## Estado de arranque

- Backend/Web/API: `9e1691dd2d1b80110d297ec03056aad57d1ad262` (`master`).
- Android: `60ef266`, versionName `1.0.76`, versionCode `77`.
- VPS: `9e1691dd2d1b80110d297ec03056aad57d1ad262`, sin migraciones pendientes después del despliegue.
- Baseline actual: 494 pruebas backend, 5.069 assertions en SQLite y MariaDB QA; 94 pruebas unitarias Android. La variante principal tiene 113 instrumentadas en el emulador QA (`110 PASS`, 3 omitidas por fixtures live no suministrados); `com.bsolutions.micatalogo.offlinecheck` conserva su última ejecución completa de 112 (`109 PASS`, 3 omitidas). El corte incluye las regresiones F-046, F-047, F-048, F-049, F-050, F-051, F-052, F-053 y F-054: precisión de precio efectivo, normalización decimal del importador legacy, redacción financiera por rol y recuperación exacta de costos de decants.
- APK 1.0.76 firmada y publicada; SHA-256 `24846233f98bfd099976d0965142b8aabca71a378659b74b5a8b14aa27334f6b`. La release anterior permanece disponible.
- Alcance reconciliado: las listas de casos de la fuente contienen 196 identificadores únicos, no 140; la matriz individual y sus estados están en `qa/TRACEABILITY.md`.
- Automatización local añadida: `.github/workflows/qa.yml` ejecuta pruebas SQLite, build frontend, Pint sobre PHP modificados e inventario de rutas; el workflow Android ejecuta unitarias, assembleDebug, lint y compilación de fixtures instrumentados.

## Reglas de ejecución

1. Inventariar primero rutas, pantallas, contratos, permisos, planes y capacidades reales.
2. Toda escritura usa fixtures y servicios QA aislados. Producción solo admite smoke de lectura después de una publicación explícitamente autorizada.
3. Cada defecto debe reproducirse, corregirse en todas las capas afectadas, tener regresión y volver a ejecutarse.
4. Un caso no ejecutado no es PASS. Hardware o servicios externos no disponibles quedan `BLOCKED_ENV`.
5. P0/P1 abiertos impiden `READY_FOR_CONTROLLED_PILOT` y `READY_FOR_PRODUCTION`.

## Orden actual

`inventario → baseline → contratos/RBAC → finanzas/inventario → Web/API → Android Room/offline → UI/hardware → E2E → carga/seguridad → release gate`.

El estado y la evidencia se mantienen en los documentos de esta carpeta.
