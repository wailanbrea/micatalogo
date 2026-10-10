# MiCatalogo: implementación local y auditoría de referencia

Fecha: 10/10/2026. Referencia: Puntto en `emulator-5554` (1344 × 2992, densidad 480 dpi). Destino: Android `micatalogowebApp`, variante debug `com.bsolutions.micatalogo.offlinecheck`, versión 1.0.90-offlinecheck / código 91. Firma debug comprobada contra la instalación previa; actualización compatible con `install -r`.

## Estado de entrega

**Implementado y probado localmente. No desplegado en VPS. Paridad visual global: PENDIENTE DE EVIDENCIA / NO APROBADA todavía.**

Se inició sesión en el emulador con la cuenta indicada por el usuario. Las credenciales no se incorporaron a archivos ni documentación. El teléfono físico no fue modificado en esta tarea. No se generó ni publicó una nueva release.

## Implementación de Decants

- Esquema aditivo: envases, lotes FIFO de envases, aperturas, lotes preparados, asignaciones de venta, asignaciones de envases, preferencias de presentación y ventas del resto.
- Preparación física: consume envases, transfiere ml/costo a un lote y conserva trazabilidad; no genera ingresos.
- Preparar desde sellada abre y prepara dentro de la misma transacción. Insuficiencia de envases o conflicto revierte la operación completa.
- Venta preparada descuenta el lote y su costo, sin consumir nuevamente ml de origen. Venta a pedido usa primero aperturas compatibles y abre una sellada cuando hace falta.
- Visibilidad de cada tamaño independiente de «A pedido». Ocultar conserva los lotes preparados y afecta tienda/Terminal. El indicador A pedido excluye tamaños ya listos.
- Precio compartido con catálogo, precio fijado con validación de snapshot y conservación de precios históricos de ventas/lotes.
- Merma de botella, reversión de apertura no utilizada, venta del resto con factura/pago y retorno a su apertura.
- Conciliación explícita de ml abiertos históricos, sin convertir capacidad derivada en frascos físicos.
- Valoración conserva costo en fuentes, aperturas, preparados y envases sin duplicarlo. Android consulta valoración remota; muestra explícitamente una estimación local cuando el endpoint aún no está activado.
- Android usa la cola de operaciones existente. Guardar en cola se muestra como pendiente; confirmación remota dispara actualización. Conflictos se conservan para revisión.
- Endpoints y comandos respetan tienda, capacidades, acceso al menú y permiso de gestión. Los costos se filtran por acceso financiero.

La compatibilidad histórica de fuentes por ml se mantiene; el flujo nuevo de aperturas físicas se centra en botellas. No se ha convertido automáticamente el historial de producción ni se ha conciliado el inventario real del usuario.

## Sistema visual aplicado

- Schibsted Grotesk y Geist Mono, confirmadas en las declaraciones públicas de Puntto. Fuentes originales convertidas de WOFF2 a TTF; licencias OFL incluidas.
- Tema global de tipografía y formas; azul y azul marino principales preservados. Superficies administrativas claras.
- Componentes compartidos de botones sólidos/delineados/acciones, etiquetas, dinero, búsqueda, importe y switch compacto.
- Hojas inferiores con asa, cierre, separadores, cuerpo desplazable y acciones. Conservación de propiedades de seguridad/cierre.
- Altura máxima calculada desde límites de ventana para evitar restar el teclado dos veces. Bloqueo del cierre animado durante procesamiento.
- Cliente de crédito en hoja hija; conserva tipo mixto, abono y cliente seleccionado en la confirmación padre.
- Terminal compacta con Detalle/Mayoreo, Cotizar, búsqueda/escáner separado, tipos y filas. Categorías permanecen en opciones.
- Pedidos: título, panel, buscador/segmentos, importes mono, metadatos y acciones.
- Inventario: acciones, búsqueda, filtros en hoja, panel agrupado, filas seleccionables y exportación de selección.
- Cotizaciones: lista inicial guardada, búsqueda, detalle capturado, fecha, artículos y descarga PDF autenticada; crear/convertir sigue disponible.
- Decants: fragancias expandibles, tamaños, conteos, visibilidad, stock/progreso, formularios y reportes.

## Verificaciones ejecutadas

