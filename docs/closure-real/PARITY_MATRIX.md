# Matriz de paridad Web / Android

| Área | Estado observado | Validación en este corte |
|---|---|---|
| Arquetipo / formulario adaptativo | Hay contrato de presentación consumido en ambas plataformas | Backend Pest completo; UI Android en curso. Paridad real E2E no certificada. |
| Unidades de venta e inventario | Web valida y Android deriva UUID canónico/contrato | Web bloquea cambio semántico con historia; Android usa contrato, falta E2E cruzado con ACK. |
| Categorías de producto/servicio | Android consume snapshot y muestra scopes en módulos existentes | Nueva regresión para snapshot vacío; falta confirmación instrumentada final y sincronización tras proceso muerto. |
| `brand`, `barcode` | `web_only` según contrato/documentación previa | No es paridad; no declarar aprobado. |
| `model` | `unsupported` | Se mantiene fuera de formulario móvil hasta ruta de persistencia completa. |
| FIFO, Decants, combos, ventas y finanzas | Cobertura por distintos tests unitarios/feature; flujos de sistema separados | Sin journeys A–H compartidos, no se certifica consistencia integral entre interfaces. |
