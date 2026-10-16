# Índice de evidencia del cierre

| Evidencia | Ubicación | Alcance |
|---|---|---|
| Pest XML/HTML | `C:\xampp\php\www\MiCatalogo\storage\logs\` y salida de esta sesión | Suite Laravel completa, SQLite en memoria; resumen también en `00_BASELINE.md` |
| Android JVM JUnit XML | `C:\Users\waila\AndroidStudioProjects\micatalogowebApp\app\build\test-results\testDebugUnitTest\` | 152 tests previos al último cambio Compose |
| Android instrumentado | `C:\Users\waila\AndroidStudioProjects\micatalogowebApp\app\build\outputs\androidTest-results\connected\debug\` | AVD; corrida en curso y anterior al cambio más reciente |
| Cambios fuente | Diffs en los dos worktrees | Guard de integridad Web/API y estado vacío de categoría Android |

Las rutas de outputs Gradle son artefactos locales regenerables; no representan publicación ni datos de producción.
