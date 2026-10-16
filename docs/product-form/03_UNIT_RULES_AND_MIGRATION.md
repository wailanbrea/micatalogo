# Reglas de unidad, legacy y migraciones

## Decisión

El comerciante selecciona una modalidad comercial una vez. El formulario calcula y muestra “Inventario contado en …”; la unidad queda asignada a su UUID canónico. El selector técnico se conserva solo en opciones avanzadas. El contrato no autoriza cambios de stock ni de unidad en registros existentes.

| Modo | Unidad por defecto | Invariante |
|---|---|---|
| `unit` | `unit` | Una unidad equivale al artículo comercial completo (ej. par si ese es el producto). |
| `bottle` | `bottle` | Botella sellada y volumen válidos; conserva lógica de botellas/Decants. |
| `ml` | `ml` | Volumen medido; no se interpreta como botella sellada. |
| `service` | `service` | Sin inventario físico del servicio. |
| `decant` | `decant` | Presentación ligada al producto fuente; no stock paralelo ficticio. |
| combo | `unit` | Los componentes existentes determinan la disponibilidad según el dominio. |

## Identidad y alcance

- Web genera los identificadores con `ShopRecordSyncService::localId($shop, 'unit', $key)`.
- Android los resuelve con la misma identidad estable `micatalogo:unit:<shopId>:<key>` (UUID name-based), persiste la unidad activa y encola `unit_upsert` antes del producto remoto.
- Una unidad custom existente se preserva al editar; desactivación/alcance sigue sujeto al servidor y a taxonomía.
- Ninguna comparación por etiqueta localizada (`Unidad`, `Unit`, `Par`) reemplaza la identidad UUID.

## Migración

No se añadió migración: tablas/UUID de unidad ya existen y el cambio afecta presentación/asignación en altas nuevas. No hay backfill, alteración de productos históricos ni cambio de `product_unit`, ledger, FIFO o stock. Producción no fue consultada ni modificada. La ruta de override avanzado mantiene validaciones actuales.

## Riesgo conocido

La edición cruzada que cambia `sale_unit` con stock/historial requiere una validación de dominio explícita para prohibir conversión silenciosa. Esta entrega no debe interpretarse como permiso de migrar una presentación ya operativa; revisar y bloquear esos casos antes de release si el formulario actual permite tal transición.
