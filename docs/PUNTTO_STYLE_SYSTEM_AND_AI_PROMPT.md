# Sistema visual de Puntto y prompt para unificar MiCatalogo

Inspección: 10 de octubre de 2026. Referencia real: `com.puntto.app`, emulador `emulator-5554`, pantalla física 1344 × 2992, densidad 480 dpi. Este documento entrega una especificación y un prompt; no representa una implementación de estilos en MiCatalogo.

## 1. Qué se observó

| Pantalla | Estados inspeccionados | Patrón dominante |
|---|---|---|
| Terminal | Lista de productos, recientes, formulario Servicio | Encabezado operativo compacto, selector Detalle/Mayoreo, búsqueda y escáner, chips de tipo, filas compactas con precio a la derecha, acción inferior fija |
| Pedidos | Lista con pedido pendiente, confirmación sin ejecutar | Título fuerte, métricas agrupadas, búsqueda y segmentos, tarjeta con estado/importe, metadatos, acciones de ancho completo, confirmación en hoja inferior |
| Inventario | Inicio y final de lista, filtros, menú contextual de producto | Acciones secundarias delineadas, acción de creación sólida, métricas 2 × 2, filtros, selección múltiple, filas agrupadas, pie de resumen |
| Cotizaciones | Lista con cotización convertida y detalle | Encabezado editorial, métricas agrupadas, tarjeta documental, badge de estado, código monoespaciado, detalle por secciones y total alineado a la derecha |
| Decants | Tarjeta contraída y expandida, tamaños, sección final de botella, formulario Preparar decants sin continuar | Resumen 2 × 2, filtros con conteos, tarjetas expandibles, stock/progreso, badges, precios y unidades monoespaciados, filas por tamaño, switches y acciones |

También se observó el menú lateral compartido. No se guardaron ventas, pedidos, clientes, ajustes, preparaciones ni cambios de inventario. Los estados vacío/error/carga y un diálogo centrado independiente no se recorrieron en todas las pantallas; sus reglas en el prompt son criterios de diseño y verificación, no observaciones de esos estados.

### Lenguaje visual común

- Interfaz sobria y utilitaria: superficies claras, contraste fuerte, bordes finos y poca elevación.
- Títulos de pantalla grandes y pesados; descripciones de una o dos líneas con menor contraste.
- Dos roles tipográficos: sans serif para lectura y acciones; monoespaciada para importes, métricas, códigos, unidades y muchas etiquetas de sección.
- Etiquetas de sección pequeñas, mayúsculas y con tracking visible. El texto de botones conserva capitalización natural.
- Esquinas redondeadas en tarjetas, campos y botones. Los chips/badges tienen variantes de píldora; no todos los controles usan el mismo radio.
- Iconos de contorno, ligeros y consistentes, con tamaños proporcionados al texto.
- Métricas en un contenedor común dividido por líneas internas, en lugar de tarjetas flotantes independientes con grandes sombras.
- Filas con jerarquía clara: nombre principal, código/metadatos secundarios, cantidad/precio alineados, acciones discretas.
- Formularios y confirmaciones móviles en hojas inferiores: fondo atenuado, esquinas superiores redondeadas, asa, título, cierre, separadores y pie de acciones.
- Los filtros extensos ocupan casi toda la altura y mantienen pestañas y acciones accesibles.
- Navegación superior e inferior persistente en las pantallas administrativas. Terminal tiene una variante operativa compacta.
- Menús contextuales pequeños anclados al control de opciones; acción destructiva separada y con tratamiento semántico.
- Los estados comunican significado con texto, icono y tratamiento de superficie, no únicamente con color.

### Tipografía: lo conocido y lo pendiente

La inspección inicial confirmó el contraste entre sans serif y monoespaciada. Posteriormente se verificaron las declaraciones `@font-face` del HTML público de `https://puntto.do/app`: **Schibsted Grotesk** (400/500/600/700/800) y **Geist Mono** (400/500/600). Se importaron las mismas fuentes públicas a recursos Android, conservando sus licencias SIL OFL en assets. Esto verifica la familia; la identidad de tamaños, interlineado, espaciado y pesos de cada estado requiere comparación visual. No confundir la fuente de la barra del sistema con la de la aplicación.

### Geometría de partida

En este dispositivo 3 píxeles físicos equivalen a 1 dp con densidad 480 dpi. El margen exterior observado de 36 px equivale a 12 dp; algunos botones observados miden 132–138 px de alto, aproximadamente 44–46 dp. Esto no demuestra los valores CSS originales ni permite deducir directamente el tamaño de letra en sp.

