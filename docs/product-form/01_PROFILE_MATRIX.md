# Matriz de perfiles y capacidades del formulario

Fuente inspeccionada: `config/business-types.php` (23 perfiles) y `ProductFormPresentationService`. La matriz distingue la configuración declarada de la funcionalidad real compartida: `product_fields` por sí solo no demuestra que el campo tenga edición/persistencia en Android.

| business_type | Etiqueta | Arquetipo | Modo predeterminado | Observación de formulario |
|---|---|---|---|---|
| `perfume_store` | Perfumes y fragancias | fragrance | `bottle` | Botella/ml disponibles por capacidad; `unit` sirve para accesorios; decants por permiso. |
| `clothing` | Ropa y moda | fashion | `unit` | Sin variantes/tallas/colores funcionales declaradas. |
| `footwear` | Calzado y tenis | footwear | `unit` | Cuenta la unidad comercial completa; “Par” solo si existe una taxonomía válida. |
| `beauty_cosmetics` | Belleza y cosméticos | beauty_retail | `unit` | Producto físico; marca y código son Web-only hoy. |
| `barbershop` | Barbería | service_retail | `service` | Servicios sin inventario físico y productos físicos separados. |
| `beauty_salon` | Salón de belleza y uñas | service_retail | `service` | Servicios sin inventario físico y productos físicos separados. |
| `electronics` | Tecnología y electrónica | electronics | `unit` | Seguimiento serial no soportado. |
| `appliance_store` | Electrodomésticos | appliances | `unit` | Sin botella/ml; garantía y serial no soportados. |
| `accessories_jewelry` | Accesorios y joyería | fashion | `unit` | Atributos de color/modelo no forman variantes persistentes. |
| `food_restaurant` | Restaurante y alimentos | food | `unit` | Inventario, recetas/peso no habilitados en el perfil. |
| `grocery` | Colmado y minimarket | grocery | `unit` | Código disponible en Web; inventario por peso no soportado. |
| `hardware` | Ferretería | hardware | `unit` | No se realizan conversiones caja/pieza/metro implícitas. |
| `auto_parts` | Repuestos y automotriz | automotive | `unit` | Modelo/compatibilidad no se anuncian como campos persistentes comunes. |
| `home_furniture` | Hogar y muebles | home | `unit` | Color no equivale a variante de inventario. |
| `pharmacy_personal_care` | Farmacia y cuidado personal | pharmacy | `unit` | No se anuncia control de lote/caducidad desde este formulario. |
| `professional_services` | Servicios profesionales | service_retail | `service` | Solo servicios; el backend desactiva productos/inventario físico. |
| `general_retail` | Tienda general | general_retail | `unit` | Campos comunes y capacidades del perfil. |
| `other` | Otro tipo de negocio | general_retail | `unit` | Default seguro de artículo completo. |
| `vapes` | Vapes | vape_retail | `unit` | Modelo no persistente como campo de producto compartido. |
| `pastry` | Repostería | food | `unit` | Inventario declarado no soportado por el perfil. |
| `tattoo_studio` | Tatuajes | service_retail | `service` | Servicio; productos físicos no habilitados por el perfil. |
| `caps_store` | Gorras | fashion | `unit` | Talla/color no son variantes de stock soportadas. |
| `boutique` | Boutique | fashion | `unit` | Talla/color no son variantes de stock soportadas. |

## Estado real de campos (cross-platform)

| Campo/función | Estado | Evidencia/alcance |
|---|---|---|
| Nombre, categoría, foto, precio, descripción | Compartido | Web formulario y `ProductInput` Android. |
| Existencias, mínimo, costo, precio mayorista | Condicional | Capacidad/permiso y flujo de inventario; no los muestra el contrato como universales. |
| Unidad inventario automática | Compartido | Contrato versionado y UUID determinista canónico por tienda. |
| Modalidad `unit` | Compartido | Alta artículo completo; conserva significado comercial del artículo, no convierte piezas. |
| `bottle`, `ml`, `decant` | Condicional | Perfil/capacidades perfume; origen/volumen permanecen sujetos a validación de dominio. |
| `service` | Compartido, condicionado | Sin stock físico del servicio; productos físicos siguen sujetos a permiso. |
| Combo | Parcial/condicionado | Se conservan componentes y validaciones existentes; el stock no se inventa como autónomo. |
| Marca y código de barras | Web-only para edición | Declarados en contrato `web_only_fields`; Android puede leer algunos datos recibidos, pero `ProductInput` no los actualiza. |
| SKU/código interno | Generado | No se promete como identificador editable compartido. |
| Modelo, talla, color, variantes, IMEI/serie, garantía, peso y caducidad | No soportado como formulario común | Configuración o conceptos existentes no equivalen a flujo completo y persistente en ambas plataformas. |

Las capacidades resueltas por tienda y rol siguen siendo autorización del servidor. Esta matriz no concede permisos.
