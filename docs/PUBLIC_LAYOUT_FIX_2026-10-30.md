# Corrección visual de inicio y descargas — 30/10/2026

Publicada y verificada en https://micatalogo.bsolutions.dev/ y /descargas.

## Causas y corrección

- Una regla global `main.mx-auto, main > .mx-auto` anulaba los límites de ancho
  públicos. Ahora pertenece exclusivamente a `.panel-shell`. Las secciones
  públicas recuperan 1280 px de máximo y el panel conserva su geometría amplia.
- Las capas ambientales de inicio se superponían al contenido y deslavaban las
  letras. El contenido principal tiene ahora su propio nivel sobre el fondo.
- La navegación completa no cabía en tablet y cortaba botones. Se muestra un menú
  adaptable hasta escritorio ancho, con enlaces de navegación y cuenta, estado
  accesible y cierre con Escape. Los controles de cuenta se acomodan al espacio.
- Descargas: marca y enlace de regreso ajustados a móviles pequeños; título con
  tamaño y espaciado legibles, ficha de versión apilada en móviles e instrucciones
  y detalle de release que se adaptan sin comprimir el texto.

Archivos modificados: public/css/responsive-layout.css,
resources/views/welcome.blade.php, resources/views/downloads/index.blade.php.

## Verificación

- 7 pruebas existentes de inicio y descargas, 47 verificaciones, sin fallos.
- Matriz local y pública de ambas páginas a 320, 390, 640, 768, 1024, 1280,
  1440 y 1920 px: sin desplazamiento horizontal ni encabezados/controles cortados.
- Menú probado en producción: apertura, enlaces dentro del ancho y cierre con
  Escape. Consola de descargas sin errores ni advertencias.
- Imágenes publicadas de escritorio, móvil, QR, instrucciones y ficha de release
  revisadas visualmente. Evidencias: output/playwright/public-layout-20261030/.
- Comprobación CSS aislada de geometría: público mantiene max-width 1280px;
  panel mantiene max-width none. También se comprobó el formulario de inicio
  de sesión local a 390px; no se realizó una sesión autenticada de producción.
- CSS pública descargada y comparada por SHA-256:
  03841300841448fd2af25c49012b7aa95a09bc861ff0d7cb967b41897f28c471.
- Caché de vistas recompilada; la URL del CSS usa la nueva versión 1793395468.
  Se conservaron la APK 1.0.128/código129, su hash y los enlaces de descarga.

## Respaldo y datos

Antes del despliegue se verificaron y respaldaron los tres destinos exactos,
manteniendo los cambios previos del servidor. Respaldo SQL de 74 tablas,
1,076,858 bytes: storage/app/private/database-backups/20261030_172326_pre-public-layout-fix-20261030.sql.
SHA-256: 5cca70bc3b7950c3d1f81b1d2f0a02425bf436172d2d248aa39a16b872f2ab3b.
Baseline privado SHA-256: ed3528fff3e86ffcbee70c2970886630e3f6122f5ff56b18c5bb66dd1c1b04a7.
Copias locales privadas verificadas. Tras publicar se conservaron los IDs y
conteos: 14 usuarios, 12 tiendas, 576 productos y 1 pedido.
