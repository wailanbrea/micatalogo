# Mejoras Android verificadas en QA — 19 de octubre de 2026

Repositorio vigente: `C:/Users/waila/AndroidStudioProjects/micatalogowebApp`.
Destino: `emulator-5554`, variante debug `com.bsolutions.micatalogo.offlinecheck.emulatorqa`, versión 1.0.122/código 123. Backend local de pruebas en 127.0.0.1:18765 y SQLite QA separada.

## Cambios instalados

- Sincronización: detalle de pendientes por tipo y tienda, con acceso a revisión de bloqueos.
- Inicio: acción para actualizar el resumen cuando falla la consulta remota.
- Inventario: filtros rápidos con conteos, indicador de filtros activos y limpieza de búsqueda/filtros. Nivel bajo y agotados no se combinan.
- Terminal móvil: recuperación de búsquedas vacías y acceso a precios de detalle cuando Mayoreo no da resultados.
- Pedidos: mensaje específico cuando un filtro no tiene resultados y acción para restaurar la lista completa.
- Cotizaciones: búsqueda de documentos independiente de la búsqueda de productos, filtros por los estados presentes en los documentos cargados, conteos y acción para limpiar. Error de consulta con acción de actualización.

## Verificación ejecutada

Compilación debug y comprobación de formato correctas. Certificado debug compatible y paquete verificados antes de instalar con `adb install -r`. Sin publicación ni instalación en teléfono físico.

En la cuenta QA Tienda A:

1. El detalle de sincronización muestra ventas, cobros, cambios y actualizaciones pendientes.
2. Inventario: Agotados devuelve 0 de 1; limpiar restaura QA Producto A con 20 unidades.
3. Terminal: una búsqueda inexistente muestra la acción de recuperación; limpiar restaura el producto y sus 20 unidades.
4. Pedidos: Confirmados no tiene resultados y muestra el mensaje de filtros; limpiar recupera QA-PEDIDO-A.
5. Cotizaciones: un texto en Guardadas no afecta el producto disponible en Nueva. Al volver, el texto se conserva; limpiar restaura la búsqueda vacía.

Estas comprobaciones no confirmaron ventas ni pedidos.

## Verificación complementaria al cierre del objetivo

- Suite unitaria Android al cierre: 167/167; suite UI local aislada: `qa/local-ui-final-20261019.json`, 116/116.
- Recorridos LIVE de clientes, pedidos, crédito, cobros y cambio de tienda: `qa/journeys-rerun-20261019.json`, 4/4.
- Roles, revocación durante sesión, acceso de solo fotos y navegación por negocio: `qa/navigation-final-20261019.json`, 4/4.
- Reintentos, OCR, Decants y conciliación se verificaron por separado; referencias en `docs/OBJETIVO_MEJORAS_PUNTTO.md`.

La matriz confirma los bloques priorizados, no paridad funcional completa ni superioridad global frente a Puntto. No incluye dispositivo físico, entrega externa de notificaciones ni backend de producción.
