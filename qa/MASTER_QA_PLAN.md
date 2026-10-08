# MiCatalogo — plan maestro QA revisado

Fuente de alcance: `C:\Users\waila\Downloads\MICATALOGO_MASTER_QA_OPERACIONAL_WEB_ANDROID.md`.
Este documento es la ejecución controlada del plan contra los HEAD reales, no una copia histórica.

## Estado de arranque

- Backend/Web/API: consultar siempre `git log -1 --oneline` y `git status --short --branch` en `C:\xampp\php\www\MiCatalogo`; la documentación de QA también genera commits, por lo que no se fija un SHA histórico aquí. Los commits locales no están publicados.
- Android: consultar `git log -1 --oneline` en `C:\Users\waila\AndroidStudioProjects\micatalogowebApp`; en este corte el HEAD es `e50e5ff` (`main`). La versión pública sigue siendo `1.0.76`, versionCode `77`; las correcciones del updater para builds debug, persistencia del aviso pendiente, PDF, logout y los tests de Finanzas están en el checkout local y requieren una nueva release autorizada.
- VPS: no se ejecutó despliegue en este corte; no inferir su estado desde este documento. Cualquier consulta o migración remota exige leer `bsolutions-infra/SKILL.md`, verificar el destino y mantener la prohibición de escribir producción sin autorización.
- Baseline actual: **508 pruebas backend, 5.152 assertions** en SQLite y MariaDB QA; 95 pruebas unitarias Android. La variante normal tiene 119 instrumentadas (`116 PASS`, 3 assumptions) y `offlinecheck` 116 (`113 PASS`, 3 assumptions) en el emulador QA. Los fixtures live aislados del importador pasan 1/1 y los del POS 2/2 en ambas variantes debug. `CatalogSyncIntegrationTest` terminó 16/16, `FinancialContractTest` 5/5, `FinanceContentTest` 2/2 y `InvoicePdfGeneratorTest` 2/2. El corte incluye las regresiones F-046 a F-058, AUTH-007 y AUTH-008 parciales para recuperación/invitaciones y tokens de cuentas suspendidas o eliminadas, la validación monetaria de productos, las rutas financieras Web, el rechazo Web de autorizaciones y la cobertura positiva de categorías/medios de catálogo documentados en `qa/FINDINGS.md`.
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
