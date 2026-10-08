# MiCatalogo — plan maestro QA revisado

Fuente de alcance: `C:\Users\waila\Downloads\MICATALOGO_MASTER_QA_OPERACIONAL_WEB_ANDROID.md`.
Este documento es la ejecución controlada del plan contra los HEAD reales, no una copia histórica.

## Addendum posterior a la publicación autorizada — 2026-10-08

La verificación vigente debe usar estos estados, que sustituyen cualquier referencia
histórica de este documento a una release 1.0.76 no publicada:

- Web/API: `master` en `efa56cf`, publicado en `C:\xampp\htdocs\micatalogo`.
- Android: `main` en `5fdb57e`, publicado en GitHub.
- Release pública: MiCatalogo `1.0.77`, `versionCode 78`, APK `bspos-1.0.77-release.apk`.
- El manifiesto y la descarga pública respondieron HTTP 200; tamaño y SHA-256
  coincidieron con el artefacto firmado y `debuggable=false`.
- Producción conserva sus conteos e identificadores registrados antes del despliegue;
  `migrate:status` mostró 0 migraciones pendientes. No se ejecutaron seeders ni
  operaciones destructivas.
- El gate QA sigue siendo `NOT_READY` por los casos parciales, bloqueos de hardware/
  servicios externos y decisiones de producto enumerados en la trazabilidad; publicar
  la release no convierte esos casos en PASS.

## Estado de arranque

- Backend/Web/API: `master` está publicado en `efa56cf` y Android `main` en `5fdb57e`; mantener la verificación de Git antes de futuras publicaciones.
- Android: la versión pública es `1.0.77`, versionCode `78`; contiene las correcciones del updater, PDF, logout y Finanzas verificadas por el helper de release.
- VPS: el checkout `C:\xampp\htdocs\micatalogo` fue actualizado con `git pull --ff-only`; cachés y assets se reconstruyeron, sin migraciones pendientes ni cambios de datos.
- Baseline actual: **513 pruebas backend, 5.207 assertions** en SQLite y MariaDB QA; 95 pruebas unitarias Android. La variante normal y `offlinecheck` tienen 119 instrumentadas (`116 PASS`, 3 assumptions) en el emulador QA. Los fixtures live aislados del importador pasan 1/1 y los del POS 2/2. El corte incluye las regresiones F-046 a F-059 y la publicación controlada documentada en el addendum.
- APK 1.0.77 firmada y publicada; SHA-256 `654531f8d6e238217e72f17a8a72ec3b15652109cbf1d4b3a2d5cbad01527b8f`. La release anterior permanece disponible.
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
