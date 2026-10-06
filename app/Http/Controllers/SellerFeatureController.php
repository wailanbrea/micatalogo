<?php

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InventoryLot;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\BusinessDashboardService;
use App\Services\SellerMenuService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerFeatureController extends Controller
{
    /** @var array<string, array{group: string, title: string, description: string, status: string}> */
    private const FEATURES = [
        'sales' => ['group' => 'Operación', 'title' => 'Ventas', 'description' => 'Consulta y administra las ventas registradas desde el Punto de venta.', 'status' => 'En preparación'],
        'quotes' => ['group' => 'Operación', 'title' => 'Cotizaciones', 'description' => 'Prepara cotizaciones, compártelas con tus clientes y conviértelas en ventas.', 'status' => 'En preparación'],
        'orders' => ['group' => 'Operación', 'title' => 'Pedidos', 'description' => 'Centraliza los pedidos recibidos desde tu catálogo público y su estado de atención.', 'status' => 'En preparación'],
        'encargos' => ['group' => 'Operación', 'title' => 'Encargos', 'description' => 'Organiza encargos pendientes, fechas de entrega y seguimiento al cliente.', 'status' => 'En preparación'],
        'shipments' => ['group' => 'Operación', 'title' => 'Envíos', 'description' => 'Gestiona entregas, transportistas y estados de envío desde un mismo lugar.', 'status' => 'En preparación'],
        'day_close' => ['group' => 'Operación', 'title' => 'Cierre de día', 'description' => 'Revisa ventas, cobros, gastos y diferencias antes de cerrar la jornada.', 'status' => 'En preparación'],
        'containers' => ['group' => 'Compras', 'title' => 'Contenedores', 'description' => 'Agrupa compras internacionales o recepciones grandes para controlar su costo total.', 'status' => 'En preparación'],
        'loads' => ['group' => 'Compras', 'title' => 'Cargas', 'description' => 'Registra mercancía en tránsito y sus costos asociados antes de recibirla.', 'status' => 'En preparación'],
        'suppliers' => ['group' => 'Compras', 'title' => 'Suplidores', 'description' => 'Administra tus suplidores, contactos, condiciones y compras históricas.', 'status' => 'En preparación'],
        'purchase_invoices' => ['group' => 'Compras', 'title' => 'Facturas de compra', 'description' => 'Relaciona facturas de compra con productos, lotes y costos FIFO.', 'status' => 'En preparación'],
        'photos' => ['group' => 'Catálogo', 'title' => 'Fotos', 'description' => 'Mejora las imágenes del catálogo y detecta productos que necesitan una foto.', 'status' => 'En preparación'],
        'services' => ['group' => 'Catálogo', 'title' => 'Servicios', 'description' => 'Crea servicios, precios y duración para negocios que venden experiencias o citas.', 'status' => 'En preparación'],
        'price_health' => ['group' => 'Catálogo', 'title' => 'Salud de precios', 'description' => 'Detecta precios sin margen, desactualizados o fuera de la regla de tu negocio.', 'status' => 'En preparación'],
        'decants' => ['group' => 'Catálogo', 'title' => 'Decants', 'description' => 'Controla fracciones, mililitros disponibles y costo compartido por lote.', 'status' => 'En preparación'],
        'attributes' => ['group' => 'Catálogo', 'title' => 'Marcas y atributos', 'description' => 'Organiza marcas, tallas, colores, concentraciones y otros atributos de tus productos.', 'status' => 'En preparación'],
        'credit' => ['group' => 'Cobros', 'title' => 'Crédito', 'description' => 'Da seguimiento a cuentas por cobrar, vencimientos y abonos de clientes.', 'status' => 'En preparación'],
        'inventory_adjustments' => ['group' => 'Finanzas', 'title' => 'Ajustes de inventario', 'description' => 'Audita ajustes, pérdidas y diferencias de inventario sin alterar el historial contable.', 'status' => 'En preparación'],
        'partners' => ['group' => 'Finanzas', 'title' => 'Socios', 'description' => 'Prepara el control de aportes, participaciones y retiros del negocio.', 'status' => 'En preparación'],
        'reports' => ['group' => 'Análisis', 'title' => 'Reportes', 'description' => 'Construye reportes operativos y financieros exportables para tomar mejores decisiones.', 'status' => 'En preparación'],
        'commissions' => ['group' => 'Equipo', 'title' => 'Comisiones', 'description' => 'Define reglas y consulta comisiones por vendedor y por venta.', 'status' => 'En preparación'],
        'authorizations' => ['group' => 'Equipo', 'title' => 'Autorizaciones', 'description' => 'Controla qué acciones requieren aprobación del propietario.', 'status' => 'En preparación'],
        'accountant' => ['group' => 'Ajustes', 'title' => 'Contador', 'description' => 'Prepara el acceso de tu contador a la información financiera necesaria.', 'status' => 'En preparación'],
        'account' => ['group' => 'Ajustes', 'title' => 'Mi cuenta', 'description' => 'Administra tus datos personales, seguridad, sesión y preferencias de MiCatalogo.', 'status' => 'En preparación'],
        'updates' => ['group' => 'Ajustes', 'title' => 'Novedades', 'description' => 'Conoce las mejoras y nuevas funciones de MiCatalogo.', 'status' => 'En preparación'],
        'help' => ['group' => 'Ajustes', 'title' => 'Ayuda', 'description' => 'Encuentra guías rápidas para aprender a usar cada parte de tu negocio.', 'status' => 'En preparación'],
        'practice' => ['group' => 'Ajustes', 'title' => 'Practicar sin miedo', 'description' => 'Aprende los flujos principales con datos de práctica sin tocar tu operación real.', 'status' => 'En preparación'],
        'support' => ['group' => 'Ajustes', 'title' => 'Soporte', 'description' => 'Envía una solicitud y consulta el estado de la ayuda de tu equipo.', 'status' => 'En preparación'],
    ];

    public function show(
        Request $request,
        Shop $shop,
        string $feature,
        SellerMenuService $menus,
        ?BusinessDashboardService $dashboard = null
    ): View
    {
        abort_unless(isset(self::FEATURES[$feature]), 404);
        abort_unless(in_array($feature, $menus->visibleForUser($shop, $request->user()), true), 403);

        $related = match ($feature) {
            'sales', 'quotes', 'orders', 'encargos', 'shipments', 'day_close' => [
                ['label' => 'Ir al Punto de venta', 'url' => route('seller.shops.pos', $shop)],
                ['label' => 'Ver ganancias y resumen', 'url' => route('seller.shops.business', $shop)],
            ],
            'containers', 'loads', 'suppliers', 'purchase_invoices' => [
                ['label' => 'Ver inventario', 'url' => route('seller.shops.inventory.index', $shop)],
                ['label' => 'Ver lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $shop)],
            ],
            'photos', 'services', 'price_health', 'decants', 'attributes' => [
                ['label' => 'Ver productos', 'url' => route('seller.shops.products.index', $shop)],
                ['label' => 'Precios automáticos', 'url' => route('seller.shops.pricing.index', $shop)],
            ],
            'credit' => [
                ['label' => 'Ver clientes y cobros', 'url' => route('seller.shops.customers.index', $shop)],
                ['label' => 'Ir al Punto de venta', 'url' => route('seller.shops.pos', $shop)],
            ],
            'inventory_adjustments' => [
                ['label' => 'Ver inventario', 'url' => route('seller.shops.inventory.index', $shop)],
                ['label' => 'Ver lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $shop)],
            ],
            'partners', 'reports' => [
                ['label' => 'Ver ganancias y resumen', 'url' => route('seller.shops.business', $shop)],
                ['label' => 'Ver estadísticas', 'url' => route('seller.shops.metrics.index', $shop)],
            ],
            'commissions', 'authorizations' => [
                ['label' => 'Administrar vendedores', 'url' => route('seller.shops.sellers.index', $shop)],
                ['label' => 'Configuración de tienda', 'url' => route('seller.shops.edit', $shop)],
            ],
            default => [
                ['label' => 'Configuración de tienda', 'url' => route('seller.shops.edit', $shop)],
                ['label' => 'Volver al resumen', 'url' => route('seller.dashboard')],
            ],
        };

        return view('seller.features.show', [
            'shop' => $shop,
            'featureKey' => $feature,
            'feature' => self::FEATURES[$feature],
            'related' => $related,
            'module' => $this->moduleData($feature, $shop, $dashboard ?: app(BusinessDashboardService::class)),
        ]);
    }

    /**
     * Build read models from the existing accounting/catalog domains.
     * Missing domains intentionally remain in the prepared state until their
     * persistence and accounting rules are defined.
     *
     * @return array{kind: string, kpis: array<int, array{label: string, value: string, tone: string}>, rows: array<int, array<string, mixed>>, note: ?string}
     */
    private function moduleData(string $feature, Shop $shop, BusinessDashboardService $dashboard): array
    {
        return match ($feature) {
            'sales' => $this->salesData($shop),
            'orders' => $this->ordersData($shop),
            'encargos' => $this->ordersData($shop, 'pending'),
            'shipments' => $this->ordersData($shop, null, true),
            'day_close' => $this->dayCloseData($shop),
            'photos' => $this->photosData($shop),
            'price_health' => $this->priceHealthData($shop),
            'decants' => $this->decantsData($shop),
            'attributes' => $this->attributesData($shop),
            'credit' => $this->creditData($shop),
            'reports' => $this->reportsData($shop, $dashboard),
            'commissions' => $this->commissionsData($shop),
            default => [
                'kind' => 'prepared',
                'kpis' => [],
                'rows' => [],
                'note' => null,
            ],
        };
    }

    private function salesData(Shop $shop): array
    {
        $today = $shop->invoices()->whereDate('issued_at', today())->where('status', '!=', 'void');

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas de hoy', 'value' => $this->money((clone $today)->sum('total')), 'tone' => 'blue'],
                ['label' => 'Operaciones', 'value' => number_format((clone $today)->count()), 'tone' => 'slate'],
                ['label' => 'Cuentas pendientes', 'value' => $this->money($shop->invoices()->whereIn('status', ['credit', 'partial'])->sum('total')), 'tone' => 'amber'],
            ],
            'rows' => $shop->invoices()->with(['customer', 'user'])->latest('issued_at')->limit(20)->get()->map(fn (Invoice $invoice) => [
                'primary' => $invoice->invoice_number,
                'secondary' => ($invoice->customer?->name ?: 'Venta general').' · '.($invoice->issued_at?->format('d/m/Y H:i') ?: 'Sin fecha'),
                'value' => $this->money($invoice->total),
                'status' => $this->invoiceStatus($invoice),
            ])->all(),
            'note' => 'Las ventas se registran desde Terminal y alimentan inventario, caja, crédito y ganancias.',
        ];
    }

    private function ordersData(Shop $shop, ?string $status = null, bool $shipping = false): array
    {
        $query = $shop->orders()->with('items')->latest();
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($shipping) {
            $query->whereIn('delivery_type', ['delivery', 'shipping', 'envio']);
        }
        $orders = $query->limit(30)->get();

        return [
            'kind' => 'orders',
            'kpis' => [
                ['label' => 'Pendientes', 'value' => number_format($shop->orders()->where('status', 'pending')->count()), 'tone' => 'amber'],
                ['label' => 'Confirmados', 'value' => number_format($shop->orders()->where('status', 'confirmed')->count()), 'tone' => 'emerald'],
                ['label' => 'Valor recibido', 'value' => $this->money($shop->orders()->sum('total')), 'tone' => 'blue'],
            ],
            'rows' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'primary' => $order->order_number,
                'secondary' => ($order->customer_name ?: 'Cliente sin nombre').' · '.($order->delivery_type ?: 'Retiro'),
                'value' => $this->money($order->total),
                'status' => $this->orderStatus($order),
                'can_confirm' => $order->invoice_id === null && $order->status !== 'cancelled',
            ])->all(),
            'note' => $shipping ? 'Los envíos se filtran desde los pedidos que tienen entrega configurada.' : 'Confirma un pedido únicamente cuando la mercancía esté lista para descontar inventario.',
        ];
    }

    private function dayCloseData(Shop $shop): array
    {
        $invoices = $shop->invoices()->whereDate('issued_at', today())->where('status', '!=', 'void');
        $expenses = $shop->expenses()->whereDate('occurred_at', today());
        $session = $shop->cashRegisterSessions()->where('status', 'open')->first();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas del día', 'value' => $this->money((clone $invoices)->sum('total')), 'tone' => 'blue'],
                ['label' => 'Gastos del día', 'value' => $this->money((clone $expenses)->sum('amount')), 'tone' => 'rose'],
                ['label' => 'Caja', 'value' => $session ? 'Abierta' : 'Cerrada', 'tone' => $session ? 'emerald' : 'slate'],
            ],
            'rows' => [
                ['primary' => 'Ventas registradas', 'secondary' => 'Facturas no anuladas de hoy', 'value' => number_format((clone $invoices)->count()), 'status' => 'Listo'],
                ['primary' => 'Gastos registrados', 'secondary' => 'Egresos con fecha de hoy', 'value' => number_format((clone $expenses)->count()), 'status' => 'Listo'],
                ['primary' => 'Sesión de caja', 'secondary' => $session?->opened_at?->format('d/m/Y H:i') ?: 'No hay una sesión abierta', 'value' => $session ? 'Abierta' : 'Revisar', 'status' => $session ? 'Activa' : 'Pendiente'],
            ],
            'session' => $session ? [
                'id' => $session->public_id,
                'expected' => number_format($session->calculateExpectedBalance() / 100, 2, '.', ''),
            ] : null,
            'note' => 'El cierre resume ventas, gastos y caja. El arqueo usa la misma sesión financiera y conserva la diferencia registrada.',
        ];
    }

    private function photosData(Shop $shop): array
    {
        $products = $shop->products()->withCount('images')->latest()->limit(30)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Productos', 'value' => number_format($shop->products()->count()), 'tone' => 'blue'],
                ['label' => 'Con fotos', 'value' => number_format($products->where('images_count', '>', 0)->count()), 'tone' => 'emerald'],
                ['label' => 'Pendientes', 'value' => number_format($products->where('images_count', 0)->count()), 'tone' => 'amber'],
            ],
            'rows' => $products->map(fn (Product $product) => [
                'primary' => $product->name,
                'secondary' => $product->product_code ?: 'Sin código',
                'value' => number_format($product->images_count).' foto(s)',
                'status' => $product->images_count ? 'Completo' : 'Pendiente',
            ])->all(),
            'note' => 'La edición y carga de imágenes se mantiene en Productos, donde se aplican límites, WebP e idempotencia.',
        ];
    }

    private function priceHealthData(Shop $shop): array
    {
        $products = $shop->products()->with('inventory')->where('sale_unit', '!=', 'decant')->limit(100)->get();
        $rules = \DB::table('product_price_rules')->whereIn('product_id', $products->pluck('id'))->get()->keyBy('product_id');
        $pending = $rules->filter(fn ($rule) => $rule->pending_price !== null)->count();
        $withoutCost = $products->filter(fn (Product $product) => $product->inventory?->cost_price === null)->count();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Reglas activas', 'value' => number_format($rules->count()), 'tone' => 'blue'],
                ['label' => 'Aprobaciones', 'value' => number_format($pending), 'tone' => $pending ? 'amber' : 'emerald'],
                ['label' => 'Sin costo', 'value' => number_format($withoutCost), 'tone' => $withoutCost ? 'rose' : 'emerald'],
            ],
            'rows' => $products->map(fn (Product $product) => [
                'primary' => $product->name,
                'secondary' => $product->inventory?->cost_price !== null ? 'Costo RD$ '.number_format((float) $product->inventory->cost_price, 2) : 'Costo pendiente',
                'value' => 'Precio RD$ '.number_format($product->currentPrice(), 2),
                'status' => isset($rules[$product->id]) ? ($rules[$product->id]->pending_price !== null ? 'Revisar' : 'Regla activa') : 'Sin regla',
            ])->all(),
            'note' => 'Las bajadas de precio siguen esperando aprobación y las ventas conservan el costo real del lote.',
        ];
    }

    private function decantsData(Shop $shop): array
    {
        $products = $shop->products()->with(['inventory', 'sourceProduct.inventory'])->where('sale_unit', 'decant')->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Decants', 'value' => number_format($products->count()), 'tone' => 'blue'],
                ['label' => 'Con origen', 'value' => number_format($products->whereNotNull('inventory_source_product_id')->count()), 'tone' => 'emerald'],
                ['label' => 'Sin origen', 'value' => number_format($products->whereNull('inventory_source_product_id')->count()), 'tone' => 'amber'],
            ],
            'rows' => $products->map(fn (Product $product) => [
                'primary' => $product->name,
                'secondary' => $product->sourceProduct?->name ?: 'Botella de origen no configurada',
                'value' => $product->volume_ml ? $product->volume_ml.' ml' : 'Volumen pendiente',
                'status' => $product->sourceProduct ? 'Vinculado' : 'Revisar',
            ])->all(),
            'note' => 'Los decants comparten el inventario de su producto de origen; no se duplica la valoración de la botella.',
        ];
    }

    private function attributesData(Shop $shop): array
    {
        $attributes = $shop->attributeDefinitions()->with('shopCategory')->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Atributos', 'value' => number_format($attributes->count()), 'tone' => 'blue'],
                ['label' => 'Obligatorios', 'value' => number_format($attributes->where('required', true)->count()), 'tone' => 'amber'],
                ['label' => 'Filtrables', 'value' => number_format($attributes->where('filterable', true)->count()), 'tone' => 'emerald'],
            ],
            'rows' => $attributes->map(fn (AttributeDefinition $attribute) => [
                'primary' => $attribute->name,
                'secondary' => $attribute->shopCategory?->name ?: 'Todas las categorías',
                'value' => ucfirst($attribute->type),
                'status' => $attribute->required ? 'Obligatorio' : 'Opcional',
            ])->all(),
            'note' => 'La definición de atributos ya está centralizada en el catálogo y se puede reutilizar para marcas, tallas, colores o concentraciones.',
        ];
    }

    private function creditData(Shop $shop): array
    {
        $customers = $shop->customers()->where('balance', '>', 0)->orderByDesc('balance')->limit(30)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Clientes con saldo', 'value' => number_format($shop->customers()->where('balance', '>', 0)->count()), 'tone' => 'amber'],
                ['label' => 'Por cobrar', 'value' => $this->money($shop->customers()->sum('balance')), 'tone' => 'rose'],
                ['label' => 'Límite asignado', 'value' => $this->money($shop->customers()->sum('credit_limit')), 'tone' => 'blue'],
            ],
            'rows' => $customers->map(fn (Customer $customer) => [
                'primary' => $customer->name,
                'secondary' => $customer->phone ?: 'Sin teléfono',
                'value' => $this->money($customer->balance),
                'status' => (float) $customer->balance > (float) $customer->credit_limit ? 'Excedido' : 'Pendiente',
            ])->all(),
            'note' => 'Los créditos y abonos se registran desde Clientes para conservar la asignación contable y evitar saldos manuales.',
        ];
    }

    private function reportsData(Shop $shop, BusinessDashboardService $dashboard): array
    {
        $summary = $dashboard->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString(), 'profit', 'desc');

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas netas', 'value' => $this->money($summary['period']['net_sales']), 'tone' => 'blue'],
                ['label' => 'Ganancia bruta', 'value' => $this->money($summary['period']['gross_profit']), 'tone' => 'emerald'],
                ['label' => 'Margen', 'value' => number_format((float) ($summary['period']['gross_margin_percent'] ?? 0), 1).'% ', 'tone' => 'slate'],
            ],
            'rows' => collect($summary['profitability'])->take(20)->map(fn (array $product) => [
                'primary' => $product['product_name'],
                'secondary' => number_format($product['units']).' unidad(es) · Ventas '.$this->money($product['revenue']),
                'value' => $this->money($product['gross_profit']),
                'status' => $product['has_unknown_cost'] ? 'Costo pendiente' : 'Calculada',
            ])->all(),
            'note' => 'Este reporte usa el costo capturado por lote y distingue ventas, costos, devoluciones y descuentos.',
        ];
    }

    private function commissionsData(Shop $shop): array
    {
        $invoices = $shop->invoices()->with('salesperson')->where('commission_amount', '>', 0)->latest('issued_at')->limit(30)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas comisionables', 'value' => number_format($invoices->count()), 'tone' => 'blue'],
                ['label' => 'Comisiones', 'value' => $this->money($invoices->sum('commission_amount')), 'tone' => 'amber'],
                ['label' => 'Vendedores', 'value' => number_format($invoices->pluck('salesperson_id')->filter()->unique()->count()), 'tone' => 'emerald'],
            ],
            'rows' => $invoices->map(fn (Invoice $invoice) => [
                'primary' => $invoice->invoice_number,
                'secondary' => ($invoice->salesperson?->name ?: 'Vendedor no asignado').' · '.$this->money($invoice->total),
                'value' => $this->money($invoice->commission_amount),
                'status' => 'Calculada',
            ])->all(),
            'note' => 'Las comisiones se leen desde la fotografía guardada en cada venta; cambiar una regla no altera ventas históricas.',
        ];
    }

    private function money(float|int|string|null $amount): string
    {
        return 'RD$ '.number_format((float) $amount, 2);
    }

    private function invoiceStatus(Invoice $invoice): string
    {
        return match ($invoice->status) {
            'paid' => 'Pagada',
            'credit' => 'A crédito',
            'partial' => 'Parcial',
            'void' => 'Anulada',
            default => ucfirst((string) $invoice->status),
        };
    }

    private function orderStatus(Order $order): string
    {
        return match ($order->status) {
            'confirmed' => 'Confirmado',
            'cancelled' => 'Cancelado',
            default => 'Pendiente',
        };
    }
}
