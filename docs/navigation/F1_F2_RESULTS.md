# F1–F2 — registro común y navegación Web

## Realizado

- Registro de once áreas en `config/seller-navigation.php`, derivado de claves efectivas mediante `SellerNavigationService`. No se reemplazaron claves persistidas ni se añadieron migraciones.
- `/api/v1/shops` incluye `navigation` schema1 de forma aditiva. El test de contrato conserva comparación exacta de todos los campos legacy excluyendo únicamente ese campo nuevo.
- Desktop/móvil comparten `x-admin.navigation-tree`: grupos colapsables, una única selección específica por ruta, hijos con claves independientes, panel global separado.
- Caja operativa en Ventas; Ajustes/FIFO/Importar en Inventario; Reportes independiente; Equipo/Contador/Autorizaciones en Configuración; Cuenta/Ayuda secundarios y accesibles a usuario autenticado con tienda accesible.
- Menú contextual móvil abre el menú principal existente; se retiró su segunda lista. Las versiones anteriores de los templates se conservaron como evidencia, fuera del árbol runtime de componentes.
- Productos y Servicios tienen tabs contextualizadas con permiso propio y conservan sus URLs. Productos físicos no lista servicios. Combos conserva filtro/formulario vigente. Categorías globales siguen separadas.
- Labels de sección de feature Web se derivan del catálogo canónico. Pantallas informativas se rotulan Consulta; Práctica se describe como guía con enlaces reales, sin afirmar un sandbox inexistente.
- Mis tiendas permanece accesible para cambio de contexto del owner/manager/contador; vendedor asignado no recibe controles de tiendas.

## Verificado

- Suite backend completa: **621 PASS / 7.328 aserciones**, SQLite en memoria. Últimos cambios de labels/grupos y contexto: suites afectadas **84 PASS / 1.305 aserciones**.
- Focalizadas de manifest/paridad/permisos/panel/visibilidad: 50 PASS / 1.077 aserciones antes de añadir la prueba adicional de separación por tipo.
- `npm run build` PASS. Pint de nuevos service/config/tests PASS. PHP lint y git diff --check PASS.
- Identidad de rutas: **237 antes = 237 después; cero removidas, modificadas o añadidas** en nombre/URI/métodos/acción. Middleware/guards pendientes de F4 no se consideran aprobados por esa comparación.
- Playwright real: diez roles, desktop1440×1000 y móvil390×844; mismos ids/URLs/selección en ambos árboles. Sin overflow horizontal. Servicios y Reportes ahora visibles a sus roles; sales-only no contiene Inventario.
- Acceso contextual móvil, colapsado de Catálogo y navegación Livewire hasta Servicios comprobados; tabs Productos y Servicios visibles al owner; antigua segunda lista ausente. Consola: cero errores.
- Compatibilidad APK anterior a F3: mismo debug aislado instalado, login/API/drawer con diez roles tras añadir manifest; instrumentación 1 PASS / 0 FAIL / 0 SKIP / 0 ASSUMPTION. Evidencia phone actual en `evidence/f2-android-legacy/`; archivos tablet en esa copia corresponden al baseline F0, no a un nuevo run F2.
- Fixture comercial permanece igual: stock20, vendidos0, costo120.00, saldo cliente0.00; cero facturas/cajas. Baseline Room/outboxes conservado.

## Regresiones encontradas y corregidas

1. La prueba de selección sidebar esperaba Resumen; nueva etiqueta aprobada Inicio. Se actualizó la expectativa y se añadió comprobación de URL exacta summary, manteniendo la exigencia de una sola selección.
2. El contrato exacto de shops rechazó el nuevo campo navigation. Se añadió su schema1 y se preservó la comparación exacta del payload anterior mediante proyección que excluye sólo navigation.
3. El selector Mis tiendas dejó de mostrarse en dashboard con tienda activa. Se restauró como acción secundaria Web del registro, con prueba original pasando.
4. Primeras capturas móviles quedaron dentro de transición de opacidad. Se repitieron esperando opacity1; no se usaron como evidencia final las imágenes durante transición.

## Gates y pendientes

F1 y F2 de estructura/presentación aprobados con evidencia anterior. **No hay aprobación final de seguridad ni producción.** F3 debe consumir el manifest con whitelist/fallback/offline y conservar Screen/back stack. F4 sigue pendiente: FinanceScreen/ViewModel por permiso/tab, mutaciones deshabilitadas por módulo, formularios products/services por recurso, cobranza y compras con permisos independientes, y admin/owner/manager por operación. F5 requiere NAV001..070/E2E A..G actuales.

Las funciones exclusivamente nativas identificadas en F0 (impresoras, distribución, preferencias locales, workspace nativo de devoluciones/cobros) no se anuncian como nuevas pantallas Web operativas. Sus endpoints y rutas existentes se conservan; no se inventan placeholders.
