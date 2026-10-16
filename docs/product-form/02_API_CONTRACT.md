# Contrato aditivo de presentación del formulario

La respuesta existente de perfil/presentación agrega `presentation.product_form`; no reemplaza `product_fields`, `capabilities`, `business_type` ni la respuesta anterior. `schema_version` actual: `1`.

```json
{
  "schema_version": 1,
  "default_sale_unit": "unit",
  "available_sale_units": ["unit", "service"],
  "sale_unit_labels": {"unit": "Electrodoméstico completo", "service": "Servicio (sin inventario físico)"},
  "default_inventory_unit_key": "unit",
  "sale_unit_to_inventory_unit": {"unit": "unit", "service": "service"},
  "inventory_unit_labels": {"unit": "Unidades", "bottle": "Botellas", "ml": "Mililitros (ml)", "decant": "Decants", "service": "Servicios"},
  "inventory_unit_uuids": {"unit": "<UUID canónico de la tienda>", "service": "<UUID canónico de la tienda>"},
  "supported_fields": ["name", "category", "photo", "price", "description", "stock", "low_stock_threshold"],
  "web_only_fields": ["brand", "barcode"],
  "feature_states": {"brand": "web_only", "barcode": "web_only", "sku": "generated", "cost_price": "permission_gated", "model": "unsupported", "serial_tracking": "unsupported"},
  "allow_advanced_unit_override": true,
  "profile_source": "server"
}
```

Las listas exactas cambian por capacidades/perfil: costo y mayorista no aparecen dentro de `supported_fields` porque son sensibles al rol; sus estados solo describen que la UI debe respetar los permisos efectivos. No usar el manifiesto como autorización; `ProductRequest`, políticas, `CatalogTaxonomyAccessService` y controles de tenant siguen validando cada escritura.

## Ejemplos

- Electrodomésticos: `default_sale_unit=unit`; `available_sale_units` no contiene `bottle` ni `ml`; unidad predeterminada `unit`. `model` y garantía no se anuncian como funcionales.
- Perfumería: `default_sale_unit=bottle`; capacidades pueden exponer `unit`, `bottle`, `ml` y `decant`. `bottle` resuelve a unidad de inventario `bottle`; el volumen y la apertura siguen validándose por el dominio.
- Calzado: `default_sale_unit=unit`. El cliente no inventa conversión a pares. La unidad “Par” solo se elige si existe y está autorizada para esa tienda.
- Barbería/servicios: `default_sale_unit=service` por arquetipo; el servicio no crea existencias físicas. El mismo perfil puede ofrecer `unit` para producto físico si las capacidades lo permiten.

## Compatibilidad

- Las claves son aditivas y opcionales para APK antiguas; los consumidores nuevos usan fallback local cuando no llega el bloque.
- No se endureció el payload legacy ni se cambió el formato de operaciones existentes.
- `profile_source=server` indica el origen de defaults de presentación, no que los permisos se hayan delegado al cliente.
- Los UUID se derivan mediante `ShopRecordSyncService::localId($shop, 'unit', $key)`, la identidad existente compartida con taxonomía/sincronización; Android usa el mismo algoritmo determinista solo para el fallback del tenant. La operación persiste/resuelve la unidad antes de encolar el producto.
