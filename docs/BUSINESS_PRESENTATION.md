# Presentación por tipo de negocio

`BusinessPresentationService` construye el contrato de UX que comparten el panel web y BSPOS. El tipo de negocio se convierte en un arquetipo (`fragrance`, `fashion`, `food`, etc.) y se combina con las capacidades efectivas de la tienda.

La presentación solo cambia etiquetas, widgets, placeholders y campos visibles. No concede permisos: el acceso continúa dependiendo de `BusinessProfileService`, `SellerMenuService`, las políticas y los guards de las rutas.

El bloque `presentation` se entrega dentro de `/api/v1/shops` y del snapshot `/api/v1/shops/{shop}/catalog`. Los clientes deben aceptar su ausencia para mantener compatibilidad con servidores anteriores y filtrar acciones usando sus permisos locales.
