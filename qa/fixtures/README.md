# Fixtures QA

Este directorio queda reservado para fixtures sintéticos y aislados de QA.

En este corte no se generaron fixtures contra VPS ni producción. La creación de
datos de prueba queda bloqueada hasta disponer de un entorno aislado para MariaDB,
archivos, colas, R2, WhatsApp y sincronización offline. Los datos reales de
usuarios, tiendas, productos, ventas, facturas y planes no se usan como fixtures.

En este corte se habilitó temporalmente un fixture local para el importador adaptativo
y la venta POS offline: base SQLite temporal, servidor Laravel local en
`127.0.0.1:8893` y reverse ADB temporal. El fixture usa una tienda y usuario
sintéticos, el archivo XLSX de `app/src/androidTest/assets/`, ventas de contado/crédito,
un cliente y un abono, además de tokens creados únicamente en la base QA. La prueba
POS validó tres ventas ordenadas, deuda, pago, ACK remoto y replay sin duplicar; el
servidor y el reverse se detuvieron al terminar.
No se usó el VPS ni la base de producción.

Cada fixture deberá:

- tener identificadores y correos sintéticos;
- ser idempotente y reversible;
- declarar el rol, tienda y plan involucrados;
- incluir el estado inicial y las invariantes esperadas;
- poder eliminarse solo dentro del entorno QA aislado.
