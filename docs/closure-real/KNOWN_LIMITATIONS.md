# Limitaciones conocidas

- La suite backend automatizada no sustituye pruebas de navegador autenticado, operación real en emulador ni jornadas cruzadas entre clientes.
- No se dispone de evidencia actual confiable de una API/base compartida y aislada para crear ventas, movimientos, abonos o mutaciones de taxonomía; no se escribieron datos.
- La matriz NAV histórica conserva 59 casos bloqueados; los resultados de otro corte/otro SHA no se trasladan automáticamente.
- La ejecución instrumentada del perfil UI que estaba corriendo se inició antes de incorporar la nueva prueba para categorías vacías; debe repetirse.
- `brand`, `barcode` y `model` no están en paridad móvil completa.
- Versiones públicas y firmas no fueron verificadas ni actualizadas.
