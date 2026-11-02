# Comparativa REA: Puntto y MiCatalogo

Fecha: 2026-10-19  
Emulador: `emulator-5554`  
Alcance: inspección estática del APK, observación de arranque y comparación con la app Android y el código de MiCatalogo.

## Resultado principal

Puntto no contiene el catálogo ni la lógica completa del negocio dentro de su APK. Es una envoltura Capacitor/WebView que carga la aplicación remota:

```text
https://puntto.do/app
```

La configuración local permite navegar por `puntto.do`, `*.puntto.do` y `puntto.aansoluciones.com`. El `index.html` incluido es un placeholder de carga. Por eso no es posible obtener del APK las pantallas completas, reglas de negocio o endpoints de Puntto mediante decompilación local: esa parte llega desde el servidor en tiempo de ejecución.

## Evidencia observada

- Paquete Puntto: `com.puntto.app`, versión `1.0.4`, código `8`.
- APK base extraído: `qa/rea-pulled-puntto-20261019/com.puntto.app/base.apk`.
- SHA-256 del APK: `5d753f53a6c6964bb1505fbb0786e3b19d6a2987a84ba57b66213d0ab8404df6`.
- Runtime detectado: Dalvik/Android + JavaScript bridge.
- Recursos web locales: `native-bridge.js`, `cordova.js`, `cordova_plugins.js` y placeholder HTML; no hay bundle de negocio local.
- Plugins declarados: App, Filesystem, Haptics, Keyboard, Network, Push Notifications, Share, Splash Screen y Status Bar.
- Arranque observado: WebView Chromium y puente Capacitor; Firebase aparece inicializado en logcat.
- Pantalla inicial observada: `Operación / Resumen`, saludo, `Registrar cobro`, `Nueva venta`, guía de primeros pasos, periodos `Hoy / Este mes / Últimos 7`, métricas de ventas/ganancia/cobros e inventario, y navegación inferior `Terminal / Pedidos / Inventario / Más`.

Evidencias REA principales: `ev_478ec1b00853b672cac997a0ca691a7f029db8d8af51905baa6d1606480e2bfb`, `ev_2755fff62a3cdf140580866a6d0308c522a220c695ec49b0b47810a639d70ae4`, `ev_82bed96b8cf9c14d23b716e62de74f10aa04197b6b5067e4003aeac2e9f08d8b`, `ev_c70b56c1275df8edd42d9599aaf2c72ae35f0d60dd2eaee2143417ae10a4e93a` y `ev_cbe86121c1fffe728b0be4032506e21f10c2eb998609deb5b1dfb6052bdca569`.

## MiCatalogo verificado

MiCatalogo QA instalado en el mismo emulador: `com.bsolutions.micatalogo.offlinecheck.emulatorqa`, versión `1.0.98-offlinecheck`, código `99`.

Su APK es nativo Compose/Room/WorkManager, con 21 DEX y librerías nativas. No depende de que la pantalla operativa sea un WebView remoto. El código local ya contiene módulos para POS, inventario, clientes, cobros, caja, ganancias, reportes, compras, decants y permisos por tienda/rol.

La pantalla actual del APK QA quedó en login porque la contraseña no se introduce durante esta inspección. No se usaron credenciales ni se ejecutaron operaciones contables. La comparación post-login se apoya en las auditorías existentes del proyecto y debe repetirse con una sesión QA controlada antes de aprobar paridad visual.

## Qué conviene copiar de Puntto

### Prioridad 1: Inicio orientado a la operación

La primera pantalla de Puntto responde inmediatamente a tres preguntas: qué está pasando hoy, qué acción debe hacer el cajero y qué falta completar. MiCatalogo debe priorizar:

1. `Nueva venta` y `Registrar cobro` como acciones principales.
2. Selector de periodo visible antes de las métricas.
3. Métricas compactas y comparables: ventas, ganancia, por cobrar e inventario al costo.
4. Guía de primeros pasos con acciones reales, progreso y posibilidad de ocultarla.
5. Resumen de inventario con capital al costo, productos y unidades.

MiCatalogo ya tiene KPIs y acciones rápidas en `DashboardScreen.kt`; la brecha es de jerarquía y densidad visual, no de ausencia total de dominio.

### Prioridad 2: Terminal

Mantener y verificar en una matriz controlada:

- Detalle/Mayoreo.
- Productos/Decants/Servicios.
- Búsqueda y escáner.
- Carrito persistente en la parte inferior.
- Hoja de cobro con cliente, descuento/nota, método, recibido y vuelto.
- Cobro contado, crédito y mixto.

