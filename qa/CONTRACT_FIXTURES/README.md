# Fixtures contractuales de QA

Estos fixtures son sintéticos, anonimizados y solo describen contratos que se
ejecutan contra SQLite temporal o `micatalogo_qa_20261008`. No contienen tokens,
teléfonos, correos, URLs de producción ni identificadores de usuarios reales.

## Archivos

- `shop_roles.json`: Tienda A/B, planes, tipos de negocio y actores de la matriz
  ACL. El `id` se sustituye por un UUID nuevo en cada test.
- `sale_contract.json`: venta mixta con costo FIFO, crédito y gasto; los importes
  están expresados en centavos y sirven para comprobar caja, cartera y P&L.
- `import_headers.csv`: encabezados sintéticos con fila de datos ubicada después
  de ocho líneas de preámbulo, usado por el contrato del importador adaptativo.

## Reglas de uso

1. Cada consumidor debe generar UUIDs nuevos y ejecutar dentro de una base QA.
2. No convertir estos archivos en seeders de producción ni invocarlos desde un
   deploy.
3. Antes y después de cada corrida se comparan stock, lotes, facturas, pagos,
   movimientos de caja, cartera y tienda; un replay con la misma UUID debe ser
   idempotente.
4. La operación de devolución pagada y el costo aterrizado internacional no se
   fijan aquí: permanecen `REQUIERE_DECISION` en el informe QA.

