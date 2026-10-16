# Contrato de Presentación y Adaptabilidad del Formulario de Catálogo

Especificación técnica de la clave `presentation.product_form` retornada en el endpoint `/api/v1/shops` y en las rutas de perfil Web y Android.

## Estructura del Contrato (`schema_version: 1`)

```json
{
  "schema_version": 1,
  "default_sale_unit": "unit",
  "available_sale_units": ["unit", "service"],
  "sale_unit_labels": {
    "unit": "Electrodoméstico completo",
    "service": "Servicio (sin inventario físico)",
    "bottle": "Botella completa",
    "ml": "Por mililitro (ml)"
  },
  "default_inventory_unit_key": "unit",
  "sale_unit_to_inventory_unit": {
    "unit": "unit",
    "service": "service",
    "bottle": "bottle",
    "ml": "ml",
    "decant": "decant"
  },
  "inventory_unit_labels": {
    "unit": "Unidades",
    "bottle": "Botellas",
    "ml": "Mililitros (ml)",
    "decant": "Decants",
    "service": "Servicios"
  },
  "inventory_unit_uuids": {
    "unit": "<UUID v3/v5 canónico de la tienda>",
    "service": "<UUID v3/v5 canónico de la tienda>",
    "bottle": "<UUID v3/v5 canónico de la tienda>",
    "ml": "<UUID v3/v5 canónico de la tienda>"
  },
  "supported_fields": [
    "name",
    "category",
    "photo",
    "price",
    "description",
    "stock",
    "low_stock_threshold"
  ],
  "web_only_fields": ["brand", "barcode"],
  "feature_states": {
    "brand": "web_only",
    "barcode": "web_only",
    "sku": "generated",
    "cost_price": "permission_gated",
    "wholesale_price": "permission_gated",
    "model": "unsupported",
    "serial_tracking": "unsupported"
  },
  "allow_advanced_unit_override": true,
  "profile_source": "server"
}
```

## Reglas de Asignación y Fallback
1. **Resolución Determinista**: La unidad de inventario se mapea mediante `sale_unit_to_inventory_unit[saleUnit]`. Si no existe o se está offline, el cliente usa `UUID.nameUUIDFromBytes("micatalogo:unit:$shopId:$inventoryUnitKey".toByteArray())`.
2. **Alta vs Edición**:
   - En altas nuevas, la unidad de inventario se autoasigna al crear el producto, garantizando que la entidad `unit` exista antes de persistir o encolar la mutación.
   - En edición de productos existentes, `current.unitId` y `current.remoteSaleUnit` se preservan estrictamente, impidiendo que una actualización de precio o nombre modifique la unidad histórica o el stock FIFO.
3. **Restricción de Modalidades**:
   - Tiendas no-fragancia (`appliances`, `footwear`, `fashion`, `grocery`, `hardware`) no ofrecen opciones de `bottle` ni `ml`.
   - Tiendas de servicios (`barbershop`, `professional_services`) establecen `default_sale_unit = "service"` y ocultan campos de inventario físico para artículos de tipo servicio.
4. **Campos Web-Only y No Soportados**:
   - `brand` y `barcode` se documentan como `web_only` hasta que Android implemente soporte completo de persistencia en `ProductInput`.
   - `model`, variantes complejas y `serial_tracking` se marcan explícitamente como `unsupported` para evitar promesas contractuales falsas.
