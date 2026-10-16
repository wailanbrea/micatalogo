# Hallazgos, correcciones y límites

| Hallazgo | Reproducción/evidencia | Corrección | Estado |
|---|---|---|---|
| El formulario de alta podía pedir modalidad y unidad por separado, con decisiones redundantes | Lectura de Web Blade y Compose `ProductForm`; la unidad de inventario se seleccionaba aparte | Contrato aditivo, UUID default por modalidad, etiqueta “Inventario contado en”; unidad avanzada conserva escape legítimo | Corregido y probado (Web + Android UI). |
| Electrodomésticos recibía opciones de botella/ml irrelevantes | Perfil appliance/contract test; navegador local | Perfil `appliances` default `unit`, botella/ml fuera de opciones y título comercial | Corregido; test + alta de fixture local. |
| `product_fields` podía interpretarse como prueba de campos Android persistentes | DTO `ProductInput` no admite edición de brand/barcode; modelo no ofrece campo estable de model | `supported_fields` se restringe a intersección; marca/barcode se identifican como `web_only`; SKU `generated`; model/serial/garantía unsupported | Corregido en contrato y prueba añadida. |
| Posible proyección incompleta de `/api/v1/shops` citada por el prompt | Test de dos tiendas y tenant tercero, incluyendo tipo, versión y override | No se modificó query: regresión ejecutada conserva business_type, profile_version, overrides y excluye tenant ajeno | No reproducido en HEAD actual. |

## Pendientes/riesgos

- No se ejecutaron seis E2E comerciales. No declarar cierre integral de seguridad, FIFO, Decants, devoluciones, permisos ni operación offline.
- Android carece de edición de marca y barcode en `ProductInput`; contrato lo expone como Web-only hasta implementar persistencia local/outbox de forma segura.
- `model`, tamaños/colores/variantes, números de serie, garantía, peso/recetas y caducidad no son capacidades completas del formulario compartido.
- La selección de `Par` solo puede hacerse si existe unidad válida; no se agregó un motor de conversiones.
- QA de navegador local comprobó alta de un artículo appliance, no toda la matriz de tamaños/teclado/accesibilidad ni modos de edición históricos.
- El screenshot del emulador/browser no quedó guardado como artefacto. No afirmar evidencia visual archivada.