El código de MiCatalogo ya implementa `ModalBottomSheet` para el carrito y lógica de cobro; el siguiente trabajo es la comparación visual y de estados, no reescribir el dominio.

### Prioridad 3: Inventario

Adoptar la estructura de Puntto: indicadores 2×2, filtros rápidos, creación visible, selección múltiple, menú contextual por producto y pie de resumen. Conservar las reglas propias de MiCatalogo: Kardex inmutable, FIFO/CPP, movimientos auditables y protección por tienda.

### Prioridad 4: Navegación

Conservar el drawer agrupado por Operación, Compras, Catálogo, Cobros, Finanzas, Análisis, Equipo y Ajustes, con búsqueda y permisos. En teléfono, mantener accesos inferiores a Terminal, Pedidos, Inventario y Más. MiCatalogo ya tiene la separación por grupos y permisos; falta validar que cada pantalla tenga estados vacíos, carga, error y retorno consistentes.

## Qué no conviene copiar

- No convertir MiCatalogo en una envoltura WebView de Puntto.
- No depender del servidor para vender o consultar el inventario local.
- No copiar la prueba, los datos, la cuenta ni el onboarding de Puntto.
- No ejecutar ventas, cobros, recepción de inventario o cierres para obtener capturas.
- No deducir endpoints o reglas de negocio de los archivos bridge del APK: no contienen esa lógica.

## Plan recomendado de mejora

1. Comparar en sesión QA autenticada: Inicio, Terminal, Pedidos, Inventario, Cotizaciones y Decants.
2. Capturar cada pantalla en vacío, con datos, cargando, error, filtro abierto, hoja abierta y confirmación sin guardar.
3. Ajustar primero el shell móvil y el Inicio: márgenes, tipografía, métricas, CTA, hojas inferiores y navegación.
4. Después ajustar Terminal e Inventario, porque son los flujos de mayor frecuencia.
5. Repetir pruebas funcionales de idempotencia, FIFO, caja, crédito y permisos sin tocar producción.

## Limitaciones

La inspección de código Android de Puntto con JADX no se ejecutó porque el entorno no tiene el JAR caller-supplied requerido por REA. En este caso la limitación no impide la conclusión principal: la configuración del APK y la extracción demuestran que el negocio se carga remotamente. Tampoco se hizo captura de tráfico autenticado del servidor; cualquier endpoint o regla no visible en la configuración queda como desconocido.

## Archivos de referencia

- Captura Puntto actual: `qa/rea-screen-puntto-20261019/screen.png`.
- Captura MiCatalogo QA actual: `qa/rea-screen-micatalogo-20261019/screen.png`.
- Auditoría funcional previa: `docs/PUNTTO_MICATALOGO_AUDIT.md`.
- Auditoría de paridad previa: `docs/PUNTTO_PARITY_AUDIT.md`.
- Sistema visual de referencia: `docs/PUNTTO_STYLE_SYSTEM_AND_AI_PROMPT.md`.

## Matriz completa de funciones

La siguiente matriz combina el menú observado de Puntto, sus formularios y las rutas actualmente declaradas en la app Android de MiCatalogo. `Equivalente` significa que existe el dominio o la entrada; no significa que la paridad visual y todos los estados estén aprobados.

