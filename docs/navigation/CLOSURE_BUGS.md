# Hallazgos del cierre

| ID | Estado inicial | Hallazgo | Evidencia / comprobación necesaria |
|---|---|---|---|
| CLOSURE-001 | REPRODUCIDO | Categorías y unidades Web sólo administra categorías | UI real HTTP200 owner: cero tabs, sin Unidades, sólo create/delete. closure-f0-web-category-content PNG/JSON. |
| CLOSURE-002 | REPRODUCIDO | Services-only no entra a taxonomía ni sincroniza category/unit | closure_f0_repro.php PASS del diagnóstico: seis403 por GET/CRUD/envelopes, hashes de negocio idénticos. Menú Web/API/Android sin categories para services-only. |
| CLOSURE-003 | Riesgo observado; test negativo pendiente | Habilitar OR sin scope afectaría categoría usada por físico | No hay scope en SQL/Room/payload; se decide en F1 y se prueba escalada negativa. |
| CLOSURE-004 | Confirmado en fuente; reproducción pendiente | Cambios Web de categoría no actualizan la proyección ShopSyncRecord ni su conflicto/estado | Web escribe ShopCategory directamente; Android lee además metadata. Validar rename/deactivate/reactivate conservando IDs. |
| CLOSURE-005 | Confirmado en fuente; reproducción pendiente | CatalogSettings Android no representa ACK/errores ni límites de modificación | Acciones directas desde viewModelScope sin estado de operación en pantalla. |
| CLOSURE-006 | Riesgo de sync confirmado en fuente; regresión específica pendiente | applySnapshot trata ausencia como retiro aun cuando el servidor filtra productos por permiso | MiCatalogoCatalogRepositoryImpl:199–204; necesita probar snapshot services-only sobre caché física sin archivar ítems ajenos al alcance. Los cambios de metadata observados en el baseline no se atribuyen automáticamente a esta causa. |

Registrar RED/GREEN, actor, base, IDs y commit al reproducir/corregir. No confundir un riesgo estático o un test parecido con un caso ejecutado.