Los siguientes valores son **tokens iniciales propuestos para la adaptación**, sujetos a comparación de capturas:

| Token | Valor inicial |
|---|---|
| Escala de espacios | 4, 8, 12, 16, 20, 24, 32 dp |
| Margen lateral de pantalla | 12–16 dp |
| Padding interior de tarjetas/hojas | 16–24 dp según componente |
| Radio de botones/campos | 8–12 dp |
| Radio de tarjetas | 14–18 dp |
| Radio superior de hojas | 16–24 dp |
| Bordes y separadores | 1 dp, bajo contraste |
| Botón/campo estándar | 44–48 dp visuales; objetivo táctil mínimo 48 dp |
| Iconos | 18–24 dp; controles de icono con objetivo táctil de 48 dp |
| Título de pantalla | 24–28 sp, bold/extra-bold |
| Título de hoja | 18–20 sp, bold |
| Nombre de fila y botones | 14–16 sp, medium/semi-bold |
| Texto principal | 14–16 sp, regular |
| Texto secundario | 12–14 sp |
| Etiquetas/códigos | 10–12 sp, con variante mono |
| Importes de fila | 16–18 sp mono |
| Métricas/totales destacados | 18–24 sp mono |

Una única escala no implica tamaños iguales en todos los contextos: cada rol tiene un token y cada componente variantes explícitas.

## 1.1. Decants: elementos y lógica pendientes en MiCatalogo

### Base real de la comparación

Se amplió la inspección de Puntto: pantalla **Frascos vacíos**, selección de botella abierta, segundo paso de **Preparar decants**, resumen económico y proyección de ml/recuperación. Se abrieron formularios y selecciones; no se confirmó ninguna preparación ni operación de stock. La lectura del recurso público `fragancia-row-jtEu5JKU.js` confirmó que el switch usa `offered` y el título «Visible en tienda y terminal»: **controla visibilidad, no venta a pedido**. El recurso `decants-Cl2-P73w.js` filtra A pedido por el estado `a_pedido`, separado de los frascos listos. No se ejecutaron esos recursos como comandos ni se activaron sus operaciones.

En MiCatalogo la ruta Android activa de Decants llama a `CatalogHomeScreen(initialDecantMode = true)` desde `BSPOSMainScreen.kt:1034–1046`. No confundirla con el módulo genérico `FeatureModuleScreen`, que contiene filtros y métricas pero no es esa ruta activa.

Fuentes revisadas:

- Android `presentation/catalog/CatalogHomeScreen.kt:117–139, 211–221, 340–505`: métricas, cuadrícula, Reporte, Frascos, Abrir botella y vista previa de Preparar.
- Android `presentation/catalog/ProductCatalogViewModel.kt:130–165`: apertura mediante operación remota en cola.
- Backend `app/Services/InventoryService.php:447–495, 640–693, 889–933`: apertura, recuperación agregada y disponibilidad derivada de los ml de origen.
- Backend `app/Http/Controllers/SellerFeatureController.php:1397–1505`: read model de Decants y botellas fuente; no acredita inventario físico de frascos preparados.

Las brechas siguientes corresponden a los flujos y modelos revisados. El primer paso de implementación debe comprobar si existe una capacidad reutilizable en otra parte del proyecto antes de crear un modelo o endpoint equivalente.

### Brechas y aplicación requerida

