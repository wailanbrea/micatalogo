# Cierre de navegación — ejecución F0–F7

Documento fuente: `MICATALOGO_PROMPT_CIERRE_AUDITORIA_NAVEGACION_WEB_ANDROID.md` (15/10/2026).

- [x] F0: baseline nuevo de código, datos SQL/Room, autorización y menús por actor/formato. Ver CLOSURE_BASELINE.md, closure-f0-reproduction.json, XML backend666 PASS, JVM147 PASS y capturas Web/Android actuales. Sin cambios de aplicación durante el gate.
- [ ] F1: TAXONOMY_DECISION.md y fixtures negativos que validen scopes e IDs sin facultades físicas indirectas.
- [ ] F2: categorías/unidades reales Web/API, fuente compartida, ACL, conflictos y suites backend.
- [ ] F3: scopes y permisos Android, outbox/ACK/errores, migración explícita si hace falta, paridad y E2E de taxonomía.
- [ ] F4: evaluar Precios sin mezclar `price_health`/`pricing`; implementar o aplazar con evidencia.
- [ ] F5: revisión de todas las áreas, seguridad/requests, aliases y accesibilidad.
- [ ] F6: cerrar individualmente NAV-001..070 y A..G con evidencia nueva; conservar la procedencia de resultados históricos.
- [ ] F7: suites/builds/checks finales, comparación SQL/Room, CLOSURE_REPORT.md, rollout/rollback documentados y commits/push verificados.

Los checks se completan después de verificar cada gate, no por haber creado archivos. F0 limita las modificaciones a documentación/artefactos de auditoría; no se reimplementa la navegación existente. Producción y publicación están fuera de esta autorización.
