# Cierre de navegación — ejecución F0–F7

Documento fuente: `MICATALOGO_PROMPT_CIERRE_AUDITORIA_NAVEGACION_WEB_ANDROID.md` (15/10/2026).

- [x] F0: baseline nuevo de código, datos SQL/Room, autorización y menús por actor/formato. Ver CLOSURE_BASELINE.md, closure-f0-reproduction.json, XML backend666 PASS, JVM147 PASS y capturas Web/Android actuales. Sin cambios de aplicación durante el gate.
- [x] F1: TAXONOMY_DECISION.md, política derivada y fixtures negativos: CatalogTaxonomyPolicyTest5 PASS/25 aserciones; sin privilegio físico para Services ni escrituras de contador/ajeno. Integración/persistencia HTTP pendiente F2.
- [~] F2: implementación Web/API de categorías/unidades y autorización por alcance avanzada en el árbol de trabajo; suites específicas 53 PASS/436 aserciones y suite backend completa 679 PASS/7.710 aserciones. Revisar contratos, lint/rutas/compilación y completar auditoría de concurrencia antes de cerrar.
- [~] F3: corregida en Android la transición Sin volumen → Configurar ml y la tarjeta de producto; 9/9 pruebas instrumentadas Decants PASS. La taxonomía Android (permisos por scope, metadata/revisión en Room, ACK/errores y paridad bidireccional) sigue pendiente.
- [ ] F4: evaluar Precios sin mezclar `price_health`/`pricing`; implementar o aplazar con evidencia.
- [ ] F5: revisión de todas las áreas, seguridad/requests, aliases y accesibilidad.
- [ ] F6: cerrar individualmente NAV-001..070 y A..G con evidencia nueva; conservar la procedencia de resultados históricos.
- [ ] F7: suites/builds/checks finales, comparación SQL/Room, CLOSURE_REPORT.md, rollout/rollback documentados y commits/push verificados.

Los checks se completan después de verificar cada gate, no por haber creado archivos. No se reimplementa la navegación ya existente. F2/F3 permanecen parciales; F4–F7 siguen abiertos. Producción y publicación están fuera de esta autorización.