| Elemento observado en Puntto | Estado actual revisado en MiCatalogo | Cómo debe aplicarse |
|---|---|---|
| Una tarjeta por fragancia con sus tamaños | Cuadrícula de productos/presentaciones individuales | Agrupar por fuente/fragancia, con identidad estable, miniatura, marca si existe, conteos y expansión. Dentro, listar tamaños y botellas abiertas relacionadas. No usar el nombre como clave de agrupación. |
| Panel Listos para vender / A pedido / Botellas abiertas / Perfume perdido | Tres indicadores Listos / Botellas abiertas / Ml disponibles | Crear un read model con definiciones explícitas: unidades realmente preparadas y valor de venta, presentaciones a pedido, número real de aperturas/botellas y merma acumulada en ml. El conteo actual de abiertas cuenta productos fuente con alguna apertura, no suma botellas. |
| Todas / Listos / A pedido / Sin botella / Se agota, con conteos | La ruta nativa no muestra estos filtros; existen en otro módulo genérico | Implementarlos en la ruta activa y añadir búsqueda por fragancia/marca. Calcular conteos por fragancia con reglas documentadas; no comparar textos traducidos ni ocultar datos de botella al filtrar tamaños. Los filtros pueden solaparse. |
| Resumen compacto de ml restantes, capacidad, rendimiento y precio/costo por ml | Texto de fuente/pool reservado y precio en cada tarjeta | Añadir progreso por botella y resumen por fragancia. Rendimiento por tamaño = parte entera de ml utilizables / ml del tamaño; identificarlo como capacidad de preparación, no como frascos ya preparados. |
| Tamaños con miniatura, listos/en estante, precio, costo, margen, switch, Preparar y opciones | Tarjeta genérica con precio, costo y botón Preparar | Filas por tamaño con stock físico y métricas reales; controles reutilizables y acciones conectadas. Verificar el significado del switch antes de asignarle lógica; distinguir modalidad a pedido, disponibilidad y publicación. |
| Pantalla Frascos vacíos: tamaños, vacíos, invertido, llenos, lotes, próximo costo FIFO, recibir y editar | Diálogo «Frascos y presentaciones» que enumera decants y cantidades derivadas | Gestionar envases vacíos como insumo independiente por tamaño, con lotes/costo FIFO, recepción y trazabilidad. Vacíos no son decants vendibles; llenos se calculan desde preparaciones físicas. Archivar en lugar de romper referencias históricas. |
| Preparación operativa en dos pasos | `DecantPrepareDialog` muestra vista previa; permite cerrar o crear una presentación, no preparar un lote físico | Seleccionar botella abierta primero; ofrecer selladas aptas para abrir mediante paso explícito; elegir tamaño, cantidad, precio y mostrar límites de ml/envases, costo, margen y efecto sobre la botella. Confirmar mediante una operación atómica real; crear presentación es una acción distinta. |
| «Vaciar la botella (N)», cantidad +/- y preview antes/después | Sin esos controles ni cálculo en el formulario actual | Proponer cantidad máxima compatible con ml y frascos vacíos; mostrar sobrante si no es divisible por el tamaño. No convertir sobrante automáticamente en merma. Confirmar sólo tras validar contra stock actual del servidor. |
| Costo de frasco preparado y proyección de ganancia | Se muestra costo de producto/fuente, sin desglose de envase/preparación | Calcular costo de perfume por lote + envase FIFO + otros costos explícitamente configurados. Separar margen de recargo y proyección de resultado realizado. No usar floats para dinero. |
| «Ya recuperado», «¿Ya se pagó?» y recuperación proyectada al vender el lote | Backend calcula recuperación agregada por producto fuente; la ruta nativa no muestra el panel ni la proyección | Exponer costos/ingresos trazables por apertura/lote cuando sea posible. Reutilizar el cálculo existente como base, distinguiendo agregado de producto de una botella individual. Preparar no genera ingresos; «si vendes este lote» es sólo proyección. |
| Vendidos este mes / ingresos / margen en el detalle expandido | Reporte nativo resume presentaciones y cantidades actuales | Consultar ventas netas por período, devoluciones/anulaciones y costos asignados. Mostrar el período y la fuente; no deducir ventas mensuales de existencias actuales. |
| Sección Botella abierta: fecha, ml, costo, recuperación, selladas y acciones Preparar / Vender el resto / Descartar / Deshacer | Apertura ya implementada, pero sin espacio de trabajo completo por botella | Mantener apertura existente y añadir identificador/fecha/estado/movimientos por apertura. Vender resto debe usar el flujo de venta; Descartar registrar merma; Deshacer generar reversión elegible y auditada, no borrar movimientos. |
| Preparar prioriza botellas ya abiertas | Vista previa elige producto enfocado o primera presentación | Selector explícito de botellas abiertas, con ml reales y prioridad visible; confirmar fuente/tamaño. No abrir una botella nueva silenciosamente ni seleccionar una fuente por posición de lista. |

### Regla central: preparado no equivale a preparable

Hoy varias presentaciones calculan su disponibilidad desde el mismo pool de origen. Esas capacidades son **alternativas que compiten por los mismos ml**, no stock físico acumulable. Con 100 ml, mostrar capacidad de 20 × 5 ml y de 10 × 10 ml no significa que existan 30 frascos listos. Sumar esas cantidades para «Listos» o multiplicarlas todas para «Ml comprometidos» contabiliza el mismo perfume varias veces.

La adaptación debe separar:

1. Botellas selladas y sus ml/costo.
2. Perfume utilizable de cada botella abierta.
3. Envases vacíos por tamaño y lote FIFO.
4. Lotes de frascos preparados por fuente, tamaño, cantidad y costo asignado.
5. Capacidad a pedido, calculada con restricciones reales y sin tratarla como inventario físico.
6. Ventas, devoluciones y mermas trazables.

