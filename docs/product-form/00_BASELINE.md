# Baseline — formulario adaptativo Web/Android

Fecha inicial: 2026-10-15 (America/Santo_Domingo). QA final: 2026-10-16 en el proceso local; diferencia de fecha del contexto documentada en `05_QA_EXECUTION.md`.

## Repositorios

| Proyecto | Rama consultada | HEAD al inicio | Remoto publicado |
|---|---|---|---|
| Laravel Web/API | `work/navigation-closure-20261015` | `4c41ec790da6243417c2e7b694d25f82fa69953c` | `master` coincide con HEAD |
| Android | `work/navigation-closure-20261015` | `2a772300e97152519d50d45a7abecc80ec82132a` | `main` coincide con HEAD |

Los dos árboles estaban limpios. Los nombres de las ramas locales son los mismos, aunque los HEAD de los repositorios son distintos. No se encontró ni sobrescribió trabajo local pendiente.

## Código observado antes de implementar

- Web: `config/business-types.php` declara 23 tipos, arquetipos, categorías, capacidades y `product_fields`.
- Web/API: `BusinessProfileService`, `BusinessCapabilityService` y `BusinessPresentationService` resuelven perfiles/capacidades. `/api/v1/shops` usa `ShopController::index`; se comprobará con una regresión si su selección de columnas realmente omite datos (la suite de presentación ya valida al menos el arquetipo hardware).
- Web: `resources/views/seller/products/form.blade.php`, `SellerProductController`, `ProductRequest`, `CatalogTaxonomyService` y `ShopRecordSyncService` participan en la persistencia; `unit_uuid` es identidad UUID por tienda, no texto libre.
- Android: `CatalogHomeScreen.kt::ProductForm` maneja los modos `unit`, `bottle`, `ml`, `service`, `decant`, combo, stock y presentación. `unitId` es UUID Room y se envía mediante el flujo de mutación; no se debe sustituir con UUID inventado ni texto localizado.
- Decants requiere conservar la semántica de botella fuente y la operación de apertura existente. Los datos de productos, stock, costo y FIFO pertenecen al dominio actual.
- Android no muestra en el modelo local de producto campos persistidos `brand`/`model`; no se asumirán implementados en la app hasta verificar un contrato real de escritura/lectura.

## Pruebas iniciales ejecutadas

- `php vendor/bin/pest tests/Feature/BusinessPresentationTest.php tests/Feature/BusinessProfileRegistrationTest.php tests/Feature/CatalogTaxonomyClosureTest.php`: **19 passed, 2,639 assertions**; SQLite en memoria según `phpunit.xml`.
- `.\gradlew.bat testDebugUnitTest`: Gradle indicó `UP-TO-DATE`; se repetirá con ejecución forzada tras cambios.
- Un intento inicial con `php artisan test --compact` no era compatible con esta instalación; no cuenta como test. Se usó Pest directamente.
- Dispositivo Android visible: `emulator-5554` (emulador). Esto por sí solo no implica que se hayan corrido pruebas instrumentadas.

## Datos y seguridad

- No se consultó ninguna base de datos remota/producción, no se inició sesión comercial ni se alteraron usuarios, tiendas, productos, inventario, ventas, caja o cuentas.
- Los tests Laravel usan `APP_ENV=testing`, `DB_CONNECTION=sqlite` y `DB_DATABASE=:memory:`.
- La validación de QA con recorridos que crean operaciones solo se hará con fixtures y almacenamiento aislado comprobables.
- No se desplegará backend ni se publicará/instalará APK release en esta fase.

## Pendientes de baseline

- Resolver evidencia sobre proyección de columnas de perfil mediante test de regresión, sin asumir el hallazgo del documento.
- Enumerar los 23 perfiles y clasificar los campos reales/soportados.
- Confirmar un entorno navegador/QA y fixtures aislados antes de afirmar E2E.

## Mapa de alta relevante

- Web alta/edición: rutas seller de productos → `SellerProductController` → `ProductRequest`/`validatePresentation` → `Product` + inventario y movimiento de stock inicial dentro de transacción → `ShopRecordSyncService::saveProductUnit` para `unit_uuid`. El importador y los flujos de servicio/combos/decants conservan sus propias validaciones/casos de uso.
- Contrato: `BusinessProfileService` resuelve `business_type`, plan y overrides; `BusinessPresentationService` agrega contexto visual; `ProductFormPresentationService` genera defaults, modos, unidad canónica y estados honestos de campo. Capacidad visual no es autorización.
- Android: DTO/modelo de presentación → `ProductForm` en Compose → `ProductInput`/caso de uso → unidad Room + outbox `unit_upsert` y luego `product_upsert`; conserva UUID del registro existente y usa el fallback estable offline. No se añadió esquema Room.
- Baseline de operación productiva: no se tomó snapshot remoto, no se consultó DB de producción y no se modificaron datos reales. El recuento de fixtures remotos queda intencionalmente sin realizar; QA se hizo con SQLite aislada.
- Regresión de `/api/v1/shops`: test con dos tipos de negocio del mismo owner y un tenant externo preserva tipo, versión, override y aislamiento. No se modificó `ShopController::index()` porque el defecto citado no se reprodujo.
