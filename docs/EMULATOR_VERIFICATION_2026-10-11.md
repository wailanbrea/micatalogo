# Verificación en emulador — 11/10/2026

## Resultado

Se ejecutaron **57 pruebas instrumentadas aisladas** (45 de pantallas/flujos y
12 de migración/aislamiento), más **2 pruebas de la actividad real contra el
backend QA local**. Ambas pruebas reales y todas las pruebas aisladas terminaron
aprobadas. Se ejecutó también `:app:testDebugUnitTest`: 111 pruebas sin fallos.

La verificación detectó y corrigió dos fallos reales que no cubrían las pruebas
de componentes:

1. Pedidos confirmaba correctamente en el servidor pero mantenía abierto el
   diálogo: el refresco borraba el mensaje antes de que la pantalla lo consumiera.
   `OrdersViewModel.load(refresh=true)` ahora conserva la confirmación.
2. Resumen mostraba ventas/ganancia cero después de confirmar un pedido remoto.
   Ahora consulta el resumen financiero por tienda y rango; rechaza respuestas
   de otra selección, respeta costos pendientes y usa los totales autoritativos.
   Cuando la caché no contiene la serie completa, enlaza al detalle de ventas
   en lugar de mostrar un gráfico local vacío como si no hubiera ventas.

## Entorno

- Dispositivo: `emulator-5554`, Pixel 10 Pro XL, Android 17.
- App: debug `com.bsolutions.micatalogo.offlinecheck.emulatorqa`.
- Versión: `1.0.93-offlinecheck`, versionCode 94, certificado debug verificado.
- Backend: proceso PHP local, `APP_ENV=testing`, SQLite separada en Temp.
- API de la ejecución completa: `http://127.0.0.1:18766/`, túnel ADB reverse.
- Base: `C:/Users/waila/AppData/Local/Temp/opencode/micatalogo-emulator-complete-20261011.sqlite`.
- Identidad de conexión comprobada antes de crear y consultar las fixtures.
- Cuenta y datos sintéticos QA. No se modificaron tiendas reales, VPS ni teléfono.
- El servicio de autocompletado de Android se desactivó temporalmente para evitar
  ventanas del gestor de contraseñas durante la automatización; quedó restaurado
  a `com.google.android.gms/.autofill.service.AutofillService`.

## Recorrido real aprobado

`EmulatorJourneyTest#loginSharedCustomerCreditOrderCollectionAndShopSwitch`:

1. Login y bootstrap de QA Tienda A.
2. Clientes muestra activos e inactivos de A, sin clientes de B.
3. Crear `QA Nuevo A2` desde Clientes y comprobar su persistencia.
4. Abrir caja con fondo inicial cero.
5. Pedidos utiliza el mismo cliente recién creado; el cliente inactivo está
   visible pero no es seleccionable para crédito.
6. Confirmar pedido de dos unidades por RD$600 a crédito; cerrar correctamente
   el diálogo y mostrar el mensaje de éxito.
7. Cobrar RD$600 y comprobar que desaparece el saldo pendiente.
8. Cambiar a B: sólo sus clientes/productos son visibles.
9. Agregar producto B al carrito, volver a A y comprobar que el carrito está vacío
   (no hay acción de cobro) y el producto B no aparece.
10. Verificar que el cliente creado sigue sólo en A.
11. Cerrar caja contando RD$600.

`EmulatorJourneyTest#ownerSummaryReadsServerForEachShop`:

- Resumen de A: ventas RD$600, ganancia RD$360, también al cambiar a Este mes.
- Resumen de B: cero, sin arrastrar los importes de A.
- Volver a A recupera sus RD$600.

## Contraste de datos persistidos

| Dato | QA Tienda A | QA Tienda B |
| --- | --- | --- |
| Stock inicial | 20 | 20 |
| Stock final | 18 | 20 |
| Unidades vendidas | 2 | 0 |
| Facturas | 1, pagada | 0 |
| Importe facturado | RD$600 | RD$0 |
| Saldo de clientes final | RD$0 | RD$0 |
| Pedido | Confirmado | Sin confirmar |
| Caja | Cerrada | Sin sesiones |
| Esperado / contado en caja | 60000 / 60000 centavos | — |
| Diferencia | 0 centavos | — |

Las ejecuciones de diagnóstico anteriores se conservaron en otra base QA; no
se borraron registros para conseguir una prueba aprobada. La ejecución completa
se hizo en una segunda base nueva y terminó aprobada de principio a fin.

## Evidencia y reproducción

- Fixture/inspector local: `qa/emulator_backend.php`; sólo admite testing y las
  rutas SQLite explícitas. Se niega a sobrescribir una snapshot existente.
- Prueba real Android:
  `app/src/androidTest/java/com/example/bspos/presentation/emulatorqa/EmulatorJourneyTest.kt`.
- Build: `-PmiCatalogoOfflineCheck=true -PmiCatalogoEmulatorUiCheck=true
  -PmiCatalogoApiBaseUrl=http://127.0.0.1:18766/`.
- Pruebas reales requieren `-e emulatorQaLive true` y verifican paquete/base URL.
- Capturas privadas: `C:/Users/waila/AppData/Local/Temp/opencode/emulatorqa-final-evidence/`.

## Alcance pendiente

Esto verifica los flujos enumerados y las pantallas instrumentadas, no todos los
dispositivos/periféricos ni todas las ramas posibles. La suite histórica completa
de pruebas de base Android todavía requiere adaptar más fixtures a ShopScope.
El recorrido real de venta offline/reconexión y las pruebas de hardware Bluetooth
no forman parte de esta ejecución. No se publicó ni desplegó una release.
