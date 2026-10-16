# Riesgos y bloqueos

| Riesgo | Nivel | Evidencia / causa | Acción necesaria |
|---|---|---|---|
| E2E financiero/inventario no certificado en recorrido integral | P0 | No hay API/base QA con identidad actual comprobable; no se usaron fixtures comerciales compartidos | Preparar instancia QA aislada verificada y ejecutar A, B, E, G, H con snapshots de ledger/FIFO |
| NAV pendiente de navegación actual completa | P1 | El reporte vigente anterior conserva 59/70 casos como BLOCKED; JVM/feature tests no sustituyen esos recorridos | Ejecutar el manifest NAV en UI de ambas plataformas y registrar screenshots/logs |
| Campos Web/Android no equivalentes | P1 | `brand`/`barcode` figuran web-only y `model` unsupported en el contrato previo | Definir e implementar contrato end-to-end antes de declarar paridad completa |
| Flujos de autenticación Web en navegador | P2 | Login puede cargar Turnstile externo; este cierre aisló Turnstile únicamente para tests | En navegador QA usar autenticación fixture/local y recoger trace/capturas sin proveedor real |
| Promesas históricas en documentos | P1 | `adaptive-catalog/FINAL_REPORT.md` declara 48/8/70 PASS en conflicto con QA_REPORT y `05_QA_EXECUTION.md` | Se marcan como afirmaciones históricas supersedidas; usar solo `docs/closure-real/` como verdad actual |

**Decisión provisional: NO-GO para aprobación de producción** mientras existan journeys críticos y navegación bloqueados. No se ha realizado ningún despliegue.
