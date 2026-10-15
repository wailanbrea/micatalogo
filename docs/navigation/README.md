# Navegación unificada — seguimiento por gates

Protocolo autorizado: `MICATALOGO_PROMPT_EJECUCION_AUDITORIA_IMPLEMENTACION_NAVEGACION_WEB_ANDROID.md`, edición 14/10/2026.

- [x] F0: baseline, inventario de UI/rutas/acciones/guards y evidencia por rol/perfil; matrices y DELEGATED_GUARDS_REVIEW.md, hallazgos F4 abiertos.
- [x] F1: config/seller-navigation.php, SellerNavigationService, campo navigation aditivo en shops; SellerNavigationManifestTest + suites de visibilidad/presentación PASS. Ver NAVIGATION_CONTRACT.md.
- [x] F2: sidebar/menú móvil compartidos, tabs/contexto Web, rutas conservadas; ver F1_F2_RESULTS.md. Seguridad por operación pendiente de F4.
- [ ] F3: shell Android adaptativo, aliases, hijos autorizados y caché implementados; certificación integral pendiente. Ver F3_PROGRESS.md.
- [ ] F4: seguridad por operación/tab, cero P0.
- [ ] F5: NAV-001..NAV-070 y E2E A..G con evidencias y conservación de datos.
- [ ] F6: documentación, commits/push de trabajo, candidato y runbook, sin despliegue implícito.

## Baseline protegido

- Backend `master`, HEAD `48cbd4ae7b73656369f2274db754ae63036a800f`.
- Android `main`, HEAD `ad9ecd7610d3089e55b2bcb8067ed4776a9bd5c1`.
- Cambios locales backend previos: `CatalogController.php`, `ApiCatalogTest.php`, artefactos QA.
- Cambios locales Android previos: `MiCatalogoDtos.kt`, `PosScreen.kt`, nuevos `PosProductPresentation.kt` y test correspondiente.
- No forman parte de una aprobación de F0 ni se sobrescriben al refactorizar navegación.
- El escáner estático exporta referencias y evidencia de guard; las filas `REVIEW` requieren inspección/validación concreta. Su mera extracción no aprueba el gate.

## Verificación actual — continuación F3/F4

- Backend completo: **663 PASS / 7.597 aserciones**, SQLite en memoria con identidad verificada. Incluye separación de crédito/cobros en POS, método de pago crédito y replay de pedido a crédito.
- Android: **147 unitarias PASS**, 0 fallos/errores/skips; builds debug y androidTest PASS. Regresión instrumentada focalizada **49/49 PASS** en `emulator-5554`: Compras, Cobros, POS, pagos mixtos, Fotos, revocación real/Atrás, outboxes, tenants y pestañas financieras.
- `routes-after-f4-credit.json` conserva las **237 rutas** originales por nombre, URI, métodos y acción; `OPERATION_ACCESS_MATRIX_F4_CREDIT.md` registra la exportación actual. Las matrices y snapshots anteriores se conservan como baseline histórico.
- `verification-baseline.json` agrega XML actuales, hashes de evidencia y compatibilidad F4; no ejecuta pruebas ni convierte los snapshots Room históricos en una comparación nueva de filas.
- Detalle, matriz de los 70 casos y límites: [QA_REPORT.md](QA_REPORT.md), [F3_PROGRESS.md](F3_PROGRESS.md), [F4_RESULTS.md](F4_RESULTS.md), [NAVIGATION_CONTRACT.md](NAVIGATION_CONTRACT.md).

## Verificación histórica del 14/10/2026

Estado actual tras F1–F2: backend completo 621 PASS / 7.328 aserciones; cambios finales de labels verificados por 84 pruebas afectadas PASS. Baseline F0 descrito debajo se conserva como evidencia histórica. Compatibilidad APK previa a F3 verificada en emulador con diez roles. Ver F1_F2_RESULTS.md para límites y regresiones.

El primer corte F3 tenía contrato/caché/decoder y 121 unitarias PASS; el shell y los permisos por tab se implementaron después, según F3_PROGRESS.md.

- Regresión ACL-019: 5 pruebas PASS, 151 aserciones. Registro exacto de 11 mutaciones administrativas, incluido admin.menus.update.
- Backend completo: 590 PASS, 6.458 aserciones, SQLite en memoria; identidad verificada con `.tools/test-connection-identity.php`.
- Android actual: 115 unitarias PASS, 0 fallos/skips; build debug/androidTest PASS. Perfil all declara 176 métodos instrumentados, no ejecutados en conjunto todavía.
- Baseline visual Android: 10 roles × 2 formatos emulados; captura instrumentada PASS sin assumptions/skips. Evidencia PNG + árbol semántico en `evidence/navigation-qa/`.
- Web: diez roles capturados también con menú móvil abierto. Hallada divergencia de enlaces respecto a desktop; ver `MATRIZ_NAV_ACTUAL.md`.
- `verification-baseline.json` contiene los conteos XML y SHA-256 de evidencia; regenerar con `python qa/navigation_baseline_report.py --android <repo>` después de añadir evidencia.
- `ROLE_ACCESS_MATRIX.md` conserva permisos efectivos, stock/saldos/caja del fixture y baseline Room27. Conteos antes/después iguales en ambos formatos; tres outboxes vacías. Revisión delegada completada en DELEGATED_GUARDS_REVIEW.md; gate de inventario F0 aprobado, con seguridad F4 pendiente.

### Incidencia del arnés y regresión

Al añadir snapshots Room, un EntryPoint definido sólo en androidTest produjo ClassCastException contra el componente Hilt de la app normal. Reproducido con resultado 0 PASS / 1 FAIL. Corregido usando apertura SQLite OPEN_READONLY del archivo QA existente, sin alterar inyección ni código de producción. Recompilado y repetido el mismo método en teléfono y tablet: ambos 1 PASS / 0 FAIL / 0 SKIP / 0 ASSUMPTION; snapshots presentes y comparados.