Al preparar `n` frascos de tamaño `v`, transferir `n × v` ml del pool abierto al lote preparado y consumir `n` envases vacíos. **No aumentar el volumen ni la valoración total por crear una presentación.** El costo se reclasifica desde sus fuentes al producto preparado; no se registra como venta ni se carga dos veces como costo.

Al vender un frasco ya preparado, descontar el lote preparado y su costo; no volver a descontar los ml de la botella. Una venta a pedido debe preparar/consumir sus insumos una única vez antes o dentro de la misma transacción. Conservar FIFO, idempotencia, permisos y colas existentes; ampliar el contrato de venta para identificar el origen físico cuando corresponda.

Fórmulas propuestas para implementar, no afirmaciones sobre código interno de Puntto:

- `máximo_preparable = min(floor(ml_utilizables / tamaño_ml), envases_vacíos_disponibles)` para preparaciones con envase.
- `ml_después = ml_antes - cantidad × tamaño_ml`.
- `costo_lote_preparado = costo_perfume_asignado + costo_envases_FIFO + costos_configurados`.
- `ganancia_proyectada = ingreso_neto_proyectado - costo_lote_preparado`.
- `margen_porcentaje = ganancia_proyectada / ingreso_neto_proyectado × 100`, si el ingreso es positivo.
- Recuperación observada por ventas frente al costo de la fuente: conservar su significado comercial y mostrarlo por separado de beneficio neto y de cobros efectivamente recibidos. Aplicar ventas netas y alcance de período/lote definidos.

### Integración y pruebas necesarias

- Ampliar backend/DTO/repositorio y estado Android antes de llenar la UI con valores. Modelos propuestos: tamaño/envase, lote de envases, apertura, lote preparado y movimientos vinculados; adaptar nombres a la arquitectura real y reutilizar lotes existentes.
- Cada comando debe validar tienda, permisos, fuente, capacidad, stock y versión esperada; ejecutar transaccionalmente con bloqueo y clave idempotente. Mostrar pendiente, sincronizado y conflicto en Android; no anunciar éxito remoto porque se encoló.
- No reinterpretar el stock histórico derivado como frascos físicos. Planificar compatibilidad y conciliación de datos existentes con respaldo; no sembrar ni recrear producción para esta tarea.
- Pruebas aisladas mínimas: preparación reduce ml/envases y aumenta preparados; venta preparada no consume perfume dos veces; dos tamaños compiten por el mismo pool; insuficiencia de ml/envases; reintento idempotente; dos preparaciones concurrentes; FIFO mixto de lotes; devoluciones; merma; reversión bloqueada tras consumo incompatible; permisos y aislamiento por tienda.
- Presentaciones y precios deben mantenerse coherentes entre Decants, Terminal y tienda pública; diferenciar precio configurado de precio histórico del lote/venta.
- La aprobación funcional de Decants y la aprobación visual son independientes: una UI idéntica con una preparación ficticia no aprueba.

## 2. Prompt listo para una IA de desarrollo

Copiar desde «Inicio del prompt» hasta «Fin del prompt». Adjuntar las capturas de referencia cuando la IA no tenga acceso al emulador.

### Inicio del prompt

Actúa como especialista en sistemas de diseño y desarrollo de interfaces móviles. Tu tarea es unificar el estilo de **todas las pantallas de MiCatalogo**, tomando como referencia visual la app Puntto inspeccionada en Terminal, Pedidos, Inventario, Cotizaciones y Decants.

El resultado debe sentirse como una sola aplicación: misma tipografía, jerarquía, geometría, densidad, iconografía, botones, campos, filtros, tarjetas, modales y navegación. Conserva íntegramente la marca y los colores principales actuales de MiCatalogo.

#### A. Fuente de verdad y alcance

1. Lee el análisis y las capturas de `docs/PUNTTO_STYLE_SYSTEM_AND_AI_PROMPT.md`. Si tienes acceso al emulador, inspecciona Puntto en los mismos estados antes de implementar.
2. La referencia real prevalece sobre una interpretación genérica de «diseño moderno». Reproduce su lenguaje visual, orden y proporciones donde corresponda.
3. Revisa todas las rutas y estados de MiCatalogo, no sólo las cinco pantallas de referencia. Incluye formularios, detalles, menús contextuales, confirmaciones, selectores, ayuda, vacíos, carga y errores.
4. Conserva datos, funciones, permisos, navegación funcional, cálculo de dinero, validaciones, ViewModels, sincronización y operaciones offline. Cambia la presentación y la composición visual necesaria, sin eliminar funcionalidades ni inventar cifras o acciones.
5. Identifica el repositorio Android activo y sus cambios locales antes de editar. En este equipo el trabajo reciente está en `C:/Users/waila/AndroidStudioProjects/micatalogowebApp`; verifica esa ruta, no uses una copia antigua por su nombre.
6. Completa las brechas funcionales de Decants descritas en la sección 1.1 de este documento y en el apartado G.1 de este prompt. No limites Decants a una migración cosmética ni confundas su ruta nativa con el módulo genérico.

