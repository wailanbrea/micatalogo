# Entrega Web y Android 1.0.112

## Cambios incluidos

- Integra el trabajo pendiente de navegación canónica Web/API/Android: mismo registro de destinos, permisos independientes y rutas anteriores conservadas.
- Refuerza los límites de Catálogo, Fotos, Compras, Clientes, Crédito, Cobros y Finanzas, incluido el replay de operaciones después de revocar permisos.
- Reutiliza el árbol del encabezado/menú lateral/menú móvil durante una misma petición. No almacena autorizaciones entre peticiones ni entre usuarios o tiendas. La regresión exige cero consultas adicionales al reutilizarlo y permisos nuevos en la petición siguiente.
- Encargos y Envíos Android dejan de sincronizar la lista de clientes de crédito que no utilizan. Pedidos conserva su sincronización y las listas anteriores mientras refresca.
- Excluye artefactos temporales QA de Git y conserva la documentación/evidencia sintética del trabajo anterior.

## Verificación de código

- Pest completo: **666 PASS, 7.614 aserciones**, SQLite en memoria, 91,90 s.
- Android JVM: **147 PASS**, con compilación debug QA y release firmada.
- Prueba focalizada de navegación: 32 PASS, 871 aserciones.
- Prueba focalizada de lecturas sensibles, incluyendo Pricing/Salud de precios independientes: 7 PASS, 53 aserciones.
- Navegador real contra QA local: **46 destinos internos HTTP 200**, formato móvil 390×844. Producto agregado al carrito desde POS; captura visual de Resumen desktop y POS/Inventario móvil. No se confirmó una venta en la base comercial.
- Las mediciones de esos destinos son locales y no equivalen a tiempos de producción: 322–1.635 ms con compilación Android/pruebas simultáneas.

## Artefacto release

- Paquete: `com.bsolutions.micatalogo`; versión `1.0.112`; código `113`; minSDK26; `debuggable=false`.
- Certificado SHA-256: `5a5670decdac3ee1e2fc95503ae65343c3a1f075f62dec835d26125a578d490f` (mismo que 1.0.111).
- APK SHA-256: `e043645ed2ee02a5697a2001d3b5465ef101978ef4fcb35b6ce1a3b1f2d442c9`.
- Tamaño: 41.245.004 bytes.
- URL prevista: https://micatalogo.bsolutions.dev/downloads/bspos-1.0.112-release.apk
- Se conserva el mínimo soportado23; esta entrega no cambia deliberadamente la política de actualización obligatoria.

## Protección de datos y reversión

Antes de desplegar se exportó la base comercial mediante la conexión de la aplicación, sin contraseñas en argumentos ni salidas. Copia privada exportada, marcador de finalización y copia local con el mismo SHA-256 `4714aa28e6dab65497a256edec7d36fcd6968ac6f936a4065dd81c00afc49590` (1.961.289 bytes). No se respaldaron carpetas de código ni se ejecutaron seeders/purgas.

Baseline de identificadores antes de publicar:

| Tabla | Conteo | SHA-256 de IDs ordenados |
|---|---:|---|
| users | 14 | b7bec94cc3b09b520fbcd9889fa6c4a2b0d2af5eaf9a861e8aee05b7a323580d |
| shops | 12 | 0d38452d616dffc9db7f54cd284b079b2da08798f363ab3b10fa66b9eec4b04f |
| products | 775 | 49c838f2939f1d9a0f1a0c46dbbba118ef4a3fd9ab08370a6cd91354290ff57c |
| orders | 4 | f6bd10506e9a4daed7c03eda2f2fde54be3bd58eee49dab471c18a888ffbdb6f |
| invoices | 33 | bff61d9b5c6a92dd4cf3d9d608d1a5cc4ed6e1804a398f5a4af3a1431fed7f3c |

Migraciones inspeccionadas: todas ejecutadas; esta entrega no añade migraciones. Reversión: código anterior Web `48cbd4a`, Android `ad9ecd7`, manifiesto anterior recuperable por Git y APK 1.0.111 conservada. No restaurar una base antigua sobre movimientos comerciales posteriores para revertir cambios de interfaz.

## Límites de certificación

No declarar aprobada globalmente la matriz NAV-001..NAV-070 ni los journeys A..G sólo por estas suites. El reporte histórico `navigation/QA_REPORT.md` enumera los recorridos manuales, accesibilidad y fixtures de integración que todavía requieren certificación específica. Las pruebas omitidas por falta de fixture no cuentan como PASS. Solo se detectó un emulador conectado; no se certifica instalación en Samsung físico en esta sesión.