| Área | Función de Puntto | MiCatalogo | Veredicto | Mejora recomendada |
|---|---|---|---|---|
| Operación | Resumen/dashboard | Sí: KPIs, acciones rápidas y ventas recientes | Funcional, visual pendiente | Hacer que Inicio priorice CTA, periodo, onboarding e inventario al costo |
| Operación | Terminal | Sí: venta, carrito, cliente, crédito, mixto, recibido/vuelto | Funcional; visual no aprobado en todas las ramas | Igualar estados, densidad, hoja de cobro y rama Servicios |
| Operación | Ventas | Sí: filtros, estados, actividad y enlaces | Equivalente inicial | Validar cada estado y detalle con datos controlados |
| Operación | Cotizaciones | Sí: crear, detalle, PDF y conversión | Funcional con evidencia incompleta | Completar detalle/PDF desplegado y conversión idempotente en Android |
| Operación | Pedidos | Sí: pendientes, confirmación y orden pública | Equivalente inicial | Probar pedido pendiente, confirmación, rechazo y navegación de retorno |
| Operación | Encargos | Ruta declarada | Pendiente de evidencia funcional | Validar si es distinto de Pedidos; unificar estados y acciones |
| Operación | Envíos | Ruta declarada | Pendiente de evidencia funcional | Añadir estados, guía, transportista, ETA, entrega y devolución |
| Operación | Cierre de día | Sí: sesión, esperado, contado y diferencia | Funcional; falta matriz completa | Mostrar bloqueado/reapertura autorizada y resumen de ventas, abonos, gastos y devoluciones |
| Compras | Contenedores | Sí: borrador, líneas, moneda, notas y recepción | Funcional | Mejorar lectura de factura y seguimiento de recepción |
| Compras | Cargas | Ruta declarada con modelo de rutas | Pendiente de evidencia completa | Completar courier/naviera, guía, ETA, libras, recepción y conciliación |
| Compras | Suplidores | Sí | Equivalente inicial | Añadir historial de deuda, moneda y compras relacionadas |
| Compras | Facturas | Sí: preview textual Excel/CSV/PDF | Funcional parcial | OCR controlado para fotos; no escribir hasta revisión y confirmación |
| Catálogo | Inventario | Sí: stock, ajustes, recepción, conteo, Kardex e importación | Funcional; visual no aprobado | Métricas 2×2, selección múltiple, menú contextual y pie de resumen |
| Catálogo | Fotos | Sí por pipeline de imágenes | Equivalente inicial | Galería, recorte, derivados, reintento y estado de sincronización |
| Catálogo | Mi tienda | Sí: configuración, apariencia, horario, catálogo, vitrina, QR | Funcional | Checklist completa, preview, estado publicado y horario visible sin ambigüedad |
| Catálogo | Servicios | Sí: oferta sin inventario físico | Funcional; visual pendiente | Igualar formulario rápido y diferenciar servicio de producto en cobro/reportes |
| Catálogo | Salud de precios | Sí | Equivalente inicial | Acción masiva segura y explicación de precio sugerido/margen |
| Catálogo | Precios automáticos | Sí | Equivalente inicial | Separar subidas automáticas de bajadas que requieren revisión |
| Catálogo | Decants | Sí: fuente, ml, FIFO y creación nativa | Funcional parcial | Completar abrir botella, preparar, merma, reversión, tres tamaños y evidencia real |
| Catálogo | Marcas y atributos | Sí | Equivalente inicial | Filtros, herencia y edición masiva |
| Catálogo | Importar | Sí: mapeo, preview y confirmación | Superior en seguridad de escritura | Añadir OCR y reporte descargable de filas rechazadas |
| Cobros | Crédito | Sí: límite, saldo y abonos | Funcional | Antigüedad, alertas de vencimiento, promesas de pago y estados de cuenta exportables |
| Cobros | Clientes | Sí | Equivalente inicial | Alta rápida desde POS, WhatsApp, RNC, historial y búsqueda unificada |
| Finanzas | Ganancias | Sí: FIFO, ventas, gastos y pérdidas | Funcional | Comparativa contra periodo anterior, drill-down y exportación consistente |
| Finanzas | Ajustes de inventario | Sí: motivos y Kardex | Superior en trazabilidad | Adjuntar evidencia, autorización y reversión controlada |
| Finanzas | Gastos | Sí | Equivalente inicial | Separar fecha de registro y fecha de pago; efecto en caja vs rentabilidad |
| Finanzas | Socios | Sí | Equivalente inicial | Aportes, retiros y distribución enlazados visualmente a caja |
| Análisis | Reportes | Sí: ventas, servicios, visitas, enlaces, inventario y exportación | Funcional parcial | Igualar filtros, columnas, inventario al costo y exportaciones en todos los estados |
| Equipo | Comisiones | Sí | Equivalente inicial | Congelar base al vender, considerar devoluciones y mostrar liquidación |
| Equipo | Autorizaciones | Sí: permisos sensibles | Equivalente inicial | Flujo visible de solicitud, aprobación, expiración y auditoría |
| Ajustes | Configuración/tienda | Sí | Equivalente inicial | Centro unificado de moneda, impuestos, caja, métodos y capacidades |
| Ajustes | Equipo/vendedores | Sí | Funcional | Vista de roles, permisos efectivos y cambio de tienda más claro |
| Ajustes | Contador | Sí como ruta declarada | Pendiente de evidencia | Confirmar alcance real y separar lectura contable de configuración |
| Ajustes | Mi cuenta | Sí | Equivalente inicial | Perfil, sesiones, dispositivos y recuperación |
| Ajustes | Novedades/Ayuda/Práctica/Soporte | Sí como rutas o módulos | Pendiente de evidencia | Ayuda contextual por pantalla y modo práctica con datos aislados |
| Utilidades | Descargar app | Parcial por flujo de actualización | Pendiente de evidencia | Actualización segura, changelog, compatibilidad y rollback informativo |
| Utilidades | Ver/compartir tienda | Sí: vitrina, QR y catálogo público | Funcional | Compartir profundo, preview y sesión separada explicada al usuario |
| Capacidades extra | Tandas, Insumos, Recetas | No confirmadas en el menú propio | Decisión pendiente | Modelarlas solo si aplican al rubro; no agregarlas como módulos vacíos |