#### B. Paleta de MiCatalogo

- Mantén los tokens actuales de color principal y secundario, azul/azul marino, identidad y marca de MiCatalogo.
- Donde Puntto usa naranja para CTA, aplica el token principal existente de MiCatalogo, no un naranja copiado.
- Donde Puntto usa superficies oscuras para navegación o selección, usa el token oscuro/contraste correspondiente de MiCatalogo.
- Conserva colores semánticos existentes de éxito, advertencia, error y deshabilitado. Obtén superficies suaves desde esos tokens.
- No introduzcas valores de color por pantalla ni sustituyas globalmente todos los colores por el principal.
- Mantén fondos claros, separación discreta y contraste legible conforme al lenguaje observado.

#### C. Tipografía única con dos roles

- Define una familia sans serif para títulos, nombres, contenido, botones y formularios; define una familia mono para importes, métricas, códigos, cantidades/unidades y etiquetas técnicas.
- Verifica las familias de la referencia si es posible. Si no se pueden identificar, documenta que son una aproximación y usa una pareja coherente; no afirmes una coincidencia de fuente no comprobada.
- Centraliza roles: `ScreenTitle`, `SheetTitle`, `RowTitle`, `Body`, `SupportingText`, `ButtonLabel`, `SectionLabel`, `Code`, `Money`, `Metric`.
- Títulos fuertes; nombres y botones medium/semi-bold; texto descriptivo regular; metadatos de menor contraste.
- Etiquetas de sección pequeñas, mayúsculas y espaciadas. No pongas todos los textos en mayúsculas.
- Importes alineados y con cifras de ancho estable. Respeta el formato monetario/localización de MiCatalogo.
- Usa la escala inicial del documento y ajusta por comparación visual; no copies píxeles físicos como si fueran dp/sp.
- Respeta escalado de fuente. Evita recortar importes, títulos de acciones o información imprescindible.

#### D. Tokens de geometría

Centraliza espaciados, radios, bordes, alturas y tamaños de icono. Parte de la escala 4/8/12/16/20/24/32 dp; márgenes 12–16 dp; controles 44–48 dp visuales con objetivo táctil mínimo 48 dp; radios 8–12 dp para controles y 14–18 dp para tarjetas. Calibra estos valores contra la referencia.

Usa bordes finos, separadores discretos y elevación mínima. Reserva sombras más visibles para elementos superpuestos como menús y acciones flotantes. Evita gradientes decorativos, contenedores excesivamente grandes, sombras fuertes y mezclas arbitrarias de radios.

#### E. Biblioteca de componentes compartidos

Reutiliza y amplía el tema/componentes existentes. Los nombres siguientes son roles ilustrativos; respeta las convenciones del proyecto y evita duplicar componentes equivalentes.

1. **Botones:** primario sólido; secundario delineado; tonal suave; enlace/terciario; icon-only; destructivo semántico. Unifica altura, radio, padding, peso, icono y separación. Define estados normal, presionado, deshabilitado y ocupado. Una acción principal dominante por sección/pie; conserva variantes justificadas.
2. **Campos:** búsqueda con icono inicial; campo de texto/número con label externo cuando la referencia lo usa; superficie neutra y borde suave; ayuda y error debajo. Consistencia en foco, teclado, clear y validación. Importes con alineación adecuada al contexto. No mezcles indiscriminadamente labels flotantes y externos para un mismo rol.
3. **Chips y segmentos:** separa chips de categoría, selector segmentado y badges informativos. Categorías con forma de píldora; segmentos dentro de un contenedor común; selección con fondo/contraste y peso. Conteos secundarios consistentes. Scroll horizontal si no caben; no reducir a texto ilegible.
4. **Badges:** pequeños, sin comportamiento de botón salvo que realmente actúen; estados con texto y superficie semántica suave. Stock, pendiente, convertido y agotado deben seguir la misma gramática.
5. **Tarjetas:** superficie clara, borde fino, padding regular y radio común por categoría. Métricas agrupadas en paneles divididos internamente. Tarjetas documentales con nombre, código, estado, importe, metadatos y acciones. Detalles expandibles con divisor y chevron consistente.
6. **Filas/listas:** miniatura o placeholder inicial, nombre dominante, metadatos debajo, cifras alineadas y acciones discretas. Separa filas con líneas suaves. Mantén filas compactas en Terminal y densidad administrativa en Inventario/Pedidos.
7. **Checks, radios y switches:** mismo tamaño, alineación y estados semánticos; variantes reutilizables. No modificar datos al abrir un selector.
8. **Menús contextuales:** popover anclado a opciones, superficie clara, borde/radio discretos, filas táctiles, acción destructiva separada. No convertir todas las opciones en grandes botones primarios.
9. **Iconografía:** una familia de iconos outline de grosor comparable; tamaños 18–24 dp por rol; posición y espaciado uniformes. Evita combinar emojis, iconos sólidos y trazos incompatibles.
10. **Estados vacíos/carga/error:** misma jerarquía y espaciado, texto útil y acción real; no interpretar un fallo de carga como cero resultados.

