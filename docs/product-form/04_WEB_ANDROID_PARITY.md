# Paridad Web / Android

| Decisión | Web | Android | Resultado |
|---|---|---|---|
| Perfil y tipo de tienda | `BusinessPresentationService` incluye `presentation.product_form` | DTO → modelo de dominio `productForm`; fallback versionado | Mismo contrato aditivo. |
| Default/modalidad disponible | Lee el contrato, capacidad y permisos de formulario | Lee presentación; fallback si está offline | Mismos valores comerciales, sujetos a permisos efectivos. |
| Unidad nueva | Campo oculto `unit_uuid` toma UUID canónico; override avanzado | Selecciona/crea unidad estable y la guarda antes de encolar producto | No pide dos veces la misma unidad. |
| Edición histórica | Conserva `old('unit_uuid')`/UUID del producto | Conserva `current.unitId`, salvo selección explícita del modo soportado | No reasigna solo por cambio de título/precio. |
| Appliance | “Nuevo electrodoméstico”, unidad; no botella/ml | Título/modo y unidad desde contrato; sin botella/ml | QA de creación web y prueba instrumentada Android. |
| Perfumería | Default botella y volumen cuando el modo lo requiere | Default botella por perfil, opciones producto completo/botella/ml y flujo Decants | Pruebas de contrato y UI; E2E de apertura/venta no ejecutado en esta fase. |
| Servicios/combos/decants | Validación y control existentes | Conserva casos de uso y vínculos existentes | No se reescribió su ciclo financiero/stock. |
| Marca/modelo/barcode/sku | Marca/código disponibles en Web según capacidad; modelo no respaldado como campo común estable | `ProductInput` no edita marca/barcode; el código interno se genera | Contrato ahora declara marca/barcode `web_only`, SKU `generated`, modelo `unsupported`. Brecha explícita. |

## Paridad que no se debe inferir

La igualdad del default y la semántica de unidad no significa que todos los campos se editen idénticamente. Marca y barcode aún requieren trabajo Android para paridad completa. Variantes talla/color, modelo persistente, serial/IMEI y garantía no se declaran soportados. El backend sigue siendo autoridad de capacidad, ACL y tenant.
