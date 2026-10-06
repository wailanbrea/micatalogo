<?php

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\BusinessPartner;
use App\Models\CashRegisterSession;
use App\Models\CommercialQuote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PurchaseDocument;
use App\Models\Product;
use App\Models\Shop;
use App\Models\SupportRequest;
use App\Models\Supplier;
use App\Services\BusinessDashboardService;
use App\Services\SellerMenuService;
use Illuminate\Http\JsonResponse;
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
        'storefront' => ['group' => 'Catálogo', 'title' => 'Mi tienda', 'description' => 'Revisa la vitrina pública y la experiencia que ven tus clientes.', 'status' => 'En preparación'],
        'metrics' => ['group' => 'Catálogo', 'title' => 'Métricas y QR', 'description' => 'Consulta visitas, contactos de WhatsApp y el código QR de tu catálogo.', 'status' => 'En preparación'],
        'public_catalog' => ['group' => 'Catálogo', 'title' => 'Compartir catálogo', 'description' => 'Comparte el enlace de tu catálogo público con tus clientes.', 'status' => 'En preparación'],
        'pricing' => ['group' => 'Catálogo', 'title' => 'Precios automáticos', 'description' => 'Define margen y redondeo para actualizar precios al recibir nuevos costos.', 'status' => 'En preparación'],
        'import' => ['group' => 'Catálogo', 'title' => 'Importar', 'description' => 'Carga productos desde Excel, CSV o PDF y revisa la previsualización antes de guardar.', 'status' => 'En preparación'],
        'expenses' => ['group' => 'Finanzas', 'title' => 'Gastos', 'description' => 'Registra y consulta los gastos que afectan la caja y la rentabilidad.', 'status' => 'En preparación'],
        'shop_settings' => ['group' => 'Ajustes', 'title' => 'Configuración de tienda', 'description' => 'Personaliza identidad, catálogo, pedidos y reglas operativas de la tienda.', 'status' => 'En preparación'],
        'sellers' => ['group' => 'Ajustes', 'title' => 'Equipo', 'description' => 'Administra vendedores, permisos y comisiones sin exponer funciones administrativas.', 'status' => 'En preparación'],
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
     * Mobile read model for the same feature modules exposed by the web panel.
     * Mutations continue to use their dedicated API resources so this endpoint
     * cannot accidentally create or alter accounting data.
     */
    public function api(
        Request $request,
        Shop $shop,
        string $feature,
        SellerMenuService $menus,
        ?BusinessDashboardService $dashboard = null
    ): JsonResponse {
        abort_unless(isset(self::FEATURES[$feature]), 404);
        abort_unless(in_array($feature, $menus->visibleForUser($shop, $request->user()), true), 403);

        return response()->json([
            'feature_key' => $feature,
            'feature' => self::FEATURES[$feature],
            'module' => $this->moduleData($feature, $shop, $dashboard ?: app(BusinessDashboardService::class)),
        ]);
    }

    /**
     * Build read models from the existing accounting/catalog domains.
     * Read models deliberately reuse existing accounting/catalog domains. This
     * keeps every menu useful without inventing transactions or demo records.
     *
     * @return array{kind: string, kpis: array<int, array{label: string, value: string, tone: string}>, rows: array<int, array<string, mixed>>, note: ?string}
     */
    private function moduleData(string $feature, Shop $shop, BusinessDashboardService $dashboard): array
    {
        return match ($feature) {
            'sales' => $this->salesData($shop),
            'quotes' => $this->quotesData($shop),
            'orders' => $this->ordersData($shop),
            'encargos' => $this->ordersData($shop, 'pending'),
            'shipments' => $this->ordersData($shop, null, true),
            'day_close' => $this->dayCloseData($shop),
            'containers', 'loads', 'suppliers', 'purchase_invoices' => $this->purchasingData($shop, $feature),
            'photos' => $this->photosData($shop),
            'services' => $this->servicesData($shop),
            'price_health' => $this->priceHealthData($shop),
            'decants' => $this->decantsData($shop),
            'attributes' => $this->attributesData($shop),
            'credit' => $this->creditData($shop),
            'inventory_adjustments' => $this->inventoryAdjustmentsData($shop),
            'partners' => $this->partnersData($shop),
            'reports' => $this->reportsData($shop, $dashboard),
            'commissions' => $this->commissionsData($shop),
            'authorizations' => $this->authorizationsData($shop),
            'accountant' => $this->accountantData($shop, $dashboard),
            'account' => $this->accountData($shop),
            'updates' => $this->updatesData(),
            'help' => $this->helpData(),
            'practice' => $this->practiceData($shop),
            'support' => $this->supportData($shop),
            default => [
                'kind' => 'prepared',
                'kpis' => [],
                'rows' => [],
                'note' => null,
            ],
        };
    }

    private function quotesData(Shop $shop): array
    {
        $quotes = $shop->quotes()->with('items')->latest()->limit(30)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Cotizaciones', 'value' => number_format($shop->quotes()->count()), 'tone' => 'blue'],
                ['label' => 'En seguimiento', 'value' => number_format($shop->quotes()->whereIn('status', ['draft', 'sent'])->count()), 'tone' => 'amber'],
                ['label' => 'Convertidas a venta', 'value' => number_format($shop->quotes()->where('status', 'converted')->count()), 'tone' => 'emerald'],
            ],
            'rows' => $quotes->map(fn (CommercialQuote $quote) => [
                'primary' => $quote->quote_number,
                'secondary' => ($quote->customer_name ?: 'Cliente sin nombre').' · '.number_format($quote->items->sum('quantity')).' artículo(s)',
                'value' => $this->money($quote->total),
                'status' => match ($quote->status) { 'converted' => 'Convertida', 'sent' => 'Enviada', default => 'Borrador' },
                'can_convert' => $quote->converted_invoice_id === null && $quote->status !== 'cancelled',
                'id' => $quote->public_id,
            ])->all(),
            'quoteProducts' => $shop->products()->with(['shopCategory', 'globalCategory', 'inventory', 'images', 'primaryImage'])->whereIn('availability_status', ['available', 'out_of_stock'])->orderBy('name')->limit(1000)->get()->map(fn (Product $product) => [
                'id' => $product->public_id,
                'name' => $product->name,
                'price' => number_format($product->currentPrice(), 2, '.', ''),
                'category' => $product->shopCategory?->name ?: ($product->globalCategory?->name ?: 'Sin categoría'),
                'brand' => $product->brand ?: '',
                'code' => $product->product_code ?: '',
                'stock' => $product->inventory?->track_inventory ? (int) $product->inventory->stock_quantity : null,
                'image_url' => $product->primaryImage?->url,
                'sale_unit_label' => $product->saleUnitLabel(),
            ])->all(),
            'note' => 'Cada cotización queda separada de la contabilidad hasta que la conviertas. Al convertirla se valida stock, se consume FIFO y se crea una venta única.',
            'actions' => [
                ['label' => 'Ir a Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'primary'],
                ['label' => 'Ver pedidos', 'url' => route('seller.shops.feature', [$shop, 'feature' => 'orders']), 'tone' => 'secondary'],
            ],
        ];
    }

    private function purchasingData(Shop $shop, string $feature): array
    {
        $documents = $shop->purchaseDocuments()->with(['supplier', 'items.product'])->latest()->limit(30)->get();
        $suppliers = $shop->suppliers()->get();
        $products = $shop->products()->whereHas('inventory', fn ($query) => $query->where('track_inventory', true))->orderBy('name')->limit(300)->get();

        $labels = [
            'containers' => ['Contenedores', 'Agrupaciones de recepción', 'Compras agrupadas'],
            'loads' => ['Cargas', 'Recepciones en inventario', 'Movimientos de reposición'],
            'suppliers' => ['Suplidores', 'Fuentes de compra', 'Productos con costo'],
            'purchase_invoices' => ['Facturas de compra', 'Recepciones valorizadas', 'Lotes con costo'],
        ];
        [$title, $primaryLabel, $secondaryLabel] = $labels[$feature];
        $rows = $documents->filter(fn (PurchaseDocument $document) => match ($feature) {
            'containers' => $document->type === 'container',
            'loads' => $document->type === 'load',
            'purchase_invoices' => $document->type === 'purchase_invoice',
            default => true,
        })->map(fn (PurchaseDocument $document) => [
            'primary' => $document->document_number,
            'secondary' => ($document->supplier?->name ?: 'Sin suplidor').' · '.($document->received_at?->format('d/m/Y H:i') ?: 'Sin fecha'),
            'value' => $this->money($document->total),
            'status' => 'Recibida',
        ])->values()->all();
        if ($feature === 'suppliers') {
            $rows = $suppliers->map(fn (Supplier $supplier) => [
                'primary' => $supplier->name,
                'secondary' => $supplier->phone ?: ($supplier->email ?: 'Sin contacto'),
                'value' => number_format($supplier->purchaseDocuments()->count()).' compra(s)',
                'status' => 'Activo',
            ])->all();
        }

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => $primaryLabel, 'value' => number_format(count($rows)), 'tone' => 'blue'],
                ['label' => $secondaryLabel, 'value' => number_format($feature === 'suppliers' ? $suppliers->count() : $documents->where('status', 'received')->count()), 'tone' => 'emerald'],
                ['label' => 'Productos controlados', 'value' => number_format($products->count()), 'tone' => 'slate'],
            ],
            'rows' => $rows,
            'suppliers' => $suppliers->map(fn (Supplier $supplier) => ['id' => $supplier->public_id, 'name' => $supplier->name])->all(),
            'purchaseProducts' => $products->map(fn (Product $product) => ['id' => $product->public_id, 'name' => $product->name, 'cost' => number_format((float) ($product->inventory?->cost_price ?? 0), 2, '.', '')])->all(),
            'note' => "{$title} registra recepciones reales, crea un lote con el costo indicado y mantiene el historial FIFO. No se modifica ningún lote anterior.",
            'actions' => [
                ['label' => 'Ver inventario', 'url' => route('seller.shops.inventory.index', $shop), 'tone' => 'primary'],
                ['label' => 'Ver lotes y costos FIFO', 'url' => route('seller.shops.inventory.lots', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function servicesData(Shop $shop): array
    {
        $products = $shop->products()->with('inventory')->latest()->limit(30)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Catálogo disponible', 'value' => number_format($shop->products()->count()), 'tone' => 'blue'],
                ['label' => 'Con precio', 'value' => number_format($products->filter(fn (Product $product) => (float) $product->currentPrice() > 0)->count()), 'tone' => 'emerald'],
                ['label' => 'Sin inventario', 'value' => number_format($products->filter(fn (Product $product) => ! $product->inventory?->track_inventory)->count()), 'tone' => 'amber'],
            ],
            'rows' => $products->map(fn (Product $product) => [
                'primary' => $product->name,
                'secondary' => 'Precio RD$ '.number_format($product->currentPrice(), 2).' · '.ucfirst((string) $product->sale_unit),
                'value' => $product->inventory?->track_inventory ? number_format((int) $product->inventory->stock_quantity).' en stock' : 'Sin control de stock',
                'status' => 'Disponible para vender',
            ])->all(),
            'note' => 'Los servicios y productos sin inventario se pueden vender desde Terminal. La creación conserva el mismo catálogo para no duplicar artículos.',
            'actions' => [
                ['label' => 'Crear producto o servicio', 'url' => route('seller.shops.products.create', $shop), 'tone' => 'primary'],
                ['label' => 'Ir a Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function inventoryAdjustmentsData(Shop $shop): array
    {
        $movements = InventoryMovement::query()->whereIn('product_id', $shop->products()->select('id'))->where('type', 'adjustment')->latest()->limit(30)->with(['product', 'user'])->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ajustes registrados', 'value' => number_format($movements->count()), 'tone' => 'blue'],
                ['label' => 'Unidades afectadas', 'value' => number_format($movements->sum(fn (InventoryMovement $movement) => abs((int) $movement->quantity))), 'tone' => 'amber'],
                ['label' => 'Productos', 'value' => number_format($movements->pluck('product_id')->unique()->count()), 'tone' => 'slate'],
            ],
            'rows' => $movements->map(fn (InventoryMovement $movement) => [
                'primary' => $movement->product?->name ?: 'Producto eliminado',
                'secondary' => ($movement->notes ?: 'Ajuste manual').' · '.($movement->user?->name ?: 'Sistema'),
                'value' => sprintf('%+d unidades', (int) $movement->quantity),
                'status' => 'Auditado',
            ])->all(),
            'note' => 'Cada ajuste conserva stock anterior, stock nuevo, usuario y motivo; no se reescribe el historial de ventas.',
            'actions' => [['label' => 'Abrir inventario', 'url' => route('seller.shops.inventory.index', $shop), 'tone' => 'primary']],
        ];
    }

    private function partnersData(Shop $shop): array
    {
        $partners = $shop->partners()->with('transactions')->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Socios activos', 'value' => number_format($partners->count()), 'tone' => 'blue'],
                ['label' => 'Aportes', 'value' => $this->money($partners->flatMap->transactions->where('type', 'contribution')->sum('amount')), 'tone' => 'emerald'],
                ['label' => 'Retiros', 'value' => $this->money($partners->flatMap->transactions->whereIn('type', ['withdrawal', 'distribution'])->sum('amount')), 'tone' => 'rose'],
            ],
            'rows' => $partners->map(fn (BusinessPartner $partner) => [
                'primary' => $partner->name,
                'secondary' => number_format((float) $partner->ownership_percent, 2).'% de participación · '.($partner->email ?: ($partner->phone ?: 'Sin contacto')),
                'value' => $this->money($partner->transactions->where('type', 'contribution')->sum('amount') - $partner->transactions->whereIn('type', ['withdrawal', 'distribution'])->sum('amount')),
                'status' => 'Activo',
                'id' => $partner->public_id,
            ])->all(),
            'partners' => $partners->map(fn (BusinessPartner $partner) => ['id' => $partner->public_id, 'name' => $partner->name])->all(),
            'note' => 'Los aportes y retiros se registran mediante la sesión de caja abierta y quedan enlazados al movimiento contable correspondiente.',
            'actions' => [['label' => 'Ver caja', 'url' => route('seller.shops.cash.index', $shop), 'tone' => 'primary']],
        ];
    }

    private function authorizationsData(Shop $shop): array
    {
        $sellers = $shop->sellers()->with('user')->where('is_active', true)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Usuarios con acceso', 'value' => number_format($sellers->count()), 'tone' => 'blue'],
                ['label' => 'Con menú personalizado', 'value' => number_format($sellers->filter(fn ($seller) => is_array($seller->menu_permissions) && count($seller->menu_permissions))->count()), 'tone' => 'emerald'],
                ['label' => 'Pendientes de revisar', 'value' => number_format($sellers->filter(fn ($seller) => ! $seller->menu_permissions)->count()), 'tone' => 'amber'],
            ],
            'rows' => $sellers->map(fn ($seller) => [
                'primary' => $seller->user?->name ?: 'Vendedor',
                'secondary' => $seller->user?->email ?: 'Sin correo',
                'value' => is_array($seller->menu_permissions) && count($seller->menu_permissions) ? count($seller->menu_permissions).' menús' : 'Permisos estándar',
                'status' => 'Activo',
            ])->all(),
            'note' => 'Las autorizaciones actuales se administran desde Equipo y se aplican por tienda. Las acciones financieras sensibles siguen protegidas por permisos del servidor.',
            'actions' => [['label' => 'Administrar equipo', 'url' => route('seller.shops.sellers.index', $shop), 'tone' => 'primary']],
        ];
    }

    private function accountantData(Shop $shop, BusinessDashboardService $dashboard): array
    {
        $summary = $dashboard->getSummary($shop, now()->startOfMonth()->toDateString(), now()->toDateString(), 'profit', 'desc');

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Ventas netas', 'value' => $this->money($summary['period']['net_sales']), 'tone' => 'blue'],
                ['label' => 'Ganancia bruta', 'value' => $this->money($summary['period']['gross_profit']), 'tone' => 'emerald'],
                ['label' => 'Gastos operativos', 'value' => $this->money($summary['period']['operating_expenses']), 'tone' => 'rose'],
            ],
            'rows' => [
                ['primary' => 'Estado financiero del mes', 'secondary' => 'Ventas, costos, gastos y comisiones integrados', 'value' => $this->money($summary['period']['operating_profit']), 'status' => 'Disponible'],
                ['primary' => 'Costos FIFO', 'secondary' => 'Costo real capturado por lote', 'value' => $this->money($summary['current_state']['inventory_cost_value'] ?? 0), 'status' => 'Disponible'],
            ],
            'note' => 'El contador consulta la misma fuente financiera que Ganancias; no se crean asientos paralelos.',
            'actions' => [
                ['label' => 'Ver ganancias', 'url' => route('seller.shops.business', $shop), 'tone' => 'primary'],
                ['label' => 'Ver reportes', 'url' => route('seller.shops.feature', [$shop, 'feature' => 'reports']), 'tone' => 'secondary'],
            ],
        ];
    }

    private function accountData(Shop $shop): array
    {
        $user = request()->user();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Cuenta', 'value' => $user->planLabel(), 'tone' => 'blue'],
                ['label' => 'Tiendas', 'value' => number_format($user->shops()->count()), 'tone' => 'emerald'],
                ['label' => 'Correo', 'value' => $user->email, 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => $user->name, 'secondary' => $user->email, 'value' => $user->email_verified_at ? 'Verificado' : 'Pendiente', 'status' => 'Mi cuenta'],
                ['primary' => $shop->name, 'secondary' => 'Tienda activa', 'value' => $shop->planLabel(), 'status' => 'Plan vigente'],
            ],
            'note' => 'La seguridad, el plan y los datos de tu tienda se administran en sus pantallas protegidas.',
            'actions' => [['label' => 'Configuración de tienda', 'url' => route('seller.shops.edit', $shop), 'tone' => 'primary']],
        ];
    }

    private function updatesData(): array
    {
        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Versión web', 'value' => config('app.version', 'Actual'), 'tone' => 'blue'],
                ['label' => 'Módulos activos', 'value' => '28', 'tone' => 'emerald'],
                ['label' => 'Estado', 'value' => 'Operativo', 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => 'Menú organizado por operación', 'secondary' => 'Operación, Compras, Catálogo, Cobros, Finanzas, Análisis, Equipo y Ajustes', 'value' => 'Publicado', 'status' => 'Listo'],
                ['primary' => 'Cierre de día y caja', 'secondary' => 'Arqueo conectado a la sesión financiera existente', 'value' => 'Publicado', 'status' => 'Listo'],
                ['primary' => 'Costos FIFO y precios automáticos', 'secondary' => 'Cada lote conserva su costo y las bajadas esperan aprobación', 'value' => 'Publicado', 'status' => 'Listo'],
            ],
            'note' => 'Las novedades se muestran aquí para que el equipo conozca qué cambió sin interrumpir la operación.',
            'actions' => [],
        ];
    }

    private function helpData(): array
    {
        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Guías rápidas', 'value' => '5', 'tone' => 'blue'],
                ['label' => 'Flujos principales', 'value' => '7', 'tone' => 'emerald'],
                ['label' => 'Soporte', 'value' => 'Disponible', 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => 'Vender', 'secondary' => 'Selecciona productos, define pago y confirma la operación', 'value' => 'Terminal', 'status' => 'Guía'],
                ['primary' => 'Controlar inventario', 'secondary' => 'Repón, ajusta y consulta lotes con costo FIFO', 'value' => 'Inventario', 'status' => 'Guía'],
                ['primary' => 'Cobrar crédito', 'secondary' => 'Registra cuentas por cobrar y abonos desde Clientes', 'value' => 'Cobros', 'status' => 'Guía'],
            ],
            'note' => 'Cada guía enlaza a la pantalla operativa correspondiente para aprender haciendo.',
            'actions' => [['label' => 'Contactar soporte', 'url' => route('support.create'), 'tone' => 'primary']],
        ];
    }

    private function practiceData(Shop $shop): array
    {
        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Modo práctica', 'value' => 'Seguro', 'tone' => 'blue'],
                ['label' => 'Datos reales', 'value' => 'Sin cambios', 'tone' => 'emerald'],
                ['label' => 'Tienda', 'value' => $shop->name, 'tone' => 'slate'],
            ],
            'rows' => [
                ['primary' => 'Explora Terminal', 'secondary' => 'Revisa productos y carrito sin enviar la venta', 'value' => 'Abrir', 'status' => 'Seguro'],
                ['primary' => 'Explora Inventario', 'secondary' => 'Consulta stock, lotes y costos antes de operar', 'value' => 'Abrir', 'status' => 'Seguro'],
                ['primary' => 'Explora Caja', 'secondary' => 'Consulta el estado de la sesión actual', 'value' => 'Abrir', 'status' => 'Seguro'],
            ],
            'note' => 'Las acciones contables requieren confirmación en sus pantallas reales. Practicar sin miedo no crea ventas ni modifica existencias.',
            'actions' => [
                ['label' => 'Explorar Terminal', 'url' => route('seller.shops.pos', $shop), 'tone' => 'primary'],
                ['label' => 'Explorar inventario', 'url' => route('seller.shops.inventory.index', $shop), 'tone' => 'secondary'],
            ],
        ];
    }

    private function supportData(Shop $shop): array
    {
        $requests = SupportRequest::query()->where('shop_id', $shop->id)->latest()->limit(20)->get();

        return [
            'kind' => 'table',
            'kpis' => [
                ['label' => 'Solicitudes', 'value' => number_format($requests->count()), 'tone' => 'blue'],
                ['label' => 'Abiertas', 'value' => number_format($requests->where('status', 'open')->count()), 'tone' => 'amber'],
                ['label' => 'Resueltas', 'value' => number_format($requests->where('status', 'resolved')->count()), 'tone' => 'emerald'],
            ],
            'rows' => $requests->map(fn (SupportRequest $request) => [
                'primary' => $request->subject,
                'secondary' => ($request->category ?: 'Soporte').' · '.($request->created_at?->format('d/m/Y H:i') ?: 'Sin fecha'),
                'value' => ucfirst((string) $request->status),
                'status' => $request->status === 'resolved' ? 'Resuelta' : 'En seguimiento',
            ])->all(),
            'note' => 'Las solicitudes quedan asociadas a la tienda para que el equipo pueda dar seguimiento sin perder contexto.',
            'actions' => [['label' => 'Nueva solicitud', 'url' => route('support.create'), 'tone' => 'primary']],
        ];
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