#### F. Modales, diálogos y hojas inferiores

- Estándar observado para formularios y confirmaciones móviles: hoja inferior con scrim, esquinas superiores redondeadas, asa discreta, título fuerte, cierre arriba a la derecha, separador, contenido y pie de acciones.
- La altura depende del contenido: confirmación corta compacta, selección mediana, filtros extensos casi a pantalla completa. No fuerces todas las hojas a la misma altura.
- Encabezado y acciones permanecen accesibles; el cuerpo puede desplazarse. Respeta teclado, barras del sistema, orientación y pantallas pequeñas.
- Botón de cancelar secundario y CTA principal con estilo compartido; ancho según el contexto observado, sin imponer dos botones iguales a todos los pies.
- Si se conserva un diálogo centrado por necesidad de uso, que derive de los mismos tokens y componentes. No atribuyas a Puntto un diálogo centrado que no se haya observado.
- No dependas de los estilos por defecto del framework para producir una apariencia distinta de la referencia.
- Preserva el estado del flujo padre al abrir un formulario hijo: pedido seleccionado, tipo de pago, abono, referencia y cliente. Crear/seleccionar un cliente debe cerrar sólo el formulario hijo y regresar al pedido intacto. Volver/cancelar no debe producir cierres en cascada ni confirmar operaciones.

#### G. Composición por contexto

- **Terminal:** encabezado operativo compacto; selector Detalle/Mayoreo; búsqueda, escáner y Servicio; categorías Productos/Decants/Servicios; recientes compactos; lista de productos con precios; CTA inferior. Conserva cotizar y opciones. Evita añadir un encabezado administrativo y una cuadrícula grande que cambien el primer viewport.
- **Pedidos:** título, métricas agrupadas, búsqueda/estados, tarjetas con importe/badge/metadatos y acciones, pie de conteos; confirmación con la misma biblioteca de hojas.
- **Inventario:** título/descripción, acciones, pestañas/conteos, aviso contextual, métricas, búsqueda/filtros, lista seleccionable, opciones y pie. Formularios y filtros con los mismos controles.
- **Cotizaciones:** etiqueta de contexto, título/descripción, métricas, búsqueda y tarjetas documentales; detalle con secciones, importes y total claramente alineado.
- **Decants:** acciones, métricas, búsqueda/filtros, tarjetas expandibles, stock/progreso y tamaños; utiliza componentes comunes también en switches, precios y formularios de preparación.
- **Resto de la app:** elige la composición funcional adecuada usando esos mismos componentes. Un estilo único no significa convertir cada módulo en la misma pantalla.
- Mantén un shell compartido: navegación inferior Terminal/Pedidos/Inventario/Más, estado seleccionado, menú lateral, insets y encabezados coherentes. La variante de Terminal debe ser explícita, no una navegación alternativa accidental.

#### G.1. Decants: implementación funcional obligatoria

Además del estilo, completa estos flujos después de comprobar las capacidades existentes:

