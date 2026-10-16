# Recorridos E2E de Calidad (Journeys A .. H)

Evidencia y descripción de los 8 recorridos integrales del sistema.

---

### Journey A: Tienda de Electrodomésticos — Creación y Venta POS
- **Actor**: Owner de tienda `appliance_store` (`Electrodomésticos QA`).
- **Flujo**:
  1. Alta de producto "Nevera Samsung Inverter" desde el formulario adaptativo.
  2. La interfaz omite los campos de botella, volumen y preparación de Decants; selecciona automáticamente la unidad comercial ("Unidad") con su UUID determinista.
  3. Registro de 5 unidades en existencias a RD$ 35,000.00 de costo y RD$ 48,000.00 de venta.
  4. Venta en POS móvil de 1 unidad.
- **Resultado verificado**:
  - Inventario remanente: exactamente 4 unidades.
  - Costo de venta (COGS) imputado exactamente por RD$ 35,000.00 según capa FIFO.
  - Ganancia bruta calculada en RD$ 13,000.00 en reportes financieros.

---

### Journey B: Perfumería — Apertura de Botella y Venta de Decants
- **Actor**: Administrador de perfumería (`perfume_store`).
- **Flujo**:
  1. Alta de "Hugo Boss Bottled 100ml" como botella sellada con stock inicial de 20 botellas.
  2. Operación de apertura de 1 botella para Decants: se decrementa 1 botella sellada (quedan 19 disponibles para venta directa) y se acreditan 100 ml en el reservorio de Decants.
  3. No se genera costo anticipado ni venta fantasma por la apertura.
  4. Venta en POS de un Decant de 10 ml a RD$ 600.00.
- **Resultado verificado**:
  - Existencias: 19 botellas completas y 90 ml disponibles para Decants.
  - Imputación de COGS proporcional exclusivamente sobre los 10 ml vendidos.

---

### Journey C: Calzado — Unidad Comercial Par 1:1
- **Actor**: Vendedor de tienda de calzado (`footwear`).
- **Flujo**:
  1. Alta de producto "Tenis Running Pro" en modalidad comercial ("Par").
  2. Se verifica que el sistema no aplica conversiones matemáticas 2:1 ficticias ni permite la división a zapatos sueltos.
  3. Venta de 1 par en POS.
- **Resultado verificado**:
  - Saldo de inventario reducido exactamente en 1 unidad comercial (par).
  - Integridad de stock y costos preservada 1:1.

---

### Journey D: Usuario Services-Only — Aislamiento y Seguridad
- **Actor**: Colaborador con rol limitado exclusivamente a servicios (`services-only`).
- **Flujo**:
  1. Inicio de sesión y navegación al menú de Catálogo.
  2. Acceso a "Categorías y unidades" a través del Drawer y la ruta de navegación autorizada (`Screen.Categories.route`).
  3. Creación de una categoría y un servicio "Instalación de Electrodoméstico".
  4. Intento de creación o edición de productos físicos con existencias.
- **Resultado verificado**:
  - El usuario puede gestionar sus servicios y categorías de servicios.
  - Las operaciones que intentan escribir sobre inventario físico son rechazadas por el backend con HTTP 403.
  - La navegación no crashea ni bloquea indebidamente el acceso a categorías.

---

### Journey E: Productos Antiguos con Unidad Custom y Sincronización Offline
- **Actor**: Usuario operando en modo sin conexión con productos históricos.
- **Flujo**:
  1. Apertura de formulario de edición de un producto creado previamente con unidad personalizada.
  2. Actualización de nombre y precio en modo avión.
  3. Se confirma que `unitId` y la unidad de taxonomía se mantienen intactos sin sobreescritura automática.
  4. Reconexión de red y procesamiento de la cola outbox.
- **Resultado verificado**:
  - La operación se aplica una sola vez (`client_operation_uuid`).
  - No se producen errores 409 ni duplicación de registros de unidades.

---

### Journey F: Aislamiento Multi-Tenant entre Tienda A y Tienda B
- **Actor**: Usuario con acceso a múltiples comercios con giros distintos (`appliance_store` vs `perfume_store`).
- **Flujo**:
  1. Consulta del listado de tiendas en `/api/v1/shops`.
  2. Conmutación activa entre Tienda A y Tienda B.
- **Resultado verificado**:
  - Tienda A expone arquetipo `appliances`, campos de marca/modelo y oculta Decants.
  - Tienda B expone arquetipo `fragrance`, campos de volumen y preparación de Decants.
  - Los borradores locales, outbox, categorías y productos no se mezclan entre tiendas.

---

### Journey G: Navegación y Operaciones Financieras Previas
- **Actor**: Vendedor y Cajero con permisos de ventas y caja.
- **Flujo**:
  1. Verificación de permisos y visibilidad en pantalla de Resumen, POS, Caja, Cobros y Reportes.
  2. Comprobación de que la regla de "Cero distorsión en diseño de interfaces" se cumple: montos íntegros sin saltos de línea ni cifras partidas.
- **Resultado verificado**:
  - Resumen, inventario y cobros alineados matemáticamente.
  - Visibilidad de menús respeta `enabled_menu_keys` de la tienda y permisos asignados al vendedor.

---

### Journey H: Combos Multiproducto Web y Android
- **Actor**: Administrador creando paquetes comerciales.
- **Flujo**:
  1. Creación de combo "Kit Cocina" compuesto por 1 Horno Microondas y 1 Licuadora.
  2. Venta del combo en POS móvil.
- **Resultado verificado**:
  - Se valida stock disponible de ambos componentes.
  - La venta del combo decrementa automáticamente las existencias de cada componente individual.
  - Devolución y anulación repone los inventarios correctamente.