| Verificación | Resultado |
|---|---|
| Backend seleccionado (Decants, lectura/PDF de cotizaciones, comercio, devoluciones, operaciones, FIFO, inventario) | **78/78, 558 assertions** |
| Conexión efectiva de pruebas | `testing`, `sqlite`, `:memory:`, host inexistente; guard adicional en `tests/TestCase.php` |
| Android unitarias | `testDebugUnitTest` satisfactorio |
| Instrumentadas de Pedidos/Decants/modales/Cotizaciones | **13/13** en el emulador aislado |
| Capturas de fixture de Decants | Repetición de Decants **3/3** satisfactoria |
| Compilación después de ajustes visuales posteriores | `assembleDebug` satisfactorio; instalación compatible en emulador |
| Sintaxis PHP de servicios/esquema | Satisfactoria |
| Whitespace de diffs | `git diff --check` satisfactorio |

Los ajustes visuales finales de espaciado, panel/segmentos y borde no implican otra certificación automática de identidad. Las pruebas funcionales no reemplazan la comparación de capturas.

## Matriz de revisión visual preliminar

| Pantalla | Evidencia y hallazgo | Veredicto de identidad estricta |
|---|---|---|
| Terminal | Referencia y captura de cuenta real. Familia de fuentes/importe y controles centrales adoptados. Se corrigieron selector de anchura parcial, separación del buscador y borde punteado del CTA. La rama Servicio conserva el flujo actual; falta cotejar su equivalencia completa con el formulario rápido de Puntto. | **NO APROBADO** para todas las ramas |
| Pedidos | Captura real: cuenta sin pendientes. Se corrigieron título, márgenes, búsqueda, segmentos y moneda. Flujo mixto/crear cliente pasa en fixture. Falta comparación visual equivalente de pedido pendiente y sus hojas completas; no se creó un pedido en producción para obtenerla. | **PENDIENTE DE EVIDENCIA** |
| Inventario | Captura real: panel, búsqueda, selección y filas. Se corrigieron espacios excesivos entre acciones y chips forzados a ocupar todo el ancho. Persisten diferencias de iconografía/composición fina y falta comparar todos los filtros/formularios con estado equivalente. | **NO APROBADO** todavía |
| Cotizaciones | Lista real y detalle en fixture. Fuente, importe, estado y secciones capturadas. Endpoint nuevo de detalle/PDF no está desplegado; la cuenta real no entrega aún todos los metadatos. La acción Nueva cotización y variantes de conversión necesitan cotejo de composición final. | **PENDIENTE DE EVIDENCIA** |
| Decants | Fixture de preparados y de preparación; cuenta real usa lectura compatible por ausencia del endpoint nuevo. Se corrigieron segmentos, iconos/tono del panel y cierre. Falta evidencia completa con tres tamaños, expansión, Frascos, apertura, merma, reversión y precio en el shell real; no certificar a partir del fixture de un tamaño. | **PENDIENTE DE EVIDENCIA** |

**Fuente incrustada comprobada** no significa que toda métrica tipográfica esté aprobada. **Componentes unificados** no significa identidad de cada composición. Se exige la matriz de aprobación del prompt para cerrar el objetivo.

## Evidencia privada

Ubicación: `C:/Users/waila/AppData/Local/Temp/opencode`. Contiene datos reales de sesión y no debe publicarse automáticamente:

- `micatalogo-final-terminal-20261010-133621-1a40ee7f.png`
- `micatalogo-final-pedidos-20261010-135035-4b1fc7b2.png`
- `micatalogo-final-inventario-20261010-135056-be728b6a.png`
- `micatalogo-final-cotizaciones-20261010-135536-1e30d01f.png`
- `micatalogo-final-decants-20261010-135923-f8101791.png`
- `style-decant-preparation.png` (fixture aislado)
- `style-decant-workspace.png` (fixture aislado, cuerpo sin shell completo)
- `style-quote-detail.png` (fixture aislado)
- `micatalogo-entrega-terminal-20261010-141021-e9061265.png` (última APK, cuenta autenticada después de la instalación final)

Estas capturas registran posiciones de la revisión; algunas preceden los últimos ajustes visuales. Repetirlas y alinearlas antes de aprobar definitivamente.

## Pendientes de cierre

1. Autorizar despliegue de backend. Verificar host/base/entorno efectivos y respaldo antes de aplicar la migración aditiva. No utilizar fresh/refresh, no recrear producción ni sembrar transacciones de prueba.
2. Activar endpoints de Decants, valoración y detalle/PDF de cotizaciones.
3. Completar cotejo visual en estados equivalentes y corregir la rama Servicio, iconografía, densidad y todas las discrepancias de la matriz.
4. Inspeccionar opciones adicionales de preparados (incluida reversión de preparación), costos adicionales de insumos y formularios de configuración de precios antes de ampliar su alcance. No figuran como implementadas en esta entrega.
5. Emitir APROBADO global sólo cuando las cinco pantallas y sus ramas comparadas aprueben. Los cambios permanecen locales, sin commit/push de esta tarea.
