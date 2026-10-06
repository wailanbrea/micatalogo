# Importador adaptativo de inventario

Estado de código: 2026-10-05. Web/API Laravel y Android MiCatalogo 1.0.24 (25).
No requiere migraciones de la base remota, seeders ni cambios de cuentas existentes.

## Auditoría y arquitectura

El lector anterior usaba la primera fila, `sheet1.xml`, ignoraba estilos y relaciones del libro,
detectaba el separador con una sola línea y redondeaba stock. Android reenviaba las filas y
comparaba todas las filas válidas con el cupo; no consumía la sesión autoritativa del backend.

Se conserva `InventoryImportService`, sus categorías, atributos, SKU, duplicados y cupos.
`WorkbookReader` sustituye únicamente el lector; `ColumnDetector` concentra aliases y heurísticas.
Web y Android usan la misma detección Laravel, sin IA externa ni transmisión de archivos a terceros.

Flujo: archivo → lectura segura → selección de hoja/fila → mapping revisable → validación
por fila → `InventoryImportSession` → confirmación transaccional → sincronización del catálogo Android.

PhpSpreadsheet 5.10.0 fue elegido frente al ZIP/XML manual: maneja relaciones, shared strings,
rich text, formatos numéricos y XLS BIFF. No se añade Laravel Excel. `readDataOnly(false)`
conserva máscaras de ceros. No se cargan imágenes ni se evalúan fórmulas: solo valores cacheados.
Dependencia mínima permitida 5.8.1; auditoría Composer sin avisos conocidos en 5.10.0.
Referencia: https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-files/

## Detección y mapping

- Escanea las primeras 50 filas; configurable en `config/inventory-import.php`.
- Puntúa aliases distintos, nombre y precio/costo/cantidad; descarta títulos de una celda y
  penaliza encabezados repetidos. Hoja con mayor confianza y datos; empate por orden del libro.
- Confianza inferior a 0.60 requiere seleccionar fila. No crea una sesión confirmable engañosa.
- NFKC, BOM real o mojibake, ASCII, minúsculas y puntuación neutralizada solo para comparación.
  Los encabezados originales permanecen intactos para mostrar en Web/Android.
- Aliases españoles/ingleses y abreviaturas están en `ColumnDetector::ALIASES` (fuente única).
- `Código del producto` y equivalentes: muestra de hasta 50 filas; ≥80% dígitos de longitud
  8/12/13/14 lo identifica como barcode; alfanuméricos como SKU. SKU explícito sigue siendo SKU.
- Mapping incluye origen, encabezado original, confianza, razón y tres ejemplos.
  Encabezado reconocido 0.95; heurística EAN/UPC 0.90; formato guardado 0.70; selección manual 1.00.
  Alta ≥0.85, media 0.60–0.84, baja <0.60. No asigna campos críticos con confianza baja.
- Costo/Precio inventario, subtotal, valor y totales no alimentan costos/precios unitarios.
- Desconocidas se ignoran. `attribute_columns[]` permite opt-in explícito; una columna no
  puede alimentar un campo y un atributo ni dos campos distintos.
- Mapping guardado es una pista para campos sin detectar, nunca pisa detección válida nueva.
  Se guarda solo una selección manual que pudo generar sesión; no impone formatos anteriores.

## Formatos, valores y límites

CSV/TXT: coma, punto y coma, tabulación o pipe; selección por consistencia de hasta 100 registros
con comillas/multilínea. UTF-8/BOM y conversión Windows-1252 (compatible con caracteres comunes
ISO-8859-1); se avisa de conversión y mojibake ambiguo. Registros CSV numerados por registro lógico.
XLSX y XLS BIFF: hojas reales por el lector de biblioteca. No ODS/PDF/imágenes/Word/HTML disfrazado.