1. Agrupa por fragancia/fuente en tarjetas expandibles con tamaños, botella abierta, ml restantes, progreso, rendimiento y precios. Agrega búsqueda por fragancia/marca y filtros con conteos Todas/Listos/A pedido/Sin botella/Se agota en la ruta Android activa.
2. Distingue **preparados físicamente** de **capacidad de preparación**. No sumes capacidades de distintos tamaños que comparten el mismo perfume. El panel debe mostrar preparados y su valor, a pedido, botellas abiertas reales y merma en ml.
3. Convierte Frascos en gestión real de envases vacíos por tamaño, cantidades, recepción, lotes, costo FIFO e inversión. El diálogo actual de presentaciones no es equivalente.
4. Sustituye la vista previa de Preparar por un flujo real: botella abierta → tamaño → cantidad → precio → preview de costos, margen y ml antes/después → confirmación transaccional. Prioriza abiertas; abrir sellada debe ser explícito. Añade cantidad +/- y máximo preparable limitado por ml y envases; conserva sobrantes.
5. Preparar transfiere ml y costo al lote preparado y consume envases, sin generar venta. Vender un preparado descuenta ese lote, no nuevamente los ml originales. A pedido consume insumos una única vez. Preserva FIFO, idempotencia, sincronización y conflictos.
6. Incluye costo perfume + envase FIFO y otros costos configurados; usa dinero en centavos y redondeo consistente. Separa proyección de ganancia, ingresos registrados, cobros y recuperación de fuente.
7. Muestra ventas netas del mes, ingresos/margen y recuperación actual/proyectada con datos reales y permisos. No uses la recuperación agregada de un producto como si perteneciera a una botella individual sin trazabilidad.
8. Integra el detalle de apertura con fecha, stock, costo, recuperación y acciones Preparar/Vender el resto/Descartar/Deshacer. Merma y reversión deben crear movimientos auditables; no borrar historial. Verifica elegibilidad de la reversión.
9. El switch de tamaño controla visibilidad en tienda y Terminal (`offered`); no cambia la modalidad a pedido ni elimina el stock preparado. A pedido debe contar tamaños ofrecidos, sin preparados y con capacidad real. Mantén separadas visibilidad, inventario y modalidad. Las opciones adicionales requieren inspección y verificación aislada, no una acción inventada.
10. Conecta backend, DTO, repositorios y UI; conserva compatibilidad con stock histórico derivado y evita duplicar valoración. Verifica preparación, ventas, devoluciones, merma, FIFO, concurrencia, idempotencia y permisos en fixtures aislados.

#### H. Implementación y aceptación

1. Haz un inventario de pantallas y componentes inconsistentes. Define tokens y variantes antes de extender cambios.
2. Migra componentes comunes y después pantallas en grupos verificables. No copies estilos a mano en cada archivo ni dejes dos sistemas visuales activos por accidente.
3. Revisa cada pantalla completa y sus estados, no sólo el primer viewport. Verifica formularios con teclado, scroll, etiquetas largas, importes grandes y distintos tamaños de pantalla.
4. Compara Puntto y MiCatalogo en el mismo emulador, orientación, tamaño y estado equivalente; acepta la diferencia de paleta/marca y datos reales, pero revisa orden, densidad, tipografía, geometría y estados de controles.
5. Comprueba navegación de ida/vuelta, tabs, filtros, apertura/cierre de hojas y estado padre/hijo. No declares paridad basándote solamente en compilación.
6. Usa pruebas aisladas para operaciones financieras y datos de negocio; no crees ni confirmes transacciones reales para obtener capturas. Selecciona explícitamente el emulador en cada operación de pruebas.
7. Entrega tokens, componentes reutilizados/creados, lista de pantallas migradas, comparación visual y discrepancias pendientes. Diferencia lo observado de lo aproximado.
8. No publiques, instales en el teléfono ni hagas commit/push sin autorización para esas acciones.

#### I. Comparación final obligatoria: aprobación de estilos idénticos

**Una vez terminada la implementación, realiza una auditoría comparativa completa de Terminal, Pedidos, Inventario, Cotizaciones y Decants contra Puntto. No declares la tarea terminada ni aprobada antes de esa revisión.**

1. Captura ambas aplicaciones en el mismo emulador, resolución, densidad, orientación y escala de fuente. Usa estados y contenido equivalentes mediante fixtures cuando haga falta; iguala posición de scroll, teclado y selección. No alteres producción para igualar cifras.
2. Para cada pantalla captura inicio, tramos intermedios y final. Abre sus formularios, hojas, diálogos, filtros, selectores y menús contextuales; verifica botones normales, ocupados, deshabilitados y seleccionados cuando corresponda.
3. Compara lado a lado y, para geometría, mediante superposición alineada. No uses el porcentaje de píxeles diferentes como criterio único: la paleta y marca de MiCatalogo se conservan, y los datos pueden ser distintos.
4. Completa una matriz **por pantalla y por modal/diálogo** con estas categorías:

