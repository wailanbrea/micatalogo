# MiCatalogo

MiCatalogo is a public product showcase for independent sellers. Visitors discover
products, build a shared cart, and contact sellers through WhatsApp. Sellers can confirm
the cart from their panel, atomically decrement inventory, and issue a stored invoice.

## Stack

- Laravel 12, PHP 8.2, MariaDB 11.4
- Blade, Livewire 4, Tailwind 4, Vite
- Cloudflare Turnstile, optional Cloudflare R2 media storage

## Commerce Flow

- WhatsApp cart links reopen the selected products in the store catalog.
- The seller-only checkout endpoint processes all cart lines in one database transaction.
- A checkout creates one invoice with frozen product names, quantities, prices, and totals.
- Invoice PDFs are served through signed links that can be opened or shared in WhatsApp.
- Bottle and decant sales update milliliters, stock availability, cost recovery, and movement history.
- The inventory dashboard includes responsive mobile cards, sales charts, and product revenue history.

## Local Setup

1. Copy `.env.example` to `.env` and configure the local database.
2. Install dependencies with `php .tools/composer/composer.phar install` and `npm install`.
3. Run `php artisan migrate --seed`.
4. Start the app with `php artisan serve` and assets with `npm run dev`.

## Verification

```powershell
php artisan test
php artisan test --filter=InventoryLiteTest
composer test
php .\vendor\bin\pint --test
npm run build
php .tools\composer\composer.phar audit
```

## Financial Architecture (Fase Financiera 1)

El motor financiero de MiCatalogo unifica la contabilidad de ventas, cobros, cartera y caja con verdad matemática única:
- **Aritmética en centavos**: Prohibido el uso de `float` en lógica contable y persistencia (`App\Support\Money`).
- **Estado de Resultados (P&L)**: Ventas brutas, deducción de ITBIS de ingresos netos, descuentos en línea y globales, devoluciones con restitución de costo FIFO, ganancia bruta, gastos operativos devengados, comisiones y ganancia operativa.
- **Flujo de Efectivo**: Registro estricto sin doble contabilización entre cobros de mostrador (`whereNull('customer_account_entry_id')`) y cobros de deudas (`customer_account_entries`).
- **Amortización FIFO de deudas**: Los cobros de clientes se asignan en orden cronológico estricto a las facturas impagas, creando vínculos `InvoicePayment` y conciliando el saldo de cartera ($\text{total\_receivable} = \text{invoices\_total} + \text{unallocated\_receivables}$, $\text{reconciliation\_difference} = 0$).
- **Arqueo de caja (`CashRegisterSession`)**: Aislamiento por usuario y tienda con bloqueo único en BD e idempotencia estricta contra arqueos concurrentes dispares (409 Conflict).
- **Autorización protegida**: Dashboard de negocio protegido con `can:viewFinance,shop` (vendedores ordinarios 403), y gastos protegidos con `menu:expenses` y plan Pro.

### Estado de Compatibilidad de la App Android (`1.0.14`, version code `15`)

- **Sincronización y POS**: Totalmente compatible a nivel de backend/API para ventas directas, cobros estándar y sincronización de clientes/inventario.
- **Cumplimiento de la Fase Financiera 1**: **NO CUMPLE AÚN** en el cliente móvil. La versión actual (`1.0.14`) no implementa en pantalla los flujos de cobros divididos multimoneda, apertura y cierre físico de caja con arqueo ciego, registro de gastos operativos, ni visualización del aging FIFO desglosado por factura. El soporte móvil completo está programado para la versión `1.0.15` / `1.1.0`.

## Media Storage

Local development falls back to the `public` disk when R2 credentials are absent. For
production, configure `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`,
`R2_ENDPOINT`, and a custom `R2_URL`. Do not use an `r2.dev` domain in production.

See `docs/10_PROJECT_STATE.md` for the verified state and `docs/07_DEPLOYMENT.md` for
the release checklist.
