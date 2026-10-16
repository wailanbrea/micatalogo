# Cierre real QA — baseline 2026-10-16

## Repositorios y aislamiento

| Repo | Rama | HEAD al iniciar | Remoto de trabajo | Estado inicial |
|---|---|---|---|---|
| Web/API | `work/navigation-closure-20261015` | `d1cb137` | igual al HEAD local | limpio |
| Android | `work/navigation-closure-20261015` | `4f03ca9` | igual al HEAD local | limpio |

Commits de código y evidencia, todos empujados a las mismas ramas de trabajo: Web cambio de código `0111b97`, ledger final `84480b5`; Android `03d18b4`.

Web estaba 7 commits por delante de `origin/master` y Android 4 commits por delante de `origin/main`; ambos HEAD coincidían con su rama remota de trabajo. No se cambió de rama ni se consultó/modificó la base productiva.

Web ejecuta Pest con `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` y `DB_URL` vacío. El archivo `phpunit.xml` fuerza SQLite en memoria y Turnstile desactivado para la suite; no se hacen llamadas a servicios externos. Android usa el AVD conectado y el perfil `ui`/paquete aislado `.offlinecheck.syncqa`, URL local no enrutable `127.0.0.1:9` y ningún perfil `syncQaLive`.

## Ejecución verificada en este cierre

- Pest completo en la versión de trabajo más los cambios locales: **686 passed, 0 failed, 7,774 assertions**, 49.08 s.
- Android JVM después de los cambios actuales: **152 tests, 0 failures, 0 errors, 0 skipped**, 45 XML de JUnit.
- Android compilación `assembleDebug` y `assembleDebugAndroidTest`: PASS después de los cambios actuales.
- UI instrumentada inicial del perfil amplio: **110 tests, 10 failure entries**. Dos eran expectativas desactualizadas (accesibilidad del login y singular/plural de Decants); ocho requerían el servidor QA live ausente/no habilitado en el perfil UI.
- Repeticiones instrumentadas seguras en Pixel 10 Pro XL AVD: `ProductFormTest` **5/5 PASS**, `LoginScreenTest` **1/1 PASS**, `DecantsWorkspaceTest` **13/13 PASS**. La prueba nueva cubre snapshot sin categorías: evita crash y no permite guardar sin categoría.
- Android `:app:lintDebug`: BUILD SUCCESSFUL; HTML/SARIF local en `Android/app/build/reports/lint-results-debug.*`.
- El reporte Lint contiene **68 avisos (64 Warning, 4 Hint), 0 Error**. Los dos avisos encontrados en `CatalogHomeScreen.kt` son sugerencias `UseKtx` preexistentes, no bloqueos de esta corrección.

## Límites de evidencia

- El puerto local de desarrollo encontrado previamente no tiene identidad QA comprobada y sus respuestas de identidad fallaron. No se usó para mutaciones.
- No se ejecutaron journeys Web↔Android con una API/base QA compartida, ni se crearon transacciones comerciales. E2E A–H siguen sin certificación de recorrido integral.
- No se han capturado screenshots/trace de navegador en este corte.
- La app pública, el backend remoto y el APK instalado no se deducen del estado Git ni de builds locales.