Dinero: moneda y separadores latinos/internacionales → cadena decimal con dos cifras, aritmética
decimal BCMath y redondeo half-up. No float final. Precios/costos negativos o no válidos se rechazan.
Stock: enteros o fracciones solo cero (`10.0`, `10,0`); `10.5` inválido, nunca redondeado.
EAN/UPC/SKU siguen como strings; máscara `000…` recupera ceros de celdas numéricas. Sin máscara
no puede reconstruirse información perdida: se advierte. Más de 15 dígitos en Excel numérico
puede haber perdido precisión; usar texto. Los valores calculados sin caché no se inventan.

Límites: 10 MB, 5000 productos, 5050 filas físicas (incluye encabezados), 100 columnas, 20 hojas,
100000 celdas dimensionadas en el libro, 16000 caracteres/celda. ZIP: 2000 entradas,
64 MB descomprimidos totales, 24 MB/entrada, ratio máximo 1000. Nunca se extrae a rutas del usuario.

## Autoridad, seguridad y concurrencia

La sesión guarda filas validadas, mapping y categorías faltantes durante dos horas.
La confirmación comprueba tienda, usuario creador, expiración y estado; bloquea tienda y sesión
en transacción y vuelve a comprobar idempotencia bajo lock. Replays devuelven el mismo resumen.
Barcode → SKU → posible duplicado por nombre. Las colisiones EAN nunca crean otro producto
en la misma tienda. Cuota solo para `create`; actualización/omisión no consume nuevos cupos.

`upload_token` UUID cachea el libro parseado dos horas para cambiar mapping/hoja/fila sin re-subir.
El caché verifica tienda y creador; su vencimiento obliga a subir de nuevo. No es un backup.
El caché debe ser persistente entre peticiones Web (file/Redis/database configurado); `array`
solo sirve en pruebas. No se crea ninguna tabla adicional.

Logs: tienda, usuario, sesión, nombre base de archivo, formato, hoja, fila, mapping, confianza,
conteos y duración. Nunca volcar filas, credenciales o archivo completo.
El enriquecimiento opcional de imágenes no convierte una importación ya confirmada en HTTP 500.

## Contrato y UX

Se conservan los campos anteriores. Nuevos opcionales: `file`, `sheet`, `sheets`, `header_row`,
`header_candidates`, `needs_header_selection`, `original_headers`, `mapping_confidence`,
`ignored_columns`, `warnings`, `sample_rows`, `counts`, `upload_token`.
Ver `API_CONTRACT.md`. Clientes 1.0.23 siguen pudiendo consumir el contrato anterior;
1.0.24 confirma por `session_id`, jamás reenviando filas. No se eleva el mínimo de actualización.

Web/Android muestran hoja/fila, selección manual, encabezados originales, razones/confianza,
ejemplos, ignoradas con opt-in de atributos y conteos nuevos/existentes/duplicados.
La vista previa se revisa antes de confirmar y el resultado informa creados/actualizados/omitidos.

## Pruebas reproducibles

`tests/Support/InventoryWorkbookFixture.php` genera el libro anónimo: hoja de instrucciones,
hoja Existencias vacía hasta fila 8, EAN `0850050062035`, UPC `812256024194`, SKU `000045`.
Se prueba XLSX y XLS, datos exactos, manual/saved mapping, delimitadores, límites, tenants,
idempotencia, expiración, cupos y benchmark de 1500 productos. SQLite en memoria obligatorio.

E2E Android: `InventoryImportLiveIntegrationTest`, asset `inventory-import-row8.xlsx`, servidor
local `scripts/testing/inventory-import-e2e.php`. El script solo admite una nueva SQLite
`inventory-import-e2e-<uuid>.sqlite` en TEMP, APP_ENV=testing y configuración explícita aislada.
Servidor 127.0.0.1:8893, `adb reverse`, token fijo únicamente de ese fixture; nunca producción.
Prueba subida multipart real Retrofit → Laravel, mapping en diálogo Compose y confirmación/replay.
La prueba usa localhost y el permiso HTTP existente del build debug; release conserva HTTPS.