## Qué debe hacer a MiCatalogo superior

1. **Operar sin conexión con claridad.** Mostrar última sincronización, operaciones pendientes, bloqueos y conflictos desde el Inicio; MiCatalogo ya tiene outbox, reintentos y WorkManager, pero esa ventaja debe ser visible para el usuario.
2. **Ser más auditable.** Cada venta, cobro, ajuste, recepción y devolución debe mostrar origen, usuario, caja, costo histórico y estado de sincronización.
3. **Resolver mejor el inventario.** Unificar stock, lotes FIFO, costo, mermas, conteo físico, decants y compras en una línea temporal entendible.
4. **Reducir pasos de alta frecuencia.** Venta rápida, cobro desde cliente, cliente nuevo desde POS, ajuste con motivo y recepción desde factura deben requerir menos navegación que en Puntto.
5. **Hacer visibles los riesgos.** Crédito vencido, producto sin costo, margen bajo, stock negativo, sesión cerrada y operaciones bloqueadas deben tener mensajes accionables, no solo colores.
6. **Convertir la navegación en contexto.** Cada módulo debe conservar filtros, periodo, tienda, vendedor y retorno al flujo padre; las mutaciones complejas que hoy abren el panel web deben mostrar claramente el destino y estado.
7. **Cerrar la paridad visual con evidencia.** Terminal, Pedidos, Inventario, Cotizaciones y Decants siguen sin aprobación estricta global; hay que repetir capturas con fixtures equivalentes y revisar tipografía, espaciado, hojas, iconografía, estados y retorno.

## Orden de implementación recomendado

### Fase A — impacto inmediato

- Rediseñar Inicio alrededor de `Nueva venta`, `Registrar cobro`, periodo, cuatro métricas y guía de primeros pasos.
- Añadir indicador persistente de conexión/sincronización y contador de operaciones pendientes o bloqueadas.
- Terminar la matriz de Terminal, incluyendo Servicios, efectivo recibido, vuelto, crédito y mixto.

### Fase B — operación crítica

- Cerrar Pedidos, Cotizaciones y Cierre de día con estados de éxito, error, bloqueo y reapertura.
- Completar Inventario con métricas, filtros, selección múltiple, ajustes autorizados y resumen FIFO.
- Completar Decants con apertura, preparación, merma y reversión, sin doble valoración.

### Fase C — diferenciadores

- OCR de facturas con revisión humana y trazabilidad.
- Centro de conflictos y sincronización offline.
- Alertas de margen, crédito y stock con acciones directas.
- Reportes comparativos y exportación uniforme.
- Práctica aislada para aprender sin tocar datos reales.

## Veredicto global

MiCatalogo tiene implementación y documentación para buena parte del mapa funcional observado de Puntto. Offline, trazabilidad, FIFO, idempotencia y permisos son capacidades propias que debemos verificar de extremo a extremo. No se ha demostrado que Puntto carezca de capacidades equivalentes ni que MiCatalogo sea globalmente superior. Hay seis áreas que requieren evidencia actualizada: **Inicio, Terminal, Pedidos, Inventario, Cotizaciones y Decants**.

La mayor oportunidad no es agregar decenas de menús: es hacer que las funciones existentes sean más rápidas, visibles, offline, auditables y coherentes entre web y Android.

## Corrección de fuente y primer bloque de implementación

El repositorio Android vigente es `C:/Users/waila/AndroidStudioProjects/micatalogowebApp`. La carpeta `BSPOS-MiCatalogo` es histórica y no debe usarse para determinar las brechas actuales. Al iniciar la implementación se comprobó que el repositorio vigente ya contiene el Inicio con periodos, guía de arranque y resumen remoto, además de un banner de sincronización. Las recomendaciones anteriores son criterios de revisión; no prueban la ausencia de esas funciones en la versión actual.

Primer bloque aplicado en el repositorio vigente:

- Detalle de sincronización desde el banner, disponible en el shell existente.
- Conteos de ventas, cobros, cambios de catálogo/inventario y actualizaciones pendientes, filtrados por tienda activa.
- Conteo de operaciones bloqueadas y acceso al flujo existente de revisión.
- Estado vacío expresado como «Sin operaciones pendientes». La cola vacía no prueba conectividad ni actualidad del catálogo.

La implementación conserva los mecanismos existentes de envío y revisión. Este bloque no constituye una publicación de APK ni una aprobación de paridad completa.