| Categoría | Qué debe coincidir |
|---|---|
| Tipografía/font | Familia o equivalencia técnicamente justificada, peso, tamaño, interlineado, tracking, mayúsculas, cifras mono/tabulares y alineación |
| Botones | Variante, altura/ancho, radio, borde, padding, icono, separación icono-texto, peso y estados |
| Modales/diálogos | Tipo y posición, altura según contenido, radio, scrim, asa, cierre, título, separadores, scroll, pie y comportamiento con teclado |
| Espacios/márgenes | Márgenes exteriores, padding de tarjetas/hojas, gaps entre secciones/controles, separación de filas y relación con barras del sistema |
| Composición | Orden, jerarquía, densidad, alineación de importes, controles visibles y ausencia de bloques adicionales ajenos a la referencia |
| Componentes restantes | Campos, chips, segmentos, badges, iconos, miniaturas, checks, switches, divisores y progreso |
| Interacción | Apertura/cierre, navegación, selección, scroll, foco y conservación del flujo padre/hijo |

5. Registra para cada hallazgo captura de referencia/destino, componente, medida o diferencia, severidad y corrección. Los tokens iniciales del documento no son tolerancias de aprobación: deben ajustarse a la referencia observada.
6. Usa los veredictos **APROBADO**, **NO APROBADO** o **PENDIENTE DE EVIDENCIA** por categoría y pantalla. «Se parece», «compila» y «tiene las mismas funciones» no son veredictos válidos.
7. Aprueba como **idéntico en estilos** sólo cuando no queden discrepancias visuales relevantes de las categorías anteriores, exista evidencia para todos los estados inspeccionables y las únicas diferencias intencionales sean paleta/marca/datos propios. Una fuente aproximada sin comprobar su equivalencia deja Tipografía pendiente; no afirmes identidad por usar cualquier sans/mono.
8. Una adaptación necesaria por accesibilidad o una función adicional propia debe quedar identificada con comparación y justificación; si cambia la apariencia respecto a Puntto, no la presentes como identidad estricta sin aceptación expresa del usuario.
9. Si una pantalla o modal no califica, corrige y vuelve a capturar/revisar ese estado y los componentes compartidos afectados. Continúa hasta que califique o documenta un bloqueo verificable. No promedies pantallas aprobadas para ocultar una no aprobada.
10. Entrega el informe final con matriz, capturas, medidas, cambios y veredicto global. **El veredicto global sólo es APROBADO si las cinco pantallas y sus modales/diálogos comparados aprueban.** La funcionalidad de Decants debe aprobar sus pruebas por separado.

Objetivo final: **el lenguaje visual de Puntto aplicado de forma sistemática a toda MiCatalogo, con la identidad cromática y la lógica de MiCatalogo intactas**.

### Fin del prompt

## 3. Evidencia privada de esta inspección

Implementación y auditoría preliminar: consultar también `docs/PUNTTO_IMPLEMENTATION_AUDIT_2026-10-10.md`. La implementación local y las pruebas no equivalen a despliegue ni a aprobación global de identidad visual.

PNG, XML y mapas de nodos permanecen en `C:/Users/waila/AppData/Local/Temp/opencode`. No adjuntar automáticamente a Git: contienen datos de la sesión. Archivos representativos:

- `puntto-terminal-20261010-030644-dc1677ca.png`
- `puntto-terminal-servicio-20261010-030703-7b7c66fd.png`
- `puntto-pedidos-lista-20261010-030731-0bf50846.png`
- `puntto-pedidos-confirmar-20261010-030751-46586b3b.png`
- `puntto-inventario-20261010-030811-1bfa94bf.png`
- `puntto-inventario-filtros-20261010-030828-5726e3f7.png`
- `puntto-inventario-final-20261010-030855-8aa83805.png`
- `puntto-inventario-menu-20261010-030916-f9746545.png`
- `puntto-cotizaciones-20261010-031002-55991da6.png`
- `puntto-cotizacion-detalle-20261010-031023-901588f4.png`
- `puntto-inicial-20261010-030553-5bca94ab.png` (Decants, estado inicial)
- `puntto-decants-modal-20261010-030630-997e7047.png`
- `puntto-decants-tamanos-20261010-031142-e230ffce.png`
- `puntto-decants-final-20261010-031220-241370e6.png`
- `puntto-menu-mas-20261010-030938-efc83fd3.png`
- `puntto-decants-frascos-20261010-031840-0f2ccbc1.png` (envases vacíos y lotes FIFO)
- `puntto-decants-preparacion-paso2-20261010-031908-61831606.png` (tamaño, cantidad, precio y costo)
- `puntto-decants-preparacion-costos-20261010-031931-f97051d4.png` (ml antes/después y recuperación proyectada)
