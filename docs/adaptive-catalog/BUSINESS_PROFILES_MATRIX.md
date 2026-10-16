# Matriz de Perfiles de Negocio y Capacidades (23 Perfiles)

Inventario completo de los 23 perfiles configurados en `config/business-types.php`, su arquetipo de presentación, modalidad predeterminada, unidades y particularidades del formulario adaptativo.

| # | `business_type` | Etiqueta | Arquetipo | Modo por defecto | Unidades autorizadas | Inventario físico | Particularidades |
|---|---|---|---|---|---|---|---|
| 1 | `perfume_store` | Perfumes y fragancias | `fragrance` | `bottle` | `unit`, `bottle`, `ml`, `decant` | Sí (unidades y ml) | Botellas selladas, volumen ml, soporte para preparación y venta de Decants. |
| 2 | `clothing` | Ropa y moda | `fashion` | `unit` | `unit`, `service` | Sí | Artículos completos (prendas). Sin variantes complejas ficticias. |
| 3 | `footwear` | Calzado y tenis | `footwear` | `unit` | `unit`, `service` | Sí | Venta como artículo completo (par comercial 1:1). Sin conversión fraccionaria a zapato suelto. |
| 4 | `beauty_cosmetics` | Belleza y cosméticos | `beauty_retail` | `unit` | `unit`, `service` | Sí | Productos físicos y servicios de aplicación/maquillaje. |
| 5 | `barbershop` | Barbería | `service_retail` | `service` | `service`, `unit` | No en servicios / Sí en reventa | Servicios principales sin stock; venta de productos capilares con stock independiente. |
| 6 | `beauty_salon` | Salón de belleza y uñas | `service_retail` | `service` | `service`, `unit` | No en servicios / Sí en reventa | Servicios de estética sin existencias; productos de reventa controlan stock. |
| 7 | `electronics` | Tecnología y electrónica | `electronics` | `unit` | `unit`, `service` | Sí | Marca, modelo y SKU. Sin promesa de tracking serial/IMEI no implementado. |
| 8 | `appliance_store` | Electrodomésticos | `appliances` | `unit` | `unit`, `service` | Sí | Neveras, estufas, televisores. Opciones botella/ml completamente ocultas. |
| 9 | `accessories_jewelry` | Accesorios y joyería | `fashion` | `unit` | `unit`, `service` | Sí | Piezas completas; sin variantes de metal/quilates que alteren el ledger. |
| 10 | `food_restaurant` | Restaurante y alimentos | `food` | `unit` | `unit` | No (`inventory=unsupported`) | Menús preparados; respeta que inventario de materia prima no está soportado. |
| 11 | `grocery` | Colmado y minimarket | `grocery` | `unit` | `unit` | Sí | Código de barras, existencias y precios al por mayor. Sin venta a granel simulada. |
| 12 | `hardware` | Ferretería | `hardware` | `unit` | `unit`, `service` | Sí | Herramientas y materiales. Asignación 1:1; unidades especiales (caja, metro) en avanzadas. |
| 13 | `auto_parts` | Repuestos y automotriz | `automotive` | `unit` | `unit`, `service` | Sí | Repuestos por pieza; servicios de mecánica e instalación sin stock. |
| 14 | `home_furniture` | Hogar y muebles | `home` | `unit` | `unit`, `service` | Sí | Muebles por pieza; entregas y armado como servicio sin inventario. |
| 15 | `pharmacy_personal_care` | Farmacia y cuidado personal | `pharmacy` | `unit` | `unit` | Sí | Productos empacados. Sin gestión de lotes o caducidades no soportada. |
| 16 | `professional_services` | Servicios profesionales | `service_retail` | `service` | `service` | No | Asesorías, consultas. Sin movimientos de inventario ni existencias físicas. |
| 17 | `general_retail` | Tienda general | `general_retail` | `unit` | `unit`, `service` | Sí | Perfil base seguro con campos y módulos estándar. |
| 18 | `other` | Otro tipo de negocio | `general_retail` | `unit` | `unit`, `service` | Sí | Fallback seguro para giros comerciales no clasificados. |
| 19 | `vapes` | Vapes | `vape_retail` | `unit` | `unit` | Sí | Dispositivos y esencias selladas como unidades completas. |
| 20 | `pastry` | Repostería | `food` | `unit` | `unit` | No (`inventory=unsupported`) | Productos horneados y tortas según disponibilidad del día. |
| 21 | `tattoo_studio` | Tatuajes | `service_retail` | `service` | `service` | No | Sesiones y diseños de tatuaje sin stock físico. |
| 22 | `caps_store` | Gorras | `fashion` | `unit` | `unit` | Sí | Gorras y accesorios de moda en unidades completas. |
| 23 | `boutique` | Boutique | `fashion` | `unit` | `unit` | Sí | Ropa y accesorios seleccionados sin división de tallas complejas. |

## Clasificación de Arquetipos
- `appliances`: Formulario de electrodomésticos; sin campos de perfumería ni volumen.
- `fragrance`: Formulario con soporte nativo de botella, volumen ml y preparación de Decants.
- `service_retail`: Formulario centrado en servicios sin inventario, con opción de habilitar productos de reventa si la tienda lo permite.
- `fashion` / `footwear`: Artículos comerciales unitarios (prendas, pares de calzado 1:1).
- `food`: Artículos preparados sin control de inventario de insumos.
- `grocery` / `hardware` / `electronics` / `general_retail`: Venta de artículos físicos con control de existencias, costo y alerta de stock mínimo.
