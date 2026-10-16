# TODO — Formulario adaptativo Web/Android

Estados: `[x]` ejecutado con evidencia, `[~]` parcial, `[ ]` pendiente/BLOCKED. QA detallado: `05_QA_EXECUTION.md`; límites y defectos: `06_FINDINGS.md`.

## F0 — Inventario y baseline
- [x] Inspeccionar ambos repositorios, branch/HEAD/remotos y conservar árboles limpios iniciales.
- [x] Extraer e inventariar los 23 perfiles/configuración en `01_PROFILE_MATRIX.md`.
- [x] Mapear el flujo de alta Web/API/Android, UUID de unidad, Room y outbox en `00_BASELINE.md`.
- [x] No utilizar producción; fixture y SQLite local aislados.
- [~] Baseline cuantitativo remoto no tomado por prohibición de producción; sin baseline comercial modificado.

## F1 — Perfil/API
- [x] Añadir regresión con tiendas appliance/perfume del mismo owner y tienda de otro tenant.
- [x] Confirmar que tipo, versión, overrides y aislamiento están en respuesta actual.
- [x] No modificar `ShopController::index()`: defecto inicial no reproducido.

## F2 — Contrato aditivo
- [x] Añadir `presentation.product_form`, versión 1, defaults, modos disponibles, etiquetas, mapping/UUIDs y estados de campos.
- [x] Mantener contrato existente y backend como autoridad ACL; test HTTP/Feature.
- [x] Documentar compatibilidad y ejemplos en `02_API_CONTRACT.md`.
- [x] Evitar promesas falsas: costo permission-gated, marca/barcode web-only, SKU generado, modelo unsupported.

## F3 — Unidad automática
- [x] Modo de venta resuelve unidad canónica de inventario; selector técnico pasa a opción avanzada.
- [x] Web usa UUID generado por el servicio de identidad por tienda y conserva `old()`/unidad histórica.
- [x] Android persiste unidad canónica estable antes de producto/outbox y preserva unidad en edición.
- [x] No hay migración/backfill ni modificación de stock/FIFO; reglas documentadas.
- [~] Concurrencia y creación offline de unidades probadas en tests unitarios focalizados, no E2E de dos dispositivos/API.
- [ ] Asegurar bloqueo de cambio semántico de unidad al editar artículo con stock/historial; revisar en flujo aparte antes de release.

## F4 — Android
- [x] Consumir contrato con DTO/modelo y fallback seguro.
- [x] Formularios appliance y perfume adaptan título, modalidad y unidad sin exponer bottle/ml a appliance.
- [x] Preservar rutas de combo/servicio/decants y permisos existentes.
- [x] Tests unitarios/instrumentados de formulario ejecutados en emulador.
- [~] Paridad de marca/barcode incompleta y declarada como `web_only`; talla/color/modelo no anunciados.
- [ ] Verificar tamaños 320/360/411 dp, tableta, fuente 130–200%, teclado, rotación y borrador con evidencia archivada.

## F5 — Web
- [x] Formulario adaptativo por perfil y título comercial; modo/unidad automática y opción avanzada.
- [x] Preservar categoría y selección, validación y permisos existentes; test de render de appliance/perfume.
- [x] Prueba de navegador local con alta real en SQLite temporal aislada y lectura de vuelta de sale_unit, stock, costo, UUID.
- [~] Un solo flujo browser probado (appliance); edición histórica, errores 422, móvil y roles requieren matriz ampliada.

## F6 — Invariantes
- [x] Regresión de perfil por tienda y aislamiento tenant.
- [x] Contrato restringe modos appliance/perfume/service; costo no se anuncia como campo universal.
- [~] FIFO, ledger, devoluciones, créditos, servicio con rol limitado, combos y Decants no recibieron E2E integrado en esta fase.
- [ ] Agregar tests de manipulación API/403/422 y edición de unidad histórica con stock.

## F7 — QA
- [x] Ejecutar Pest focalizado: 8 tests/2,581 assertions.
- [x] Ejecutar Android unit: `:app:testDebugUnitTest --rerun-tasks` PASS.
- [x] Ejecutar 3 instrumentadas de `ProductFormTest` en Pixel 10 Pro XL AVD: 0 fallos.
- [x] Ejecutar navegador local con fixture Appliance y confirmar persistencia aislada.
- [~] `05_QA_EXECUTION.md` incluye estado real PF/E2E; E2E-A..F no reportados como PASS.

## F8 — Revisión y entrega
- [x] Crear entregables `00` a `07`, con hallazgos, paridad y gates.
- [x] Repetir suite Web tras último ajuste del contrato, unit/instrumented Android y `git diff --check`.
- [ ] Revisar dos diffs completos y estado final limpio luego de commits.
- [ ] Crear commits descriptivos separados y push solo a las ramas de trabajo `work/navigation-closure-20261015` de cada repo.
- [x] No desplegar, publicar APK, migrar producción ni modificar datos comerciales.

## Estado honesto

Implementación focalizada lista, con QA parcial real. No se declara cumplimiento completo de los seis E2E comerciales. No publicar release hasta cerrar los gates marcados `[ ]` y solicitar autorización explícita.
