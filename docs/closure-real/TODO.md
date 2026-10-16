# TODO — cierre real

- [x] Verificar ramas, HEAD, estado inicial y aislamiento.
- [x] Ejecutar Pest completo en SQLite en memoria y corregir dos fallos del arnés/expectativa.
- [x] Proteger en API/Web el cambio de unidad/presentación cuando el producto ya tiene historial comercial y FIFO.
- [x] Añadir regresiones backend para mutación Web y mobile-operations; confirmar estado/lotes intactos tras rechazo.
- [x] Detectar el acceso Android inseguro `categories.first()` ante snapshot vacío; añadir estado vacío seguro.
- [x] Mantener visible la categoría de servicio sin controles de inventario físico.
- [x] Repetir Android JVM/build tras las regresiones Compose nuevas: 152 tests, 0 failures; debug y androidTest compilan.
- [x] Repetir `ProductFormTest` actual: 5/5 PASS; Login 1/1 y Decants 13/13 PASS.
- [x] Revisar XML de corrida de 110 tests: 2 expectativas obsoletas y 8 casos live sin entorno; documentados, no marcados PASS.
- [x] Ejecutar lint y `git diff --check`, revisar guard y rutas.
- [x] Generar matriz individual de 48 QA + 8 E2E + 70 NAV con estados honestos.
- [x] Documentar paridad, limitaciones, evidencias y decisión de salida.
- [x] `git diff --check`, lint Android, PHP syntax lint y route:list local QA.
- [ ] Commit y push a la rama de trabajo en ambos repositorios; verificar SHA remoto.

## No cubierto / sin marcar como terminado

- Recorridos E2E A–H Web↔Android contra una API QA y fixture compartidos.
- Ciertos recorridos NAV que dependen de roles reales, navegación visual, TalkBack, tamaños y fixtures multitienda.
- Publicación, instalación release, pruebas comerciales reales o producción: explícitamente fuera de alcance.
